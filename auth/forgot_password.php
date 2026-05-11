<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_helper.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_check();
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expire = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $upd = $conn->prepare("UPDATE users SET otp_code = ?, otp_expire = ? WHERE email = ?");
        $upd->bind_param("sss", $otp, $expire, $email);
        $upd->execute();
        $upd->close();

        send_otp_email($email, $row['full_name'], $otp);

        header("Location: verify_otp.php?email=" . urlencode($email));
        exit();
    } else {
        $message = "Email không tồn tại hoặc chưa được đăng ký trong hệ thống!";
    }
}

// Include header SAU KHI xử lý POST xong
$page_title = 'Quên mật khẩu - StayGo';
include '../includes/header.php';
?>

<div class="forgot-wrapper">
    <div class="forgot-card">

        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
        </div>

        <h2>Quên mật khẩu</h2>
        <p>Nhập địa chỉ email bạn đã dùng để đăng ký tài khoản. Chúng tôi sẽ xác minh và cho phép bạn đặt lại mật khẩu.</p>

        <form method="POST">
    <?= csrf_field() ?>
            <label for="email">Email đăng ký</label>
            <div class="input-group">
                <span class="input-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.75L2.25 6.75"/>
                    </svg>
                </span>
                <input type="email" id="email" name="email" placeholder="example@email.com" required>
            </div>

            <button type="submit">Tiếp tục</button>
        </form>

        <?php if ($message != ""): ?>
            <div class="message">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="back-login">
            <a href="login.php" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Quay lại đăng nhập
            </a>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>