<?php
// AJAX image upload endpoint for blog editor — admin only
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Auth check
if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['error' => 'Không có file']);
    exit;
}

$check = validate_image_upload($_FILES['image'], 5 * 1024 * 1024, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
if (!$check['success']) {
    echo json_encode(['error' => $check['message']]);
    exit;
}

$upload_dir = __DIR__ . '/../assets/images/blog/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$filename = safe_filename('blog', $check['ext']);
if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
    echo json_encode(['url' => '/assets/images/blog/' . $filename]);
} else {
    echo json_encode(['error' => 'Lưu file thất bại, kiểm tra quyền thư mục']);
}
