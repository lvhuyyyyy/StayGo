<?php
session_start();
include("../config/database.php");
include("../includes/activity_helper.php");

$id     = isset($_GET['id'])     ? (int)$_GET['id']                          : 0;
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Map các giá trị cũ (paid/cancel) sang giá trị chuẩn
$status_alias = [
    'paid'      => 'confirmed',
    'cancel'    => 'cancelled',
    'confirmed' => 'confirmed',
    'cancelled' => 'cancelled',
    'completed' => 'completed',
];

// Normalize status
$status = $status_alias[$status] ?? '';

// Chỉ cho phép các trạng thái hợp lệ
$allowed = ['confirmed', 'cancelled', 'completed'];

if (!$id || !in_array($status, $allowed)) {
    header("Location: bookings.php?error=invalid");
    exit;
}

// Kiểm tra booking tồn tại
$check = $conn->query("SELECT id, status FROM bookings WHERE id = $id");
if ($check->num_rows === 0) {
    header("Location: bookings.php?error=notfound");
    exit;
}

$booking = $check->fetch_assoc();

// Không cho thay đổi nếu đã hủy hoặc hoàn thành
if (in_array($booking['status'], ['cancelled', 'completed'])) {
    header("Location: bookings.php?error=already_done");
    exit;
}

// Cập nhật trạng thái
$conn->query("UPDATE bookings SET status = '$status' WHERE id = $id");

// Ghi nhật ký
log_activity($conn, $status === 'confirmed' ? 'confirm_booking' : ($status === 'cancelled' ? 'cancel_booking' : 'complete_booking'), 'booking', $id, "Đơn #$id → $status");

// Redirect về trang chi tiết hoặc danh sách
if (isset($_GET['redirect']) && $_GET['redirect'] === 'detail') {
    header("Location: booking_detail.php?id=$id&success=$status");
} else {
    header("Location: bookings.php?success=$status");
}
exit;

