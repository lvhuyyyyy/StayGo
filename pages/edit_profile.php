<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// Lấy thông tin hiện tại
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');

    if (!$full_name || !$email) {
        $error = 'Vui lòng nhập đầy đủ họ tên và email!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ!';
    } else {
        // Kiểm tra email trùng (nếu đổi email)
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->bind_param("si", $email, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email này đã được sử dụng bởi tài khoản khác!';
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
            $upd->bind_param("sssi", $full_name, $email, $phone, $user_id);
            if ($upd->execute()) {
                $success = 'Cập nhật thông tin thành công!';
                unset($_SESSION['user_cache']); // reset cache sau khi cập nhật
                // Reload user
                $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $user = $stmt2->get_result()->fetch_assoc();
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại!';
            }
        }
    }
}

$first_char = mb_strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1));
$joined     = $user['created_at'] ? date('d/m/Y', strtotime($user['created_at'])) : '–';
?>

<div class="ep-page">
    <div class="ep-container">

        <!-- Sidebar info -->
        <div class="ep-sidebar">
            <div class="ep-avatar-wrap">
                <div class="ep-avatar"><?= $first_char ?></div>
                <div class="ep-user-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
                <div class="ep-user-role"><?= htmlspecialchars($user['role'] ?? 'user') ?></div>
            </div>
            <div class="ep-sidebar-info">
                <div class="ep-sinfo-row">
                    <span>📧</span>
                    <span><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <div class="ep-sinfo-row">
                    <span>📅</span>
                    <span>Tham gia <?= $joined ?></span>
                </div>
            </div>
            <div class="ep-sidebar-links">
                <a href="profile.php" class="ep-slink">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                    Trang cá nhân
                </a>
                <a href="change_password.php" class="ep-slink">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    Đổi mật khẩu
                </a>
                <a href="my_bookings.php" class="ep-slink">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                    Lịch sử đặt phòng
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="ep-main">
            <div class="ep-card">
                <div class="ep-card-head">
                    <div>
                        <h2 class="ep-title">Chỉnh sửa thông tin</h2>
                        <p class="ep-sub">Cập nhật thông tin cá nhân của bạn</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="ep-alert ep-alert-success">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="ep-alert ep-alert-error">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="ep-form">
                <?= csrf_field() ?>

                    <div class="ep-field-grid">
                        <!-- Họ tên -->
                        <div class="ep-field ep-field-full">
                            <label>Họ và tên <span class="required">*</span></label>
                            <div class="ep-input-wrap">
                                <span class="ep-input-icon">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                </span>
                                <input type="text" name="full_name"
                                    value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                                    placeholder="Nhập họ và tên" required>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="ep-field">
                            <label>Email <span class="required">*</span></label>
                            <div class="ep-input-wrap">
                                <span class="ep-input-icon">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                </span>
                                <input type="email" name="email"
                                    value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                    placeholder="Nhập email" required>
                            </div>
                        </div>

                        <!-- Số điện thoại -->
                        <div class="ep-field">
                            <label>Số điện thoại</label>
                            <div class="ep-input-wrap">
                                <span class="ep-input-icon">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                </span>
                                <input type="text" name="phone"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                    placeholder="Nhập số điện thoại">
                            </div>
                        </div>
                    </div>

                    <div class="ep-form-footer">
                        <a href="profile.php" class="ep-btn-cancel">Hủy</a>
                        <button type="submit" class="ep-btn-save">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>