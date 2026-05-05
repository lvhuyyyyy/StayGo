<?php
// ✔ session_start() và xử lý logic TRƯỚC KHI include header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/../includes/security.php';

// Kiểm tra email có được truyền vào không
if (!isset($_GET['email']) || empty($_GET['email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_GET['email'];
$message = "";
$success = false;

// Xác minh email tồn tại trong DB + lấy password hash
$check = $conn->prepare("SELECT id, password FROM users WHERE email=?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: forgot_password.php");
    exit();
}

$current_hashed_password = $user['password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old_password     = $_POST['old_password']     ?? '';
    $new_password     = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old_password)) {
        $message = "Vui lòng nhập mật khẩu cũ!";
    } elseif (!password_verify($old_password, $current_hashed_password)) {
        $message = "Mật khẩu cũ không đúng!";
    } elseif (strlen($new_password) < 6) {
        $message = "Mật khẩu mới phải có ít nhất 6 ký tự!";
    } elseif ($new_password !== $confirm_password) {
        $message = "Mật khẩu xác nhận không khớp!";
    } elseif ($old_password === $new_password) {
        $message = "Mật khẩu mới không được trùng với mật khẩu cũ!";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=?, otp_code=NULL, otp_expire=NULL WHERE email=?");
        $stmt->bind_param("ss", $hashed, $email);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $message = "Có lỗi xảy ra, vui lòng thử lại!";
        }
    }
}

// ✔ Include header SAU KHI xử lý POST xong
$page_title = 'Đặt lại mật khẩu - StayGo';
include '../includes/header.php';
?>

<div class="reset-wrapper">
    <div class="reset-card">

        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
            </svg>
        </div>

        <h2>Đặt lại mật khẩu</h2>
        <p>Nhập mật khẩu mới cho tài khoản <strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if ($success): ?>
            <div class="message success">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Đổi mật khẩu thành công! Đang chuyển về trang đăng nhập...
            </div>
            <meta http-equiv="refresh" content="2;url=login.php">
        <?php else: ?>

            <form method="POST" id="resetForm">
                <?= csrf_field() ?>

                <!-- Mật khẩu cũ -->
                <label>Mật khẩu cũ</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                    </span>
                    <input type="password" name="old_password" id="old_password" placeholder="Nhập mật khẩu cũ" required>
                    <span class="toggle-pw" onclick="togglePassword('old_password', this)"></span>
                </div>

                <!-- Mật khẩu mới -->
                <label>Mật khẩu mới</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                    </span>
                    <input type="password" name="password" id="new_password" placeholder="Nhập mật khẩu mới" required>
                    <span class="toggle-pw" onclick="togglePassword('new_password', this)"></span>
                </div>

                <!-- Xác nhận mật khẩu -->
                <label>Xác nhận mật khẩu</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Nhập lại mật khẩu mới" required>
                    <span class="toggle-pw" onclick="togglePassword('confirm_password', this)"></span>
                </div>

                <button type="submit">Đổi mật khẩu</button>
            </form>

            <?php if ($message != ""): ?>
                <div class="message error">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="back-login">
                <a href="login.php" class="back-btn">
                    ← Quay lại đăng nhập
                </a>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>