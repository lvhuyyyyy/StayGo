<?php
session_start();
require_once '../config/database.php';

require_once '../config/secrets.php';
$client_id     = GOOGLE_CLIENT_ID;
$client_secret = GOOGLE_CLIENT_SECRET;
$redirect_uri  = GOOGLE_REDIRECT_URI;

// Kiểm tra state
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die("Lỗi xác thực state!");
}

if (!isset($_GET['code'])) {
    die("Không có code từ Google!");
}

// Đổi code lấy access token
$token_data = json_decode(file_get_contents("https://oauth2.googleapis.com/token", false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded",
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ])
    ]
])));

if (!isset($token_data->access_token)) {
    die("Lỗi lấy token!");
}

// Lấy thông tin user từ Google
$user_info = json_decode(file_get_contents("https://www.googleapis.com/oauth2/v2/userinfo", false, stream_context_create([
    'http' => [
        'header' => "Authorization: Bearer " . $token_data->access_token
    ]
])));

$email = $user_info->email;
$name  = $user_info->name;

// Kiểm tra user đã tồn tại chưa
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Tạo tài khoản mới
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'user')");
    $stmt2->bind_param("sss", $name, $email, $random_password);
    $stmt2->execute();
    $user_id = $conn->insert_id;
    $role = 'user';
} else {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $role    = $user['role'];
}

// Lưu session
$_SESSION['user_id'] = $user_id;
$_SESSION['email']   = $email;
$_SESSION['role']    = $role;

// Redirect
if ($role === 'admin') {
    header("Location: /admin/dashboard.php");
} else {
    $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
    unset($_SESSION['redirect_after_login']);
    header("Location: " . $redirect);
}
exit();