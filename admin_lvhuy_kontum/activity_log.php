<?php
include "../config/database.php";

$page_title    = 'Nhật ký hoạt động';
$page_subtitle = 'Lịch sử thao tác của Admin';
include "../includes/admin_header.php";

// ---- Tham số lọc ----
$search     = trim($_GET['search']  ?? '');
$filter_act = trim($_GET['action']  ?? '');
$date_from  = trim($_GET['from']    ?? '');
$date_to    = trim($_GET['to']      ?? '');
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
if ($date_to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = '';

$per_page = 20;
$page     = max(1, (int)($_GET['p'] ?? 1));

$where_parts = [];
if ($search) {
    $sw = $conn->real_escape_string($search);
    $where_parts[] = "(admin_name LIKE '%$sw%' OR detail LIKE '%$sw%' OR target LIKE '%$sw%')";
}
if ($filter_act) {
    $where_parts[] = "action = '" . $conn->real_escape_string($filter_act) . "'";
}
if ($date_from) $where_parts[] = "DATE(created_at) >= '$date_from'";
if ($date_to)   $where_parts[] = "DATE(created_at) <= '$date_to'";

$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$total    = $conn->query("SELECT COUNT(*) as c FROM activity_log $where")->fetch_assoc()['c'];
$tot_pgs  = max(1, (int)ceil($total / $per_page));
$page     = min($page, $tot_pgs);
$offset   = ($page - 1) * $per_page;

$rows = $conn->query("SELECT * FROM activity_log $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

// Lấy danh sách action đã có để dropdown filter
$actions_list = $conn->query("SELECT DISTINCT action FROM activity_log ORDER BY action")->fetch_all(MYSQLI_ASSOC);

// Action label map
$action_labels = [
    'confirm_booking' => ['✅ Xác nhận booking', '#276749', '#f0fff4'],
    'cancel_booking'  => ['❌ Huỷ booking',       '#c53030', '#fff5f5'],
    'complete_booking'=> ['🎉 Hoàn thành booking','#1e73be', '#ebf8ff'],
    'edit_booking'    => ['✏️ Sửa booking',       '#b7791f', '#fffbeb'],
    'approve_refund'  => ['💰 Duyệt hoàn tiền',   '#276749', '#f0fff4'],
    'reject_refund'   => ['🚫 Từ chối hoàn tiền', '#c53030', '#fff5f5'],
    'delete_user'     => ['🗑️ Xóa user',          '#c53030', '#fff5f5'],
    'add_hotel'       => ['🏨 Thêm khách sạn',    '#276749', '#f0fff4'],
    'delete_hotel'    => ['🗑️ Xóa khách sạn',     '#c53030', '#fff5f5'],
    'update_hotel'    => ['✏️ Sửa khách sạn',     '#b7791f', '#fffbeb'],
    'add_voucher'     => ['🏷️ Tạo voucher',        '#6d28d9', '#f5f3ff'],
    'delete_voucher'  => ['🗑️ Xóa voucher',        '#c53030', '#fff5f5'],
    'resolve_support' => ['💬 Xử lý hỗ trợ',      '#1e73be', '#ebf8ff'],
    'delete_review'   => ['⭐ Xóa đánh giá',       '#b7791f', '#fffbeb'],
    'login'           => ['🔑 Đăng nhập',           '#276749', '#f0fff4'],
];

function log_qs($overrides = []) {
    $base = ['search'=>$_GET['search']??'','action'=>$_GET['action']??'','from'=>$_GET['from']??'','to'=>$_GET['to']??''];
    $merged = array_merge($base, $overrides);
    $f = [];
    foreach ($merged as $k => $v) { if ($v !== '') $f[$k] = $v; }
    return http_build_query($f);
}
?>

<!-- Bộ lọc -->
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:18px 22px;margin-bottom:18px">
    <form method="GET" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div>
            <label style="font-size:12px;font-weight:700;color:#718096;display:block;margin-bottom:4px">Tìm kiếm</label>
            <input type="text" name="search" placeholder="Admin, đối tượng, chi tiết..."
                value="<?= htmlspecialchars($search) ?>"
                style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;width:200px">
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:#718096;display:block;margin-bottom:4px">Loại hành động</label>
            <select name="action" style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff">
                <option value="">Tất cả</option>
                <?php foreach ($actions_list as $al): ?>
                <option value="<?= htmlspecialchars($al['action']) ?>" <?= $filter_act === $al['action'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($action_labels[$al['action']][0] ?? $al['action']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:#718096;display:block;margin-bottom:4px">Từ ngày</label>
            <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>"
                style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:#718096;display:block;margin-bottom:4px">Đến ngày</label>
            <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>"
                style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
        </div>
        <button type="submit"
            style="padding:9px 20px;background:#1e73be;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">
            Lọc
        </button>
        <?php if ($search || $filter_act || $date_from || $date_to): ?>
        <a href="activity_log.php" style="padding:9px 16px;background:#f1f5f9;color:#64748b;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none">✕ Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<!-- Bảng log -->
<div class="section-card">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #f1f5f9">
        <span style="font-size:13.5px;font-weight:700;color:#1a202c">📋 Nhật ký hoạt động</span>
        <span style="font-size:12px;color:#a0aec0">Tổng: <?= $total ?> bản ghi</span>
    </div>
    <?php if ($rows->num_rows === 0): ?>
    <div style="text-align:center;padding:60px;color:#a0aec0">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.5" style="margin-bottom:10px">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p style="margin:0">Chưa có bản ghi nào</p>
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width:160px">Thời gian</th>
                <th style="width:130px">Admin</th>
                <th style="width:200px">Hành động</th>
                <th>Chi tiết</th>
                <th style="width:100px">IP</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $rows->fetch_assoc()):
            $al = $action_labels[$row['action']] ?? [$row['action'], '#718096', '#f7fafc'];
        ?>
        <tr>
            <td style="font-size:12px;color:#a0aec0;white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?></td>
            <td>
                <div style="font-weight:600;font-size:13px;color:#1a202c"><?= htmlspecialchars($row['admin_name']) ?></div>
                <div style="font-size:11px;color:#a0aec0">ID <?= $row['admin_id'] ?: '—' ?></div>
            </td>
            <td>
                <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;color:<?= $al[1] ?>;background:<?= $al[2] ?>">
                    <?= $al[0] ?>
                </span>
                <?php if ($row['target'] && $row['target_id']): ?>
                <div style="font-size:11px;color:#a0aec0;margin-top:3px"><?= htmlspecialchars($row['target']) ?> #<?= $row['target_id'] ?></div>
                <?php endif; ?>
            </td>
            <td style="font-size:13px;color:#4a5568;max-width:350px;line-height:1.5">
                <?= htmlspecialchars(mb_substr($row['detail'] ?? '', 0, 120)) ?>
            </td>
            <td style="font-size:11.5px;color:#a0aec0"><?= htmlspecialchars($row['ip'] ?? '—') ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Phân trang -->
<?php if ($tot_pgs > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap">
    <?php if ($page > 1): ?>
        <a href="activity_log.php?<?= log_qs(['p' => $page-1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">‹ Trước</a>
    <?php endif; ?>
    <?php
    $s = max(1,$page-2); $e = min($tot_pgs,$page+2);
    if ($s > 1) { echo "<a href='activity_log.php?".log_qs(['p'=>1])."' style='padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff'>1</a>"; if ($s>2) echo "<span style='color:#a0aec0;padding:0 4px'>…</span>"; }
    for ($i = $s; $i <= $e; $i++):
        $active_sty = $i==$page ? 'background:#1e73be;color:#fff;border-color:#1e73be;font-weight:700' : 'background:#fff;color:#4a5568;border-color:#e2e8f0';
    ?>
    <a href="activity_log.php?<?= log_qs(['p'=>$i]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid;text-decoration:none;font-size:13px;<?= $active_sty ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php
    if ($e < $tot_pgs) { if ($e<$tot_pgs-1) echo "<span style='color:#a0aec0;padding:0 4px'>…</span>"; echo "<a href='activity_log.php?".log_qs(['p'=>$tot_pgs])."' style='padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff'>$tot_pgs</a>"; }
    if ($page < $tot_pgs):
    ?>
    <a href="activity_log.php?<?= log_qs(['p'=>$page+1]) ?>"
       style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">Tiếp ›</a>
    <?php endif; ?>
    <span style="font-size:12px;color:#a0aec0;margin-left:8px">Trang <?= $page ?>/<?= $tot_pgs ?> · <?= $total ?> bản ghi</span>
</div>
<?php endif; ?>

<?php include "../includes/admin_footer.php"; ?>
