<?php
session_start();
include "../config/database.php";

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header("Location: " . BASE_PATH . "/admin/dashboard.php");
    exit;
}

// ------------------------------------------
// BRUTE FORCE PROTECTION - Admin
// ------------------------------------------
$max_attempts = 3;       // Admin chỉ cho 3 lần
$lockout_time = 1800;    // Khóa 30 phút

$ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attempt_key = 'admin_attempts_' . md5($ip);
$lockout_key = 'admin_lockout_'  . md5($ip);

$error = '';

if (isset($_SESSION[$lockout_key])) {
    $remaining = $_SESSION[$lockout_key] - time();
    if ($remaining > 0) {
        $mins  = ceil($remaining / 60);
        $error = "Tài khoản bị khóa. Thử lại sau $mins phút.";
    } else {
        unset($_SESSION[$lockout_key], $_SESSION[$attempt_key]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && $user['role'] === 'admin' && password_verify($password, $user['password'])) {
            // ✔ Thành công
            unset($_SESSION[$attempt_key], $_SESSION[$lockout_key]);
            session_regenerate_id(true);

            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_name'] = $user['full_name'];
            // Xóa session user nếu đang đăng nhập user trước đó
            unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['full_name']);

            header("Location: " . BASE_PATH . "/admin/dashboard.php");
            exit;

        } else {
            // ❌ Sai
            $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;
            $left = $max_attempts - $_SESSION[$attempt_key];

            if ($_SESSION[$attempt_key] >= $max_attempts) {
                $_SESSION[$lockout_key] = time() + $lockout_time;
                unset($_SESSION[$attempt_key]);
                $error = "Đăng nhập sai quá nhiều lần. Tài khoản admin bị khóa 30 phút!";
            } else {
                $error = "Thông tin đăng nhập không chính xác! Còn $left lần thử.";
            }
        }
    }
}
$attempts_used = $_SESSION[$attempt_key] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - StayGo</title>
    <link rel="stylesheet" href="/assets/css/login_admin.css">
</head>
<body>
<div class="login-wrap">
    <div class="brand">
        <div class="brand-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
            </svg>
        </div>
        <h1>StayGo</h1>
        <p>Hệ thống quản trị</p>
    </div>

    <div class="login-card">
        <div class="admin-badge">Admin Panel</div>
        <h2>Đăng nhập Admin</h2>
        <p class="subtitle">Chỉ tài khoản có quyền admin mới được truy cập.</p>

        <?php if ($error): ?>
            <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($attempts_used > 0 && $attempts_used < 3): ?>
            <div style="background:#fffbeb;border:1px solid #fbd38d;color:#b7791f;padding:8px 12px;border-radius:8px;font-size:13px;margin-bottom:12px;text-align:center">
                ⚠ Còn <?= 3 - $attempts_used ?> lần thử – Admin sẽ bị khóa 30 phút nếu sai tiếp
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <div class="input-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                    <input type="email" name="email"
                        placeholder="admin@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>Mật khẩu</label>
                <div class="input-wrap">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                    <input type="password" name="password" placeholder="Nhập mật khẩu" required>
                </div>
            </div>

            <button type="submit" class="btn-login">Đăng nhập vào Admin</button>
        </form>
    </div>

    <div class="login-footer">
        <a href="/index.php">← Về trang chủ</a>
    </div>
</div>
</body>
</html>