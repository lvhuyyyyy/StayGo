<?php
/**
 * MoMo IPN — server-to-server POST JSON notification.
 * URL này cần public (Railway production tự hoạt động; localhost cần ngrok).
 * MoMo gửi POST body JSON và kỳ vọng HTTP 204 hoặc JSON {"status":0}.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../includes/email_helper.php';
header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

// Xác minh chữ ký
$data['signature'] = $data['signature'] ?? '';
if (!momo_verify_signature($data)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'Invalid signature']);
    exit();
}

$orderCode  = $data['orderId']     ?? '';
$resultCode = intval($data['resultCode'] ?? -1);

// Lấy booking để kiểm tra tồn tại
$stmt = $conn->prepare("
    SELECT b.id AS booking_id, p.payment_status
    FROM bookings b
    JOIN payments p ON p.booking_id = b.id
    WHERE b.order_code = ?
    ORDER BY p.id DESC LIMIT 1
");
$stmt->bind_param('s', $orderCode);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 404, 'message' => 'Order not found']);
    exit();
}

// Idempotent
if ($row['payment_status'] !== 'pending') {
    echo json_encode(['status' => 0, 'message' => 'Already processed']);
    exit();
}

$bookingId = $row['booking_id'];

if ($resultCode === 0) {
    $upd = $conn->prepare("
        UPDATE payments p
        JOIN bookings b ON p.booking_id = b.id
        SET p.payment_status   = 'paid',
            p.payment_verified = 1,
            p.verified_at      = NOW(),
            b.status           = 'confirmed'
        WHERE b.id = ?
    ");
    $upd->bind_param('i', $bookingId);
    $upd->execute();
    $upd->close();

    // Ghi booking_log
    $conn->query("
        INSERT INTO booking_logs (booking_id, actor_type, actor_name, action, description)
        VALUES ($bookingId, 'SYSTEM', 'MoMo IPN', 'PAYMENT_CONFIRMED', 'Thanh toán MoMo xác nhận qua IPN.')
    ");

    // Gửi email hotel
    $binfo = $conn->query("
        SELECT b.order_code, b.full_name, b.email, b.check_in, b.check_out, b.total_price,
               r.room_name, h.name AS hotel_name, h.partner_email AS hotel_email
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN hotels h ON r.hotel_id = h.id
        WHERE b.id = $bookingId LIMIT 1
    ")->fetch_assoc();
    if ($binfo && !empty($binfo['hotel_email'])) {
        send_hotel_new_booking_email($binfo['hotel_email'], [
            'order_code'     => $binfo['order_code'],
            'hotel_name'     => $binfo['hotel_name'],
            'full_name'      => $binfo['full_name'],
            'guest_email'    => $binfo['email'],
            'room_name'      => $binfo['room_name'],
            'checkin'        => $binfo['check_in'],
            'checkout'       => $binfo['check_out'],
            'payment_method' => 'Ví MoMo (IPN)',
            'amount'         => $binfo['total_price'],
        ]);
    }
} else {
    $upd = $conn->prepare("UPDATE payments SET payment_status = 'failed' WHERE booking_id = ?");
    $upd->bind_param('i', $bookingId);
    $upd->execute();
    $upd->close();
}

echo json_encode(['status' => 0, 'message' => 'success']);
