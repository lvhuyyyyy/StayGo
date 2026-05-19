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
           partner_email, contact_name, contact_phone, bank_info, image, rating, review_count
    FROM hotels WHERE id = ?
");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$hotel = $stmt->get_result()->fetch_assoc();

// Lấy cancel_free_days riêng — column có thể chưa tồn tại (migration chưa chạy)
$cfd = $conn->query("SELECT cancel_free_days FROM hotels WHERE id = $hotel_id");
$hotel['cancel_free_days'] = ($cfd && $row = $cfd->fetch_assoc()) ? (int)$row['cancel_free_days'] : 1;

// Lấy dispute_window_days (số ngày giữ tiền sau checkout trước khi giải ngân)
$dwd = $conn->query("SELECT dispute_window_days FROM hotels WHERE id = $hotel_id");
$hotel['dispute_window_days'] = ($dwd && $row2 = $dwd->fetch_assoc()) ? (int)$row2['dispute_window_days'] : 7;

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
            // Fix #7: prepared statement thay real_escape_string
            $upd = $conn->prepare("
                UPDATE hotels
                SET contact_name = ?, contact_phone = ?, bank_info = ?
                WHERE id = ?
            ");
            $upd->bind_param('sssi', $contact_name, $contact_phone, $bank_info, $hotel_id);
            $upd->execute();
            $_SESSION['hotel_contact'] = $contact_name;
            $msg = 'Đã cập nhật thông tin liên hệ.';
            $hotel['contact_name']  = $contact_name;
            $hotel['contact_phone'] = $contact_phone;
            $hotel['bank_info']     = $bank_info;
        }

    } elseif ($action === 'cancel_policy') {
        $days = max(0, min(30, (int)($_POST['cancel_free_days'] ?? 1)));
        $dw   = max(0, min(30, (int)($_POST['dispute_window_days'] ?? 7)));
        $conn->query("UPDATE hotels SET cancel_free_days = $days, dispute_window_days = $dw WHERE id = $hotel_id");
        $hotel['cancel_free_days']     = $days;
        $hotel['dispute_window_days']  = $dw;
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
            // Fix #7: prepared statement cho password update
            $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
            $pw_upd = $conn->prepare("UPDATE hotels SET partner_password = ? WHERE id = ?");
            $pw_upd->bind_param('si', $hashed, $hotel_id);
            $pw_upd->execute();
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
            Để thay đổi tên, địa chỉ, ảnh khách sạn —
            <a href="/hotel/request_change.php" style="color:#3182ce;font-weight:600">Gửi yêu cầu đến Admin →</a>
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
            <div class="form-group" style="margin-top:16px">
                <label>Dispute window — số ngày giữ tiền sau check-out trước khi giải ngân</label>
                <select name="dispute_window_days" style="padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;width:100%">
                    <?php
                    $dw_opts = [0=>'Giải ngân ngay sau check-out',3=>'3 ngày',5=>'5 ngày',7=>'7 ngày (khuyến nghị)',14=>'14 ngày',30=>'30 ngày'];
                    foreach ($dw_opts as $v => $lbl):
                    ?>
                    <option value="<?= $v ?>" <?= (int)$hotel['dispute_window_days'] === $v ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:12px;color:#718096;margin-top:6px;line-height:1.5">
                    Sau khi khách check-out, tiền được giữ thêm N ngày để xử lý khiếu nại (no-show, phòng không đúng mô tả...).<br>
                    Hết thời gian này, booking chuyển sang <strong>READY</strong> và admin có thể giải ngân.
                </div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Lưu chính sách</button>
        </form>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
