<?php
include("../config/database.php");

// ---- Xử lý lưu ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $val) {
        if ($key === 'csrf_token') continue;
        $k = $conn->real_escape_string(trim($key));
        $v = $conn->real_escape_string(trim($val));
        $conn->query("UPDATE site_settings SET value='$v' WHERE `key`='$k'");
    }
    // Ghi activity log nếu có helper
    if (function_exists('log_activity')) log_activity($conn, 'update_settings', 'site_settings', 0, 'Cập nhật cài đặt hệ thống');
    header("Location: site_settings.php?msg=saved");
    exit;
}

// ---- Nhãn cố định trong PHP (tránh lỗi encoding CLI) ----
$key_labels = [
    'site_name'        => 'Tên website',
    'site_email'       => 'Email liên hệ',
    'site_phone'       => 'Số điện thoại',
    'site_address'     => 'Địa chỉ',
    'facebook_url'     => 'Link Facebook',
    'zalo_url'         => 'Link Zalo OA',
    'booking_fee_pct'  => 'Phí huỷ đặt phòng (%)',
    'max_advance_days' => 'Đặt trước tối đa (ngày)',
    'maintenance_mode' => 'Chế độ bảo trì',
];

// ---- Thứ tự hiển thị các group ----
$group_order = ['general', 'booking', 'social', 'system'];

$group_labels = [
    'general' => ['🌐 Thông tin chung', '#1e73be', '#ebf8ff'],
    'social'  => ['📲 Mạng xã hội',    '#6d28d9', '#f5f3ff'],
    'booking' => ['📋 Đặt phòng',      '#b7791f', '#fffbeb'],
    'system'  => ['⚙️ Hệ thống',       '#c53030', '#fff5f5'],
];

// Toggle keys dạng bool
$bool_keys = ['maintenance_mode'];

// ---- Load tất cả settings ----
$rows_raw = $conn->query("SELECT * FROM site_settings ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$settings = []; // group => [key => row]
foreach ($rows_raw as $r) {
    // Dùng nhãn PHP, bỏ qua nhãn DB (có thể bị lỗi encoding)
    $r['label'] = $key_labels[$r['key']] ?? $r['key'];
    $settings[$r['group_name']][$r['key']] = $r;
}

// Sắp xếp theo thứ tự group đã định
$settings_ordered = [];
foreach ($group_order as $g) {
    if (isset($settings[$g])) $settings_ordered[$g] = $settings[$g];
}
// Thêm các group ngoài danh sách (nếu có)
foreach ($settings as $g => $v) {
    if (!isset($settings_ordered[$g])) $settings_ordered[$g] = $v;
}
$settings = $settings_ordered;

$page_title    = 'Cài đặt hệ thống';
$page_subtitle = 'Quản lý cấu hình chung của StayGo';
include("../includes/admin_header.php");
?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
<div style="padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13.5px;font-weight:600;color:#276749;background:#f0fff4;border:1px solid #9ae6b433">
    ✅ Đã lưu cài đặt thành công!
</div>
<?php endif; ?>

<form method="POST">
<?php foreach ($settings as $grp => $items):
    $gl = $group_labels[$grp] ?? ["📁 $grp", '#718096', '#f7fafc'];
?>
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:24px 28px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f1f5f9">
        <span style="padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700;color:<?= $gl[1] ?>;background:<?= $gl[2] ?>">
            <?= $gl[0] ?>
        </span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
    <?php foreach ($items as $key => $row):
        $val = htmlspecialchars($row['value'] ?? '');
        $lbl = htmlspecialchars($row['label']);
    ?>
    <div>
        <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:6px">
            <?= $lbl ?>
            <span style="font-size:10.5px;color:#a0aec0;font-weight:400;margin-left:4px"><?= htmlspecialchars($key) ?></span>
        </label>
        <?php if (in_array($key, $bool_keys)): ?>
        <select name="<?= htmlspecialchars($key) ?>"
            style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;background:#fff">
            <option value="0" <?= ($row['value'] == '0') ? 'selected' : '' ?>>Tắt (bình thường)</option>
            <option value="1" <?= ($row['value'] == '1') ? 'selected' : '' ?>>Bật (bảo trì)</option>
        </select>
        <?php else: ?>
        <input type="<?= is_numeric($row['value']) ? 'text' : 'text' ?>"
               name="<?= htmlspecialchars($key) ?>"
               value="<?= $val ?>"
               style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;box-sizing:border-box"
               placeholder="<?= $lbl ?>">
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<div style="display:flex;gap:12px;align-items:center;margin-top:8px">
    <button type="submit"
        style="padding:12px 32px;background:#1e73be;color:#fff;border:none;border-radius:10px;font-size:14.5px;font-weight:700;cursor:pointer;box-shadow:0 2px 8px rgba(30,115,190,.3)"
        onmouseover="this.style.background='#1558a8'" onmouseout="this.style.background='#1e73be'">
        💾 Lưu tất cả cài đặt
    </button>
    <a href="site_settings.php"
       style="padding:12px 22px;background:#f1f5f9;color:#4a5568;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none">
        Đặt lại
    </a>
</div>
</form>

<?php include("../includes/admin_footer.php"); ?>
