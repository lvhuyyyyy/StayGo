<?php
/**
 * MoMo IPN — server-to-server POST JSON notification.
 * URL này cần public (Railway production tự hoạt động; localhost cần ngrok).
 * MoMo gửi POST body JSON và kỳ vọng HTTP 204 hoặc JSON {"status":0}.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
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
        SET p.payment_status = 'paid', b.status = 'confirmed'
        WHERE b.id = ?
    ");
    $upd->bind_param('i', $bookingId);
    $upd->execute();
    $upd->close();
} else {
    $upd = $conn->prepare("UPDATE payments SET payment_status = 'failed' WHERE booking_id = ?");
    $upd->bind_param('i', $bookingId);
    $upd->execute();
    $upd->close();
}

echo json_encode(['status' => 0, 'message' => 'success']);
