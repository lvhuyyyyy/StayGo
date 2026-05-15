<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Đã đăng nhập → redirect
if (!empty($_SESSION['hotel_id'])) {
    header("Location: /hotel/dashboard.php");
    exit;
}

$error = null;

// Hiển thị thông báo bị suspend
$suspend_msg = null;
if (isset($_GET['err']) && $_GET['err'] === 'suspended') {
    $suspend_msg = 'Tài khoản khách sạn của bạn đang bị tạm đình chỉ. Vui lòng liên hệ Admin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, name, contact_name, partner_password, partner_status
            FROM hotels
            WHERE partner_email = ? AND partner_password IS NOT NULL
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $hotel = $stmt->get_result()->fetch_assoc();

        if (!$hotel || !password_verify($password, $hotel['partner_password'])) {
            $error = 'Email hoặc mật khẩu không đúng.';
        } elseif ($hotel['partner_status'] === 'SUSPENDED') {
            $error = 'Tài khoản khách sạn của bạn đang bị tạm đình chỉ. Vui lòng liên hệ Admin.';
        } elseif ($hotel['partner_status'] === 'REJECTED') {
            $error = 'Tài khoản khách sạn của bạn đã bị từ chối. Vui lòng liên hệ Admin.';
        } elseif ($hotel['partner_status'] !== 'ACTIVE') {
            $error = 'Tài khoản đang chờ duyệt. Admin sẽ kích hoạt sớm.';
        } else {
            $_SESSION['hotel_id']     = (int)$hotel['id'];
            $_SESSION['hotel_name']   = $hotel['name'];
            $_SESSION['hotel_contact']= $hotel['contact_name'];
            $_SESSION['hotel_status'] = $hotel['partner_status'];
            session_regenerate_id(true);
            header("Location: /hotel/dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Partner Login - StayGo</title>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #2b6cb0 100%);
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .login-wrap { width: 100%; max-width: 420px; }
    .login-brand {
        text-align: center; margin-bottom: 24px; color: #fff;
    }
    .login-brand-icon {
        width: 64px; height: 64px; background: rgba(255,255,255,.15); border-radius: 20px;
        display: flex; align-items: center; justify-content: center; font-size: 32px;
        margin: 0 auto 12px;
    }
    .login-brand h1 { font-size: 26px; font-weight: 800; }
    .login-brand p { font-size: 13px; opacity: .8; margin-top: 4px; }
    .login-card {
        background: #fff; border-radius: 20px; padding: 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,.25);
    }
    .login-title { font-size: 18px; font-weight: 800; color: #1a365d; margin-bottom: 6px; }
    .login-sub { font-size: 13px; color: #718096; margin-bottom: 24px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 700; color: #4a5568; margin-bottom: 6px; }
    .form-group input {
        width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
        font-size: 14px; font-family: inherit; transition: border-color .2s;
    }
    .form-group input:focus { outline: none; border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,.1); }
    .btn-login {
        width: 100%; padding: 13px; background: linear-gradient(135deg, #1a365d, #3182ce);
        color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700;
        cursor: pointer; font-family: inherit; transition: opacity .2s; margin-top: 4px;
    }
    .btn-login:hover { opacity: .92; }
    .alert-err {
        padding: 12px 14px; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 10px;
        color: #c53030; font-size: 13.5px; margin-bottom: 18px;
    }
    .alert-warn {
        padding: 12px 14px; background: #fffbeb; border: 1px solid #fbd38d; border-radius: 10px;
        color: #92400e; font-size: 13.5px; margin-bottom: 18px;
    }
    .login-footer {
        text-align: center; margin-top: 20px; color: #fff; font-size: 12.5px; opacity: .7;
    }
    .login-footer a { color: #90cdf4; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-brand">
        <div class="login-brand-icon">🏨</div>
        <h1>StayGo</h1>
        <p>Hotel Partner Portal</p>
    </div>

    <div class="login-card">
        <div class="login-title">Đăng nhập đối tác</div>
        <div class="login-sub">Quản lý đặt phòng, xem doanh thu và cập nhật thông tin khách sạn</div>

        <?php if ($suspend_msg): ?>
        <div class="alert-warn">⚠️ <?= htmlspecialchars($suspend_msg) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Email đối tác</label>
                <input type="email" name="email" placeholder="hotel@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">Đăng nhập</button>
        </form>
    </div>

    <div class="login-footer">
        Chưa có tài khoản? Liên hệ Admin để được cấp quyền truy cập.<br>
        <a href="/">← Về trang chủ StayGo</a>
    </div>
</div>
</body>
</html>
