<?php
$page_title    = 'Thông tin khách sạn';
$page_subtitle = 'Xem và cập nhật thông tin liên hệ';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

$msg    = null;
$errors = [];

// Lấy thông tin đầy đủ của hotel
$stmt = $conn->prepare("
    SELECT id, name, address, description, commission_rate, partner_status,
           partner_email, contact_name, contact_phone, bank_info, image, rating, review_count,
           COALESCE(cancel_free_days, 1) AS cancel_free_days
    FROM hotels WHERE id = ?
");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$hotel = $stmt->get_result()->fetch_assoc();

// ── POST: Cập nhật thông tin liên hệ ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['form_action'] ?? 'profile';

    if ($action === 'profile') {
        $contact_name  = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $bank_info     = trim($_POST['bank_info'] ?? '');

        if (!$contact_name) $errors[] = 'Tên liên hệ không được để trống.';

        if (empty($errors)) {
            $cn_esc = $conn->real_escape_string($contact_name);
            $cp_esc = $conn->real_escape_string($contact_phone);
            $bi_esc = $conn->real_escape_string($bank_info);
            $conn->query("
                UPDATE hotels
                SET contact_name = '$cn_esc', contact_phone = '$cp_esc', bank_info = '$bi_esc'
                WHERE id = $hotel_id
            ");
            $_SESSION['hotel_contact'] = $contact_name;
            $msg = 'Đã cập nhật thông tin liên hệ.';
            $hotel['contact_name']  = $contact_name;
            $hotel['contact_phone'] = $contact_phone;
            $hotel['bank_info']     = $bank_info;
        }

    } elseif ($action === 'cancel_policy') {
        $days = max(0, min(30, (int)($_POST['cancel_free_days'] ?? 1)));
        $conn->query("UPDATE hotels SET cancel_free_days = $days WHERE id = $hotel_id");
        $hotel['cancel_free_days'] = $days;
        $msg = 'Đã cập nhật chính sách hủy phòng.';

    } elseif ($action === 'password') {
        $cur_pw  = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password'] ?? '';
        $conf_pw = $_POST['confirm_password'] ?? '';

        // Lấy password hiện tại
        $pw_row = $conn->query("SELECT partner_password FROM hotels WHERE id = $hotel_id")->fetch_assoc();

        if (!password_verify($cur_pw, $pw_row['partner_password'] ?? '')) {
            $errors[] = 'Mật khẩu hiện tại không đúng.';
        } elseif (strlen($new_pw) < 8) {
            $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        } elseif ($new_pw !== $conf_pw) {
            $errors[] = 'Xác nhận mật khẩu không khớp.';
        } else {
            $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
            $h_esc  = $conn->real_escape_string($hashed);
            $conn->query("UPDATE hotels SET partner_password = '$h_esc' WHERE id = $hotel_id");
            $msg = 'Đã đổi mật khẩu thành công.';
        }
    }
}
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">

<!-- Thông tin khách sạn (read-only, admin quản lý) -->
<div class="card">
    <div class="card-header"><span class="card-title">🏨 Thông tin khách sạn</span></div>
    <div class="card-body form-row">
        <?php if ($hotel['image']): ?>
        <img src="/assets/images/<?= htmlspecialchars($hotel['image']) ?>" alt=""
             style="width:100%;max-height:160px;object-fit:cover;border-radius:10px;margin-bottom:14px">
        <?php endif; ?>
        <div style="font-size:13px;color:#4a5568;line-height:2">
            <div><strong>Tên:</strong> <?= htmlspecialchars($hotel['name']) ?></div>
            <div><strong>Địa chỉ:</strong> <?= htmlspecialchars($hotel['address'] ?? '—') ?></div>
            <div><strong>Hoa hồng platform:</strong> <?= number_format($hotel['commission_rate'], 1) ?>%</div>
            <div><strong>Trạng thái:</strong>
                <span class="badge badge-<?= strtolower($hotel['partner_status']) === 'active' ? 'confirmed' : 'cancelled' ?>">
                    <?= $hotel['partner_status'] ?>
                </span>
            </div>
            <div><strong>Đánh giá:</strong> <?= number_format($hotel['rating'], 1) ?> ⭐ (<?= (int)$hotel['review_count'] ?> đánh giá)</div>
        </div>
        <div style="font-size:12px;color:#a0aec0;margin-top:10px">
            Để thay đổi tên, địa chỉ, ảnh khách sạn — vui lòng liên hệ Admin.
        </div>
    </div>
</div>

<!-- Thông tin liên hệ (hotel tự cập nhật) -->
<div class="card">
    <div class="card-header"><span class="card-title">📋 Thông tin liên hệ</span></div>
    <div class="card-body">
        <form method="POST" class="form-row">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="profile">

            <div class="form-group">
                <label>Tên người liên hệ <span style="color:#e53e3e">*</span></label>
                <input type="text" name="contact_name" value="<?= htmlspecialchars($hotel['contact_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Số điện thoại</label>
                <input type="tel" name="contact_phone" value="<?= htmlspecialchars($hotel['contact_phone'] ?? '') ?>" placeholder="0901234567">
            </div>
            <div class="form-group">
                <label>Email đăng nhập (không đổi được)</label>
                <input type="email" value="<?= htmlspecialchars($hotel['partner_email'] ?? '') ?>" disabled
                       style="background:#f7fafc;color:#a0aec0">
            </div>
            <div class="form-group">
                <label>Thông tin ngân hàng</label>
                <textarea name="bank_info" rows="3" placeholder="VD: 1234567890 - Vietcombank - Nguyen Van A"><?= htmlspecialchars($hotel['bank_info'] ?? '') ?></textarea>
                <div style="font-size:12px;color:#a0aec0;margin-top:4px">Admin dùng để chuyển khoản giải ngân</div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
        </form>
    </div>
</div>

<!-- Đổi mật khẩu -->
<div class="card" style="grid-column:1/-1;max-width:500px">
    <div class="card-header"><span class="card-title">🔑 Đổi mật khẩu</span></div>
    <div class="card-body">
        <form method="POST" class="form-row">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="password">
            <div class="form-group">
                <label>Mật khẩu hiện tại</label>
                <input type="password" name="current_password" required placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Mật khẩu mới (ít nhất 8 ký tự)</label>
                <input type="password" name="new_password" required placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Xác nhận mật khẩu mới</label>
                <input type="password" name="confirm_password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">🔑 Đổi mật khẩu</button>
        </form>
    </div>
</div>

<!-- Chính sách hủy phòng -->
<div class="card" style="grid-column:1/-1;max-width:500px">
    <div class="card-header"><span class="card-title">🚫 Chính sách hủy phòng</span></div>
    <div class="card-body">
        <form method="POST" class="form-row">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="cancel_policy">
            <div class="form-group">
                <label>Cho phép hủy miễn phí trước bao nhiêu ngày check-in?</label>
                <select name="cancel_free_days" style="padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;width:100%">
                    <?php
                    $options = [0=>'Không cho hủy miễn phí',1=>'1 ngày',2=>'2 ngày',3=>'3 ngày',5=>'5 ngày',7=>'7 ngày',14=>'14 ngày',30=>'30 ngày'];
                    foreach ($options as $v => $lbl):
                    ?>
                    <option value="<?= $v ?>" <?= (int)$hotel['cancel_free_days'] === $v ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:12px;color:#718096;margin-top:6px;line-height:1.5">
                    Khách hủy trong thời hạn này sẽ được hoàn tiền 100%.<br>
                    Hủy sau thời hạn (hoặc nếu chọn "Không cho hủy miễn phí") sẽ bị phí hủy 20%.
                </div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Lưu chính sách</button>
        </form>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
