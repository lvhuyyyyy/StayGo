<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_FILES['image'])) {
    echo json_encode(['error' => 'Không có file']);
    exit;
}

$file    = $_FILES['image'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($ext, $allowed)) {
    echo json_encode(['error' => 'Chỉ chấp nhận jpg, jpeg, png, webp, gif']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'File quá lớn (max 5MB)']);
    exit;
}

$upload_dir = __DIR__ . '/../assets/images/blog/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = 'blog_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['url' => '/assets/images/blog/' . $filename]);
} else {
    echo json_encode(['error' => 'Lưu file thất bại, kiểm tra quyền thư mục']);
}

