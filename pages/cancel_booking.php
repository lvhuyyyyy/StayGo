<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$booking_id = (int)($_GET['id'] ?? 0);
$user_id    = $_SESSION['user_id'];

if (!$booking_id) {
    header("Location: my_bookings.php?error=invalid");
    exit();
}

// Chỉ cho hủy booking pending thuộc về user này
$stmt = $conn->prepare("
    SELECT id FROM bookings 
    WHERE id = ? AND user_id = ? AND status = 'pending'
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    header("Location: my_bookings.php?error=notfound");
    exit();
}

// Cập nhật sang cancelled
$upd = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
$upd->bind_param("i", $booking_id);
$upd->execute();

header("Location: my_bookings.php?cancelled=1");
exit();

