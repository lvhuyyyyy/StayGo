<?php
$page_title    = 'Yêu cầu thay đổi thông tin';
$page_subtitle = 'Gửi yêu cầu đến Admin để cập nhật tên, địa chỉ hoặc ảnh khách sạn';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

$msg    = null;
$errors = [];

// Lấy thông tin hotel hiện tại
$hotel = $conn->query("SELECT name, address, partner_email, contact_name, contact_phone FROM hotels WHERE id = $hotel_id")->fetch_assoc();

// Kiểm tra đã có request pending chưa
$existing = $conn->query("
    SELECT id, created_at FROM support_requests
    WHERE hotel_id = $hotel_id AND type = 'hotel' AND status IN ('pending','processing')
    ORDER BY created_at DESC LIMIT 1
")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $change_type = $_POST['change_type'] ?? '';
    $new_value   = trim($_POST['new_value'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');

    $allowed_types = ['name' => 'Tên khách sạn', 'address' => 'Địa chỉ', 'image' => 'Ảnh đại diện', 'other' => 'Khác'];
    if (!array_key_exists($change_type, $allowed_types)) $errors[] = 'Vui lòng chọn loại thay đổi.';
    if (!$new_value && $change_type !== 'image') $errors[] = 'Vui lòng nhập nội dung mới.';

    if (empty($errors)) {
        $type_label = $allowed_types[$change_type];
        $subject    = "[Yêu cầu đổi $type_label] " . $hotel['name'];
        $note       = "Loại thay đổi: $type_label\n";
        $note      .= "Giá trị hiện tại: " . ($hotel[$change_type] ?? 'N/A') . "\n";
        $note      .= "Giá trị mới: $new_value\n";
        if ($reason) $note .= "Lý do: $reason";

        $subj_esc = $conn->real_escape_string($subject);
        $note_esc = $conn->real_escape_string($note);
        $name_esc = $conn->real_escape_string($hotel['contact_name'] ?? $hotel['name']);
        $phone_esc = $conn->real_escape_string($hotel['contact_phone'] ?? '');
        $email_esc = $conn->real_escape_string($hotel['partner_email'] ?? '');

        $conn->query("
            INSERT INTO support_requests (hotel_id, type, full_name, phone, email, subject, note, status)
            VALUES ($hotel_id, 'hotel', '$name_esc', '$phone_esc', '$email_esc', '$subj_esc', '$note_esc', 'pending')
        ");

        $msg = 'Yêu cầu đã được gửi. Admin sẽ xem xét và cập nhật trong vòng 1–2 ngày làm việc.';
        $existing = ['id' => $conn->insert_id, 'created_at' => date('Y-m-d H:i:s')];
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

<div style="max-width:620px">

<!-- Thông tin hiện tại -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">🏨 Thông tin hiện tại</span></div>
    <div class="card-body" style="font-size:13.5px;color:#4a5568;line-height:2">
        <div><strong>Tên:</strong> <?= htmlspecialchars($hotel['name']) ?></div>
        <div><strong>Địa chỉ:</strong> <?= htmlspecialchars($hotel['address'] ?? '—') ?></div>
        <div style="font-size:12px;color:#a0aec0;margin-top:8px">
            Tên, địa chỉ và ảnh đại diện do Admin quản lý để đảm bảo chất lượng nội dung hiển thị trên nền tảng.
        </div>
    </div>
</div>

<?php if ($existing): ?>
<!-- Đã có request đang chờ -->
<div class="alert alert-info" style="margin-bottom:20px">
    📋 Bạn đang có <strong>1 yêu cầu đang chờ xử lý</strong> (gửi lúc <?= date('d/m/Y H:i', strtotime($existing['created_at'])) ?>).
    Admin sẽ phản hồi sớm. Vui lòng chờ trước khi gửi yêu cầu mới.
</div>
<?php else: ?>

<!-- Form gửi request -->
<div class="card">
    <div class="card-header"><span class="card-title">📝 Gửi yêu cầu thay đổi</span></div>
    <div class="card-body">
        <form method="POST" class="form-row">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Loại thay đổi <span style="color:#e53e3e">*</span></label>
                <select name="change_type" required style="padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;width:100%">
                    <option value="">-- Chọn loại --</option>
                    <option value="name">Tên khách sạn</option>
                    <option value="address">Địa chỉ</option>
                    <option value="image">Ảnh đại diện</option>
                    <option value="other">Khác</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nội dung mới muốn cập nhật <span style="color:#e53e3e">*</span></label>
                <textarea name="new_value" rows="3" placeholder="Nhập tên mới / địa chỉ mới / mô tả yêu cầu..." style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box"></textarea>
            </div>

            <div class="form-group">
                <label>Lý do thay đổi (tuỳ chọn)</label>
                <textarea name="reason" rows="2" placeholder="VD: Khách sạn đổi tên thương hiệu, địa chỉ mới..." style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">📨 Gửi yêu cầu</button>
        </form>
    </div>
</div>

<?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
