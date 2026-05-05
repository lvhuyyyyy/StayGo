<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

$stmt = $conn->prepare("SELECT full_name, email, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pw  = $_POST['old_password']     ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $conf_pw = $_POST['confirm_password'] ?? '';

    if (!$old_pw || !$new_pw || !$conf_pw) {
        $error = 'Vui lòng nhập đầy đủ tất cả các trường!';
    } elseif (strlen($new_pw) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
    } elseif ($new_pw !== $conf_pw) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } elseif ($old_pw === $new_pw) {
        $error = 'Mật khẩu mới phải khác mật khẩu cũ!';
    } else {
        $chk = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $chk->bind_param("i", $user_id);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();

        if (!password_verify($old_pw, $row['password'])) {
            $error = 'Mật khẩu cũ không đúng!';
        } else {
            $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hashed, $user_id);
            if ($upd->execute()) {
                $success = 'Đổi mật khẩu thành công!';
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại!';
            }
        }
    }
}

$first_char = mb_strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1));
$joined     = $user['created_at'] ? date('d/m/Y', strtotime($user['created_at'])) : '—';
?>

<div class="ep-page">
    <div class="ep-container">

        <!-- Sidebar -->
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
                    Trang cá nhân
                </a>
                <a href="edit_profile.php" class="ep-slink">
                    Chỉnh sửa thông tin
                </a>
                <a href="my_bookings.php" class="ep-slink">
                    Lịch sử đặt phòng
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="ep-main">
            <div class="ep-card">
                <div class="ep-card-head">
                    <div>
                        <h2 class="ep-title">Đổi mật khẩu</h2>
                        <p class="ep-sub">Để bảo mật tài khoản, hãy dùng mật khẩu mạnh và không chia sẻ với ai</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="ep-alert ep-alert-success">
                        <?= $success ?> 
                        <a href="profile.php" style="color:inherit;margin-left:8px;font-weight:700">
                            → Về trang cá nhân
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="ep-alert ep-alert-error">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="ep-form">

                    <!-- Mật khẩu cũ -->
                    <div class="ep-field" style="margin-bottom:18px">
                        <label>Mật khẩu hiện tại <span class="required">*</span></label>
                        <div class="ep-input-wrap">
                            <input type="password" name="old_password" id="old_pw"
                                placeholder="Nhập mật khẩu hiện tại" required>
                        </div>
                    </div>

                    <div class="pw-divider">Mật khẩu mới</div>

                    <!-- Mật khẩu mới -->
                    <div class="ep-field" style="margin-bottom:18px">
                        <label>Mật khẩu mới <span class="required">*</span></label>
                        <div class="ep-input-wrap">
                            <input type="password" name="new_password" id="new_pw"
                                placeholder="Tối thiểu 6 ký tự" required
                                oninput="checkStrength(this.value)">
                        </div>
                    </div>

                    <!-- Xác nhận -->
                    <div class="ep-field" style="margin-bottom:24px">
                        <label>Xác nhận mật khẩu mới <span class="required">*</span></label>
                        <div class="ep-input-wrap">
                            <input type="password" name="confirm_password" id="conf_pw"
                                placeholder="Nhập lại mật khẩu mới" required
                                oninput="checkMatch()">
                        </div>
                    </div>

                    <div class="ep-form-footer">
                        <a href="profile.php" class="ep-btn-cancel">Hủy</a>
                        <button type="submit" class="ep-btn-save">
                            Đổi mật khẩu
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>