<?php
session_start();
include "../config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: users.php?error=invalid"); exit();
}

$user_id  = (int)$_GET['id'];
$admin_id = (int)$_SESSION['admin_id'];

if ($user_id === $admin_id) {
    header("Location: users.php?error=invalid"); exit();
}

$res  = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $res ? $res->fetch_assoc() : null;

if (!$user) {
    header("Location: users.php?error=notfound"); exit();
}

if ($user['role'] === 'admin') {
    header("Location: users.php?error=cannot_delete_admin"); exit();
}

// Xóa dữ liệu liên quan
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("DELETE FROM reviews WHERE user_id = $user_id");
$conn->query("DELETE p FROM payments p INNER JOIN bookings b ON p.booking_id = b.id WHERE b.user_id = $user_id");
$conn->query("DELETE FROM bookings WHERE user_id = $user_id");
$conn->query("DELETE FROM favorites WHERE user_id = $user_id");
$conn->query("DELETE FROM users WHERE id = $user_id AND role != 'admin'");
$deleted = $conn->affected_rows;
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

if ($deleted > 0) {
    log_activity($conn, 'delete_user', 'user', $user_id, "Xóa tài khoản: " . $user['full_name'] . " (" . $user['email'] . ")");
    header("Location: users.php?deleted=1&name=" . urlencode($user['full_name']));
} else {
    header("Location: users.php?error=failed");
}
exit();