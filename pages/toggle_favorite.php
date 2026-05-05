<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để lưu yêu thích.', 'login_required' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$hotel_id = (int) ($_POST['hotel_id'] ?? 0);

if ($hotel_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Hotel không hợp lệ.']);
    exit;
}

// Kiểm tra khách sạn tồn tại
$chk = $conn->prepare("SELECT id FROM hotels WHERE id = ? AND is_active = 1");
$chk->bind_param("i", $hotel_id);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Khách sạn không tồn tại.']);
    exit;
}

// Kiểm tra đã lưu chưa
$exist = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND hotel_id = ?");
$exist->bind_param("ii", $user_id, $hotel_id);
$exist->execute();
$row = $exist->get_result()->fetch_assoc();

if ($row) {
    // Xóa yêu thích
    $del = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND hotel_id = ?");
    $del->bind_param("ii", $user_id, $hotel_id);
    $del->execute();
    echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Đã xóa khỏi danh sách yêu thích.']);
} else {
    // Thêm yêu thích
    $ins = $conn->prepare("INSERT INTO favorites (user_id, hotel_id) VALUES (?, ?)");
    $ins->bind_param("ii", $user_id, $hotel_id);
    $ins->execute();
    echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Đã lưu vào danh sách yêu thích!']);
}
