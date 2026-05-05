<?php
/**
 * check_payment.php — đặt vào /pages/check_payment.php
 * API polling: frontend gọi mỗi 5 giây để kiểm tra trạng thái thanh toán
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$booking_id = intval($_GET['booking_id'] ?? 0);

if (!$booking_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing booking_id']);
    exit();
}

// Lấy payment_status + order_code cùng lúc
$stmt = $conn->prepare("
    SELECT p.payment_status, b.order_code
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE p.booking_id = ?
    ORDER BY p.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result  = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    echo json_encode(['status' => 'pending', 'booking_id' => $booking_id]);
    exit();
}

// Nếu đã paid → tự động xác nhận booking
if ($payment['payment_status'] === 'paid') {
    $upd = $conn->prepare("
        UPDATE bookings SET status = 'confirmed'
        WHERE id = ? AND status = 'pending'
    ");
    $upd->bind_param("i", $booking_id);
    $upd->execute();
}

echo json_encode([
    'status'     => $payment['payment_status'],  // 'pending' | 'paid' | 'failed'
    'booking_id' => $booking_id,
    'order_code' => $payment['order_code'],     // JS dùng để hiển thị trên overlay
]);