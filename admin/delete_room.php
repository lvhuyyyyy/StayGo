<?php
require_once 'admin_bootstrap.php'; // auth + CSRF
csrf_check();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/rooms.php'); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: /admin/rooms.php?error=invalid_id');
    exit;
}

$res  = $conn->query("SELECT * FROM rooms WHERE id = $id");
$room = $res ? $res->fetch_assoc() : null;
if (!$room) {
    header('Location: /admin/rooms.php?error=not_found');
    exit;
}

$res2 = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE room_id = $id AND status IN ('pending', 'confirmed')");
$active_bookings = $res2 ? (int)$res2->fetch_assoc()['total'] : 0;

if ($active_bookings > 0) {
    header('Location: /admin/rooms.php?error=has_bookings&count=' . $active_bookings);
    exit;
}

$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("DELETE p FROM payments p INNER JOIN bookings b ON p.booking_id = b.id WHERE b.room_id = $id");
$conn->query("DELETE FROM bookings WHERE room_id = $id");

if (!empty($room['image'])) {
    $img_path = __DIR__ . '/../assets/images/' . $room['image'];
    if (file_exists($img_path)) @unlink($img_path);
}

$conn->query("DELETE FROM rooms WHERE id = $id");
$deleted = $conn->affected_rows;
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
if ($deleted > 0) {
    log_activity($conn, 'delete_room', 'room', $id, "Xóa phòng: {$room['room_name']}");
    $hotel_id = (int)$room['hotel_id'];
    $conn->query("UPDATE hotels SET price = COALESCE((SELECT MIN(price) FROM rooms WHERE hotel_id = $hotel_id), 0) WHERE id = $hotel_id");
}

if ($deleted > 0) {
    header('Location: /admin/rooms.php?success=deleted');
} else {
    header('Location: /admin/rooms.php?error=failed');
}
exit;

