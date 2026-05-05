<?php
include("../config/database.php");

// ---- Xử lý xóa ----
if (isset($_GET['delete'])) {
    $del = (int)$_GET['delete'];
    $conn->query("DELETE FROM vouchers WHERE id = $del");
    log_activity($conn, 'delete_voucher', 'voucher', $del, "Xóa voucher #$del");
    header("Location: vouchers.php?msg=deleted");
    exit;
}

// ---- Xử lý toggle ----
if (isset($_GET['toggle'])) {
    $tog = (int)$_GET['toggle'];
    $conn->query("UPDATE vouchers SET is_active = 1 - is_active WHERE id = $tog");
    log_activity($conn, 'toggle_voucher', 'voucher', $tog, "Bật/tắt voucher #$tog");
    header("Location: vouchers.php");
    exit;
}

$errors = [];
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_row = null;
if ($edit_id) {
    $edit_row = $conn->query("SELECT * FROM vouchers WHERE id = $edit_id")->fetch_assoc();
    if (!$edit_row) { header("Location: vouchers.php"); exit; }
}

// ---- Xử lý POST thêm/sửa ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid         = (int)($_POST['id'] ?? 0);
    $code        = strtoupper(trim($_POST['code']        ?? ''));
    $type        = in_array($_POST['type'] ?? '', ['percent','fixed']) ? $_POST['type'] : 'percent';
    $value       = max(0, (float)($_POST['value']        ?? 0));
    $min_order   = max(0, (float)($_POST['min_order']    ?? 0));
    $max_uses    = max(1, (int)  ($_POST['max_uses']     ?? 1));
    $expires_at  = trim($_POST['expires_at'] ?? '');
    $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$code) $errors[] = 'Mã voucher không được trống.';
    elseif (!preg_match('/^[A-Z0-9_\-]{3,30}$/', $code)) $errors[] = 'Mã chỉ gồm chữ in hoa, số, dấu _ hoặc - (3–30 ký tự).';
    if ($value <= 0) $errors[] = 'Giá trị giảm phải lớn hơn 0.';
    if ($type === 'percent' && $value > 100) $errors[] = 'Phần trăm giảm không được vượt 100%.';
    if ($expires_at && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_at)) $expires_at = null;

    // Kiểm tra trùng code
    $code_esc = $conn->real_escape_string($code);
    $dup = $conn->query("SELECT id FROM vouchers WHERE code='$code_esc'" . ($pid ? " AND id != $pid" : ""));
    if ($dup->num_rows > 0) $errors[] = 'Mã voucher đã tồn tại.';

    if (empty($errors)) {
        $exp_sql = $expires_at ? "'$expires_at'" : "NULL";
        if ($pid) {
            $conn->query("UPDATE vouchers SET code='$code_esc', type='$type', value=$value,
                min_order=$min_order, max_uses=$max_uses, expires_at=$exp_sql,
                is_active=$is_active, description='$description' WHERE id=$pid");
            log_activity($conn, 'edit_voucher', 'voucher', $pid, "Sửa voucher: $code");
            header("Location: vouchers.php?msg=updated"); exit;
        } else {
            $conn->query("INSERT INTO vouchers (code,type,value,min_order,max_uses,expires_at,is_active,description)
                VALUES ('$code_esc','$type',$value,$min_order,$max_uses,$exp_sql,$is_active,'$description')");
            log_activity($conn, 'add_voucher', 'voucher', $conn->insert_id, "Thêm voucher: $code");
            header("Location: vouchers.php?msg=added"); exit;
        }
    }
}

// ---- Danh sách voucher ----
$search   = trim($_GET['search'] ?? '');
$per_page = 12;
$page     = max(1, (int)($_GET['p'] ?? 1));
$where    = $search ? "WHERE code LIKE '%" . $conn->real_escape_string($search) . "%' OR description LIKE '%" . $conn->real_escape_string($search) . "%'" : '';
$total    = $conn->query("SELECT COUNT(*) as c FROM vouchers $where")->fetch_assoc()['c'];
$tot_pgs  = max(1, (int)ceil($total / $per_page));
$page     = min($page, $tot_pgs);
$offset   = ($page - 1) * $per_page;
$rows     = $conn->query("SELECT * FROM vouchers $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

$page_title    = 'Voucher';
$page_subtitle = 'Quản lý mã giảm giá';
include("../includes/admin_header.php");

$msgs = ['added'=>['✅ Đã tạo voucher thành công!','#276749','#f0fff4'],'updated'=>['✅ Đã cập nhật voucher!','#276749','#f0fff4'],'deleted'=>['🗑️ Đã xóa voucher!','#c53030','#fff5f5']];
if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])):
    $n = $msgs[$_GET['msg']];
    echo "<div style='padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:{$n[1]};background:{$n[2]};border:1px solid {$n[1]}33'>{$n[0]}</div>";
endif;
?>

<div class="page-header">
    <h2>🏷️ Quản lý Voucher</h2>
    <a href="vouchers.php" class="btn btn-add" style="margin-bottom:0">+ Tạo voucher</a>
</div>

<!-- Form thêm / sửa -->
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:24px 28px;margin-bottom:22px">
    <h3 style="font-size:15px;font-weight:700;color:#1a202c;margin:0 0 18px">
        <?= $edit_id ? '✏️ Sửa voucher' : '➕ Tạo voucher mới' ?>
    </h3>
    <?php if (!empty($errors)): ?>
    <div style="background:#fff5f5;border:1.5px solid #feb2b2;border-radius:10px;padding:10px 16px;margin-bottom:16px">
        <?php foreach ($errors as $e): ?>
            <div style="color:#c53030;font-size:13px;font-weight:600">❌ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="POST" action="vouchers.php<?= $edit_id ? "?edit=$edit_id" : '' ?>">
        <?php if ($edit_id): ?><input type="hidden" name="id" value="<?= $edit_id ?>"><?php endif; ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:14px">
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Mã voucher *</label>
                <input type="text" name="code" placeholder="VD: SUMMER20"
                    value="<?= htmlspecialchars($_POST['code'] ?? strtoupper($edit_row['code'] ?? '')) ?>"
                    style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;box-sizing:border-box;text-transform:uppercase">
            </div>
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Loại giảm *</label>
                <select name="type" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;background:#fff">
                    <option value="percent" <?= ($_POST['type'] ?? $edit_row['type'] ?? '') === 'percent' ? 'selected' : '' ?>>% Phần trăm</option>
                    <option value="fixed"   <?= ($_POST['type'] ?? $edit_row['type'] ?? '') === 'fixed'   ? 'selected' : '' ?>>đ Số tiền cố định</option>
                </select>
            </div>
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Giá trị *</label>
                <input type="number" name="value" min="1" step="0.01" placeholder="20"
                    value="<?= htmlspecialchars($_POST['value'] ?? $edit_row['value'] ?? '') ?>"
                    style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Đơn tối thiểu (đ)</label>
                <input type="number" name="min_order" min="0" step="1000" placeholder="0"
                    value="<?= htmlspecialchars($_POST['min_order'] ?? $edit_row['min_order'] ?? 0) ?>"
                    style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Số lần dùng tối đa</label>
                <input type="number" name="max_uses" min="1" placeholder="100"
                    value="<?= htmlspecialchars($_POST['max_uses'] ?? $edit_row['max_uses'] ?? 1) ?>"
                    style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Ngày hết hạn</label>
                <input type="date" name="expires_at"
                    value="<?= htmlspecialchars($_POST['expires_at'] ?? $edit_row['expires_at'] ?? '') ?>"
                    style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;box-sizing:border-box">
            </div>
        </div>
        <div style="margin-bottom:14px">
            <label style="font-size:12.5px;font-weight:700;color:#4a5568;display:block;margin-bottom:5px">Mô tả</label>
            <input type="text" name="description" placeholder="Mô tả ngắn về voucher này..."
                value="<?= htmlspecialchars($_POST['description'] ?? $edit_row['description'] ?? '') ?>"
                style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;box-sizing:border-box">
        </div>
        <div style="display:flex;align-items:center;gap:16px">
            <label style="display:flex;align-items:center;gap:7px;font-size:13.5px;color:#4a5568;font-weight:600;cursor:pointer">
                <input type="checkbox" name="is_active" value="1"
                    <?= (isset($_POST['is_active']) ? $_POST['is_active'] : ($edit_row['is_active'] ?? 1)) ? 'checked' : '' ?>
                    style="width:16px;height:16px">
                Kích hoạt ngay
            </label>
            <button type="submit"
                style="padding:10px 24px;background:#1e73be;color:#fff;border:none;border-radius:9px;font-size:13.5px;font-weight:700;cursor:pointer">
                <?= $edit_id ? '💾 Lưu thay đổi' : '➕ Tạo voucher' ?>
            </button>
            <?php if ($edit_id): ?>
            <a href="vouchers.php" style="padding:10px 18px;background:#f1f5f9;color:#4a5568;border-radius:9px;font-size:13.5px;font-weight:600;text-decoration:none">Hủy</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Search -->
<form method="GET" action="">
    <div class="search-bar" style="margin-bottom:16px">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search" placeholder="Tìm theo mã hoặc mô tả..." value="<?= htmlspecialchars($search) ?>">
        <?php if ($search): ?>
            <a href="vouchers.php" class="btn-reset">✕ Xóa</a>
        <?php endif; ?>
        <button type="submit" class="btn-search">Tìm kiếm</button>
    </div>
</form>

<!-- Bảng danh sách -->
<div class="section-card">
    <table>
        <thead>
            <tr>
                <th>Mã</th>
                <th>Loại / Giá trị</th>
                <th>Đơn tối thiểu</th>
                <th>Đã dùng / Tối đa</th>
                <th>Hết hạn</th>
                <th>Trạng thái</th>
                <th>Mô tả</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="8" style="text-align:center;color:#a0aec0;padding:40px">Chưa có voucher nào</td></tr>
        <?php else: ?>
        <?php while ($row = $rows->fetch_assoc()):
            $expired  = $row['expires_at'] && strtotime($row['expires_at']) < strtotime('today');
            $depleted = $row['used_count'] >= $row['max_uses'];
            $active   = $row['is_active'] && !$expired && !$depleted;
        ?>
            <tr>
                <td style="font-family:monospace;font-weight:800;font-size:14px;color:#1a202c;letter-spacing:1px">
                    <?= htmlspecialchars($row['code']) ?>
                </td>
                <td>
                    <?php if ($row['type'] === 'percent'): ?>
                        <span style="font-weight:700;color:#1e73be">-<?= number_format($row['value'], 0) ?>%</span>
                    <?php else: ?>
                        <span style="font-weight:700;color:#276749">-<?= number_format($row['value'], 0, ',', '.') ?>đ</span>
                    <?php endif; ?>
                </td>
                <td style="color:#718096"><?= $row['min_order'] > 0 ? number_format($row['min_order'], 0, ',', '.') . 'đ' : '—' ?></td>
                <td>
                    <span style="font-weight:700;color:<?= $depleted ? '#c53030' : '#276749' ?>">
                        <?= $row['used_count'] ?> / <?= $row['max_uses'] ?>
                    </span>
                    <?php if ($depleted): ?>
                        <span style="font-size:11px;color:#c53030;display:block">Hết lượt</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12.5px;color:<?= $expired ? '#c53030' : '#718096' ?>">
                    <?= $row['expires_at'] ? date('d/m/Y', strtotime($row['expires_at'])) : '∞ Không hết hạn' ?>
                    <?php if ($expired): ?><span style="font-size:11px;display:block;font-weight:600">Đã hết hạn</span><?php endif; ?>
                </td>
                <td>
                    <a href="vouchers.php?toggle=<?= $row['id'] ?>" style="text-decoration:none">
                        <span class="status-badge" style="color:<?= $active ? '#276749' : '#c53030' ?>;background:<?= $active ? '#f0fff4' : '#fff5f5' ?>;cursor:pointer">
                            <?= $active ? '✅ Hoạt động' : '⏸ Tắt' ?>
                        </span>
                    </a>
                </td>
                <td style="font-size:12.5px;color:#718096;max-width:180px"><?= htmlspecialchars(mb_substr($row['description'] ?? '', 0, 50)) ?></td>
                <td style="white-space:nowrap">
                    <a href="vouchers.php?edit=<?= $row['id'] ?>" class="btn btn-edit">✏️ Sửa</a>
                    <a href="vouchers.php?delete=<?= $row['id'] ?>"
                       class="btn btn-delete"
                       onclick="return confirm('Xóa voucher <?= htmlspecialchars(addslashes($row['code'])) ?>?')">🗑️</a>
                </td>
            </tr>
        <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($tot_pgs > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap">
    <?php if ($page > 1): ?>
        <a href="vouchers.php?p=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">‹ Trước</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $tot_pgs; $i++): ?>
        <a href="vouchers.php?p=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>"
           style="padding:7px 12px;border-radius:8px;border:1.5px solid <?= $i==$page?'#1e73be':'#e2e8f0' ?>;color:<?= $i==$page?'#fff':'#4a5568' ?>;background:<?= $i==$page?'#1e73be':'#fff' ?>;text-decoration:none;font-size:13px"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $tot_pgs): ?>
        <a href="vouchers.php?p=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">Tiếp ›</a>
    <?php endif; ?>
    <span style="font-size:12px;color:#a0aec0;margin-left:8px">Trang <?= $page ?>/<?= $tot_pgs ?> · <?= $total ?> voucher</span>
</div>
<?php endif; ?>

<?php include("../includes/admin_footer.php"); ?>
