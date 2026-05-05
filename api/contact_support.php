<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

csrf_check();

$full_name = trim($_POST['full_name'] ?? '');
$phone     = trim($_POST['phone']     ?? '');
$email     = trim($_POST['email']     ?? '');
$subject   = trim($_POST['subject']   ?? '');
$note      = trim($_POST['note']      ?? '');
$user_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (empty($full_name) || empty($phone) || empty($note)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin bắt buộc!']);
    exit;
}

if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ!']);
    exit;
}

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ!']);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO support_requests (user_id, full_name, phone, email, subject, note, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
");
$stmt->bind_param("isssss", $user_id, $full_name, $phone, $email, $subject, $note);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Yêu cầu đã được gửi! Chúng tôi sẽ liên hệ sớm nhất.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại!']);
}
