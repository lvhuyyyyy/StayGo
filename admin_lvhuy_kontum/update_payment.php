<?php
include("../config/database.php");

$id     = isset($_GET['id'])     ? (int)$_GET['id']                          : 0;
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

$allowed = ['paid', 'failed', 'refunded'];

if (!$id || !in_array($status, $allowed)) {
    header("Location: payments.php?error=invalid");
    exit;
}

// Kiểm tra tồn tại
$check = $conn->query("SELECT id, payment_status, booking_id FROM payments WHERE id = $id");
if ($check->num_rows === 0) {
    header("Location: payments.php?error=notfound");
    exit;
}

$payment        = $check->fetch_assoc();
$current_status = trim($payment['payment_status']);

// Chính sách:
// - failed/refunded -> không làm gì thêm
// - paid -> chỉ cho phép hoàn tiền (refunded)
// - pending -> cho phép paid hoặc failed
if (in_array($current_status, ['failed', 'refunded'])) {
    header("Location: payments.php?error=already_done");
    exit;
}
if ($current_status === 'paid' && $status !== 'refunded') {
    header("Location: payments.php?error=already_done");
    exit;
}

// Cập nhật payment_status
$conn->query("UPDATE payments SET payment_status = '$status' WHERE id = $id");

// Đồng bộ bookings
if (!empty($payment['booking_id'])) {
    $booking_id = (int)$payment['booking_id'];
    if ($status === 'paid') {
        $conn->query("UPDATE bookings SET status = 'confirmed' WHERE id = $booking_id AND status NOT IN ('completed','cancelled')");
    } elseif ($status === 'failed') {
        $conn->query("UPDATE bookings SET status = 'cancelled' WHERE id = $booking_id AND status = 'pending'");
    } elseif ($status === 'refunded') {
        $conn->query("UPDATE bookings SET status = 'cancelled' WHERE id = $booking_id");
    }
}

log_activity($conn, 'update_payment', 'payment', $id, "Giao dịch #$id → $status");
header("Location: payments.php?success=$status");
exit;

