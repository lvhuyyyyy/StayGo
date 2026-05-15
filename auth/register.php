<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$message = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name        = trim($_POST['full_name']        ?? '');
    $email            = trim($_POST['email']            ?? '');
    $phone            = trim($_POST['phone']            ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';

    if (empty($full_name)) {
        $message = "Vui lòng nhập họ và tên!";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email không hợp lệ!";
    } elseif (strlen($password) < 6) {
        $message = "Mật khẩu phải có ít nhất 6 ký tự!";
    } elseif ($password !== $confirm_password) {
        $message = "Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra email đã tồn tại chưa
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email này đã được đăng ký. Vui lòng dùng email khác hoặc đăng nhập!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->bind_param("ssss", $full_name, $email, $phone, $hashed);

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                // Liên kết các đơn đặt phòng guest (email trùng, chưa có tài khoản)
                $link = $conn->prepare("UPDATE bookings SET user_id = ? WHERE email = ? AND user_id IS NULL");
                $link->bind_param("is", $new_user_id, $email);
                $link->execute();
                $linked_count = $link->affected_rows;
                $success = true;
            } else {
                $message = "Có lỗi xảy ra, vui lòng thử lại!";
            }
        }
    }
}

$page_title = 'Tạo tài khoản - StayGo';
include '../includes/header.php';
?>

<div class="reset-wrapper">
    <div class="reset-card register-card">

        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
        </div>

        <h2>Tạo tài khoản</h2>
        <p>Điền thông tin bên dưới để đăng ký tài khoản StayGo mới.</p>

        <?php if ($success): ?>
            <div class="message success">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Đăng ký thành công!
                <?php if (!empty($linked_count) && $linked_count > 0): ?>
                    <?= $linked_count ?> đơn đặt phòng trước đó đã được liên kết với tài khoản của bạn.
                <?php endif; ?>
                Đang chuyển về trang đăng nhập...
            </div>
            <meta http-equiv="refresh" content="2;url=login.php">
        <?php else: ?>

            <form method="POST">
                <?= csrf_field() ?>

                <label>Họ và tên</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                    </span>
                    <input type="text" name="full_name" placeholder="Nhập họ và tên" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>

                <label>Email</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.75L2.25 6.75"/>
                        </svg>
                    </span>
                    <input type="email" name="email" placeholder="example@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <label>Số điện thoại <span style="color:#999;font-weight:400">(tùy chọn)</span></label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                        </svg>
                    </span>
                    <input type="tel" name="phone" placeholder="Nhập số điện thoại" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <label>Mật khẩu</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                    </span>
                    <input type="password" name="password" id="password" placeholder="Tối thiểu 6 ký tự" required>
                    <span class="toggle-pw" onclick="togglePassword('password', this)"></span>
                </div>

                <label>Xác nhận mật khẩu</label>
                <div class="input-group">
                    <span class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#999" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                    </span>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Nhập lại mật khẩu" required>
                    <span class="toggle-pw" onclick="togglePassword('confirm_password', this)"></span>
                </div>

                <button type="submit">Đăng ký</button>
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
