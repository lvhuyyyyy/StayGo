<?php
require_once __DIR__ . '/../includes/security.php';
include("../config/database.php");

$action = $_GET['action'] ?? 'add';
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user   = null;
$errors = [];

// ============================================================
// ✅ XỬ LÝ POST TRƯỚC KHI include header (tránh "headers already sent")
// ============================================================

// ➕ THÊM USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add'])) {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $role      = $_POST['role'];

    if (empty($full_name)) $errors[] = 'Họ tên không được để trống.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (strlen($password) < 6) $errors[] = 'Mật khẩu phải ít nhất 6 ký tự.';

    $check = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($email) . "'");
    if ($check->num_rows > 0) $errors[] = 'Email đã tồn tại trong hệ thống.';

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $full_name, $email, $hashed, $role);
        if ($stmt->execute()) {
            log_activity($conn, 'add_user', 'user', $conn->insert_id, "Thêm tài khoản: $full_name ($email)");
            header("Location: users.php?success=added");
            exit;
        } else {
            $errors[] = 'Lỗi khi thêm user: ' . $conn->error;
        }
    }
    $action = 'add';
}

// ✏️ SỬA USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $id        = intval($_POST['id']);
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $role      = $_POST['role'];
    $password  = $_POST['password'];

    if (empty($full_name)) $errors[] = 'Họ tên không được để trống.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';

    $check = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($email) . "' AND id != $id");
    if ($check->num_rows > 0) $errors[] = 'Email đã được dùng bởi tài khoản khác.';

    if (empty($errors)) {
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = 'Mật khẩu mới phải ít nhất 6 ký tự.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password=?, role=? WHERE id=?");
                $stmt->bind_param("ssssi", $full_name, $email, $hashed, $role, $id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $full_name, $email, $role, $id);
        }

        if (empty($errors)) {
            if ($stmt->execute()) {
                log_activity($conn, 'edit_user', 'user', $id, "Sửa tài khoản #$id");
                header("Location: users.php?success=updated");
                exit;
            } else {
                $errors[] = 'Lỗi khi cập nhật: ' . $conn->error;
            }
        }
    }
    $action = 'edit';
}

// 🔍 LẤY THÔNG TIN USER KHI SỬA
if ($action === 'edit' && $id > 0) {
    $res  = $conn->query("SELECT * FROM users WHERE id = $id");
    $user = $res->fetch_assoc();
    if (!$user) {
        header("Location: users.php?error=not_found");
        exit;
    }
}

// ✅ Include header SAU KHI đã xử lý hết redirect
$page_title    = $action === 'edit' ? 'Chỉnh sửa tài khoản' : 'Thêm tài khoản';
$page_subtitle = $action === 'edit' ? 'Cập nhật thông tin user' : 'Tạo tài khoản mới';
include("../includes/admin_header.php");
?>

<div class="form-card" style="max-width:640px;margin:40px auto;padding:40px 48px;">
    <h2>
        <?= $action === 'edit' ? '✏️ Chỉnh sửa tài khoản' : '➕ Thêm tài khoản mới' ?>
    </h2>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li>❌ <?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <input type="hidden" name="action_edit" value="1">
        <?php else: ?>
            <input type="hidden" name="action_add" value="1">
        <?php endif; ?>

        <div class="form-group">
            <label>Họ và tên <span class="required">*</span></label>
            <input type="text" name="full_name"
                value="<?= htmlspecialchars($user['full_name'] ?? $_POST['full_name'] ?? '') ?>"
                placeholder="Nhập họ và tên">
        </div>

        <div class="form-group">
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email"
                value="<?= htmlspecialchars($user['email'] ?? $_POST['email'] ?? '') ?>"
                placeholder="Nhập địa chỉ email">
        </div>

        <div class="form-group">
            <label>
                Mật khẩu
                <?php if ($action === 'add'): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <input type="password" name="password"
                placeholder="<?= $action === 'edit' ? 'Để trống nếu không đổi mật khẩu' : 'Tối thiểu 6 ký tự' ?>">
            <?php if ($action === 'edit'): ?>
                <small>💡 Chỉ nhập nếu muốn thay đổi mật khẩu</small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Vai trò <span class="required">*</span></label>
            <select name="role">
                <option value="user"  <?= (($user['role'] ?? 'user') === 'user')  ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= (($user['role'] ?? '')      === 'admin') ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>

        <div style="margin-top: 24px; display:flex; align-items:center;">
            <button type="submit" class="btn-submit">
                <?= $action === 'edit' ? '💾 Lưu thay đổi' : '➕ Thêm tài khoản' ?>
            </button>
            <a href="users.php" class="btn-back" style="margin-left:12px;">Huỷ</a>
        </div>
    </form>
</div>

<?php include("../includes/admin_footer.php"); ?>