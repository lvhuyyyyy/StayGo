<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$error = "";

// ------------------------------------------
// BRUTE FORCE PROTECTION
// ------------------------------------------
$max_attempts = 5;
$lockout_time = 900; // 15 phút

$ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attempt_key = 'login_attempts_' . md5($ip);
$lockout_key = 'login_lockout_'  . md5($ip);

if (isset($_SESSION[$lockout_key])) {
    $remaining = $_SESSION[$lockout_key] - time();
    if ($remaining > 0) {
        $mins  = ceil($remaining / 60);
        $error = "Quá nhiều lần đăng nhập sai. Vui lòng thử lại sau $mins phút.";
    } else {
        unset($_SESSION[$lockout_key], $_SESSION[$attempt_key]);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {

    $secretKey = "6LeH_3wsAAAAADuogi_XMTuCv5MXK0F26zI-IDz_";

    if (empty($_POST['g-recaptcha-response'])) {
        $error = "Vui lòng xác minh bạn không phải robot!";
    } else {
        $verify       = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$_POST['g-recaptcha-response']}");
        $responseData = json_decode($verify);

        if (!$responseData->success) {
            $error = "Xác minh reCAPTCHA thất bại!";
        } else {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = "Vui lòng nhập đầy đủ thông tin!";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email không hợp lệ!";
            } else {
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password'])) {
                    // ✔ Thành công
                    unset($_SESSION[$attempt_key], $_SESSION[$lockout_key]);
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'];

                    $email_safe = $conn->real_escape_string($user['email']);
                    $conn->query("UPDATE bookings SET user_id={$user['id']} WHERE user_id IS NULL AND email='$email_safe'");

                    if ($user['role'] === 'admin') {
                        header("Location: /admin_lvhuy_kontum/dashboard.php");
                    } else {
                        $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
                        unset($_SESSION['redirect_after_login']);
                        header("Location: $redirect");
                    }
                    exit();

                } else {
                    // ❌ Sai → tăng attempts
                    $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;
                    $left = $max_attempts - $_SESSION[$attempt_key];

                    if ($_SESSION[$attempt_key] >= $max_attempts) {
                        $_SESSION[$lockout_key] = time() + $lockout_time;
                        unset($_SESSION[$attempt_key]);
                        $error = "Tài khoản bị khóa 15 phút do đăng nhập sai quá nhiều lần!";
                    } else {
                        $error = "Email hoặc mật khẩu không chính xác! Còn $left lần thử.";
                    }
                }
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
    <title>Đăng nhập - StayGo</title>
    <link rel="stylesheet" href="/assets/css/login.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="login-wrapper">
    <div class="login-box">
        <h2>Đăng nhập hoặc tạo tài khoản</h2>
        <p class="login-sub">Bạn có thể đăng nhập tài khoản StayGo của mình để truy cập các dịch vụ của chúng tôi.</p>

        <?php if ($error): ?>
            <p style="color:red;background:#fff5f5;padding:10px;border-radius:8px;border:1px solid #fed7d7">
                ⚠️ <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($attempts_used > 0 && $attempts_used < 5): ?>
            <p style="color:#b7791f;font-size:13px;text-align:center;margin-bottom:10px">
                ⚠️ Còn <?= 5 - $attempts_used ?> lần thử trước khi bị khóa 15 phút
            </p>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <label>Địa chỉ email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Nhập địa chỉ email" required>

            <label>Mật khẩu</label>
            <input type="password" name="password" placeholder="Nhập mật khẩu" required>

            <div class="g-recaptcha" data-sitekey="6LeH_3wsAAAAAFuWQOsDsByqn69icwQa0sLJlaNt"></div>

            <button type="submit" class="btn-primary">Đăng nhập</button>

            <div style="text-align:center;margin-top:10px">
                <a href="register.php" class="btn-register" id="btnRegister">Tạo tài khoản</a>
            </div>
            <div style="margin-top:10px">
                <a href="forgot_password.php" class="forgot-link" id="btnForgotPassword">Quên mật khẩu?</a>
            </div>
        </form>

        <div class="divider"><span>hoặc sử dụng một trong các lựa chọn này</span></div>
        <div class="social-login">
            <a href="google_login.php" class="social-btn google"><img src="/assets/images/google.png" alt="Google"></a>
            <?php if (!empty(FB_APP_ID)): ?>
            <a href="facebook_login.php" class="social-btn facebook"><img src="/assets/images/facebook.png" alt="Facebook"></a>
            <?php endif; ?>
        </div>

        <div class="login-footer">
            Qua việc đăng nhập hoặc tạo tài khoản, bạn đồng ý với
            <a href="#">Điều khoản</a> và <a href="#">Chính sách bảo mật</a>.
        </div>
    </div>
</div>
</body>
</html>
<?php include '../includes/footer.php'; ?>