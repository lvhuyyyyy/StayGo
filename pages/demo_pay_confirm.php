<?php
/**
 * demo_pay_confirm.php — CHỈ DÙNG ĐỂ DEMO
 * Đánh dấu payment là 'paid' cho booking_id được truyền vào.
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$booking_id = intval($_POST['booking_id'] ?? 0);
if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Thiếu booking_id']);
    exit;
}

// Kiểm tra payment tồn tại và còn pending
$stmt = $conn->prepare("SELECT id FROM payments WHERE booking_id = ? AND payment_status = 'pending' ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy giao dịch pending']);
    exit;
}

$pay_id = $payment['id'];

// Đổi payment → paid
$conn->query("UPDATE payments SET payment_status = 'paid' WHERE id = $pay_id");

// Đổi booking → confirmed
$conn->query("UPDATE bookings SET status = 'confirmed' WHERE id = $booking_id AND status = 'pending'");

echo json_encode(['success' => true, 'message' => 'Thanh toán đã được xác nhận!']);
