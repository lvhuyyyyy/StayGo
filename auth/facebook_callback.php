<?php
session_start();
require_once '../config/database.php';

require_once '../config/secrets.php';
$app_id       = FB_APP_ID;
$app_secret   = FB_APP_SECRET;
$redirect_uri = FB_REDIRECT_URI;

if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    echo "STATE từ Facebook: " . ($_GET['state'] ?? 'null') . "<br>";
    echo "STATE trong SESSION: " . ($_SESSION['oauth_state'] ?? 'null');
    exit("Lỗi xác thực state!");
}

if (!isset($_GET['code'])) {
    die("Không có code từ Facebook!");
}

// Đổi code lấy access token
$token_data = json_decode(file_get_contents("https://graph.facebook.com/v19.0/oauth/access_token", false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded",
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ])
    ]
])));

if (!isset($token_data->access_token)) {
    die("Lỗi lấy token!");
}

// Lấy thông tin user từ Facebook
$user_info = json_decode(file_get_contents(
    "https://graph.facebook.com/v19.0/me?fields=id,name,email&access_token=" . $token_data->access_token
));

$email = $user_info->email ?? null;
$name  = $user_info->name;

// Nếu Facebook không trả về email
if (!$email) {
    die("Không lấy được email từ Facebook. Vui lòng dùng tài khoản đã xác minh email.");
}

// Kiểm tra user đã tồn tại chưa
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Tạo tài khoản mới
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
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
    header("Location: /admin_lvhuy_kontum/dashboard.php");
} else {
    $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
    unset($_SESSION['redirect_after_login']);
    header("Location: " . $redirect);
}
exit();