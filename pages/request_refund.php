<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    header("Location: my_bookings.php?error=invalid");
    exit;
}

// Lấy booking, kiểm tra quyền sở hữu
$res     = $conn->query("SELECT * FROM bookings WHERE id = $booking_id AND user_id = $user_id");
$booking = $res ? $res->fetch_assoc() : null;

if (!$booking) {
    header("Location: my_bookings.php?error=notfound");
    exit;
}

// Kiểm tra điều kiện
if ($booking['status'] !== 'confirmed') {
    header("Location: my_bookings.php?error=not_confirmed");
    exit;
}
if ($booking['refund_requested']) {
    header("Location: my_bookings.php?error=already_requested");
    exit;
}

$hours_to_checkin = (strtotime($booking['check_in']) - time()) / 3600;
if ($hours_to_checkin <= 24) {
    header("Location: my_bookings.php?error=too_late");
    exit;
}

// Tính số tiền hoàn (80%)
$refund_amount = $booking['total_price'] * 0.8;

// Lưu yêu cầu hoàn tiền
$conn->query("
    UPDATE bookings
    SET refund_requested    = 1,
        refund_requested_at = NOW(),
        refund_amount       = $refund_amount
    WHERE id = $booking_id
");

header("Location: my_bookings.php?refund=requested");
exit;