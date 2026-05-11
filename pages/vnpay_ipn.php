<?php
/**
 * VNPay IPN — server-to-server notification (VNPay gọi vào đây sau khi giao dịch hoàn tất).
 * URL này cần public (Railway production tự hoạt động; localhost cần ngrok).
 * VNPay gửi GET params, kỳ vọng response JSON: {"RspCode":"00","Message":"Confirm Success"}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
header('Content-Type: application/json');

// Xác minh chữ ký
if (!vnpay_verify_signature($_GET)) {
    echo json_encode(['RspCode' => '97', 'Message' => 'Invalid signature']);
    exit();
}

$orderCode    = $_GET['vnp_TxnRef']       ?? '';
$responseCode = $_GET['vnp_ResponseCode'] ?? '';
$vnpAmount    = intval($_GET['vnp_Amount'] ?? 0) / 100;

// Kiểm tra đơn tồn tại và số tiền khớp
$stmt = $conn->prepare("
    SELECT b.id AS booking_id, p.payment_status, p.amount
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
    echo json_encode(['RspCode' => '01', 'Message' => 'Order not found']);
    exit();
}

// Cho phép sai lệch ±1 VNĐ do làm tròn
if (abs($row['amount'] - $vnpAmount) > 1) {
    echo json_encode(['RspCode' => '04', 'Message' => 'Invalid amount']);
    exit();
}

// Đã xử lý rồi (idempotent)
if ($row['payment_status'] !== 'pending') {
    echo json_encode(['RspCode' => '02', 'Message' => 'Order already updated']);
    exit();
}

$bookingId = $row['booking_id'];

if ($responseCode === '00') {
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

echo json_encode(['RspCode' => '00', 'Message' => 'Confirm Success']);
