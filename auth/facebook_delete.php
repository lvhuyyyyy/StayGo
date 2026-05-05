<?php
// Facebook Data Deletion Callback
// Meta yêu cầu endpoint này khi user muốn xóa dữ liệu

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Lấy signed_request từ Facebook
$signed_request = $_POST['signed_request'] ?? null;

if (!$signed_request) {
    echo json_encode(['error' => 'No signed_request']);
    exit();
}

require_once '../config/secrets.php';
$app_secret = FB_APP_SECRET;

// Giải mã signed_request
list($encoded_sig, $payload) = explode('.', $signed_request, 2);

$data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

// Xác thực chữ ký
$expected_sig = hash_hmac('sha256', $payload, $app_secret, true);
$sig = base64_decode(strtr($encoded_sig, '-_', '+/'));

if ($sig !== $expected_sig) {
    echo json_encode(['error' => 'Invalid signature']);
    exit();
}

// Lấy Facebook user ID
$fb_user_id = $data['user_id'] ?? null;

// Xóa dữ liệu user nếu cần (tùy logic của bạn)
// Ở đây chỉ log lại, bạn có thể thêm DELETE query nếu muốn
// $conn->query("DELETE FROM users WHERE facebook_id = '$fb_user_id'");

// Tạo confirmation code
$confirmation_code = md5($fb_user_id . time());

// Trả về response theo yêu cầu của Facebook
$base_url = "https://superadaptable-noneffusively-evon.ngrok-free.dev/tour_khach_san_project";

echo json_encode([
    'url'  => $base_url . '/pages/delete_confirm.php?code=' . $confirmation_code,
    'confirmation_code' => $confirmation_code
]);
exit();