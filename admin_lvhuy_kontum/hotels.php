<?php
require_once __DIR__ . '/../includes/security.php';
include("../config/database.php");

$page_title    = 'Khách sạn';
$page_subtitle = 'Danh sách khách sạn';
include("../includes/admin_header.php");

// Thông báo
$msg_map = [
    'deleted' => ['🗑️ Đã xóa khách sạn thành công!', '#c53030', '#fff5f5'],
    'updated' => ['✅ Đã cập nhật khách sạn thành công!', '#276749', '#f0fff4'],
    'added'   => ['✅ Đã thêm khách sạn thành công!', '#276749', '#f0fff4'],
];
if (isset($_GET['success']) && isset($msg_map[$_GET['success']])):
    $n = $msg_map[$_GET['success']];
    echo "<div style='padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:{$n[1]};background:{$n[2]};border:1px solid {$n[1]}33'>{$n[0]}</div>";
endif;

$search     = trim($_GET['search'] ?? '');
$per_page   = 15;
$page       = max(1, (int)($_GET['p'] ?? 1));

$where = '';
if ($search) {
    $sw    = $conn->real_escape_string($search);
    $where = "WHERE name LIKE '%$sw%' OR address LIKE '%$sw%'";
}

$total_rows  = $conn->query("SELECT COUNT(*) as c FROM hotels $where")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$result = $conn->query("SELECT * FROM hotels $where ORDER BY id ASC LIMIT $per_page OFFSET $offset");

function hotels_qs($overrides = []) {
    $base    = ['search' => $_GET['search'] ?? ''];
    $merged  = array_merge($base, $overrides);
    $filtered = [];
    foreach ($merged as $k => $v) { if ($v !== '') $filtered[$k] = $v; }
    return http_build_query($filtered);
}
?>

<div class="page-header">
    <h2>🏨 Danh sách khách sạn</h2>
    <a href="add_hotel.php" class="btn btn-add" style="margin-bottom:0">+ Thêm khách sạn</a>
</div>

<form method="GET" action="">
    <div class="search-bar">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search"
            placeholder="Tìm theo tên hoặc địa chỉ khách sạn..."
            value="<?= htmlspecialchars($search) ?>">
        <?php if ($search): ?>
            <span class="search-result-info">Tìm thấy <strong><?= $total_rows ?></strong> khách sạn</span>
            <a href="hotels.php" class="btn-reset">✕ Xóa</a>
        <?php endif; ?>
        <button type="submit" class="btn-search">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
            </svg>
            Tìm kiếm
        </button>
    </div>
</form>

<div class="section-card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên khách sạn</th>
                <th>Địa chỉ</th>
                <th>Giá từ</th>
                <th>Đánh giá</th>
                <th>Trạng thái</th>
                <th>Deal cuối tuần</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#a0aec0;padding:40px">
                    <?= $search ? 'Không tìm thấy khách sạn nào với từ khóa "<strong>' . htmlspecialchars($search) . '</strong>"' : 'Chưa có khách sạn nào' ?>
                </td>
            </tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()):
                $hotel_name = htmlspecialchars($row['name']);
                $address    = htmlspecialchars($row['address'] ?? '');
                if ($search) {
                    $kw = preg_quote(htmlspecialchars($search), '/');
                    $hotel_name = preg_replace('/(' . $kw . ')/i', '<span class="search-highlight">$1</span>', $hotel_name);
                    $address    = preg_replace('/(' . $kw . ')/i', '<span class="search-highlight">$1</span>', $address);
                }
                $rating = (float)($row['rating'] ?? 0);
                $stars  = $rating >= 9 ? '⭐⭐⭐⭐⭐' : ($rating >= 8 ? '⭐⭐⭐⭐' : ($rating >= 7 ? '⭐⭐⭐' : '⭐⭐'));
            ?>
            <tr>
                <td class="td-order"><?= $row['id'] ?></td>
                <td class="td-name"><?= $hotel_name ?></td>
                <td style="color:#718096;max-width:280px;font-size:12.5px"><?= $address ?></td>
                <td class="td-price"><?= $row['price'] ? number_format($row['price'], 0, ',', '.') . 'đ' : '—' ?></td>
                <td>
                    <?php if ($rating > 0): ?>
                        <span style="font-weight:700;color:#1a202c"><?= number_format($rating, 1) ?></span>
                        <span style="font-size:11px;color:#718096;margin-left:3px"><?= $stars ?></span>
                    <?php else: ?>
                        <span style="color:#a0aec0">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($row['is_active'] ?? 0) == 1): ?>
                        <span class="status-badge" style="color:#276749;background:#f0fff4">✅ Hoạt động</span>
                    <?php else: ?>
                        <span class="status-badge" style="color:#c53030;background:#fff5f5">⏸ Tạm dừng</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($row['is_weekend_deal'] ?? 0) == 1): ?>
                        <span class="status-badge" style="color:#6d28d9;background:#f5f3ff">🏷️ Deal cuối tuần</span>
                    <?php else: ?>
                        <span style="color:#a0aec0;font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="hotel_stats.php?id=<?= $row['id'] ?>" class="btn btn-edit" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0">📊</a>
                    <a href="manage_hotel.php?id=<?= $row['id'] ?>" class="btn btn-edit">✏️ Sửa</a>
                    <a href="manage_hotel.php?id=<?= $row['id'] ?>&action=delete"
                       class="btn btn-delete"
                       onclick="return confirm('Xóa khách sạn «<?= addslashes($row['name']) ?>»?')">🗑️ Xóa</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap">
    <?php if ($page > 1): ?>
        <a href="hotels.php?<?= hotels_qs(['p' => $page - 1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">‹ Trước</a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
    if ($start > 1): ?>
        <a href="hotels.php?<?= hotels_qs(['p' => 1]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">1</a>
        <?php if ($start > 2): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="hotels.php?<?= hotels_qs(['p' => $i]) ?>"
           style="padding:7px 12px;border-radius:8px;border:1.5px solid <?= $i == $page ? '#1e73be' : '#e2e8f0' ?>;color:<?= $i == $page ? '#fff' : '#4a5568' ?>;background:<?= $i == $page ? '#1e73be' : '#fff' ?>;text-decoration:none;font-size:13px;font-weight:<?= $i == $page ? '700' : '400' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
    <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
        <a href="hotels.php?<?= hotels_qs(['p' => $total_pages]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff"><?= $total_pages ?></a>
    <?php endif; ?>
    <?php if ($page < $total_pages): ?>
        <a href="hotels.php?<?= hotels_qs(['p' => $page + 1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">Tiếp ›</a>
    <?php endif; ?>
    <span style="font-size:12px;color:#a0aec0;margin-left:8px">Trang <?= $page ?>/<?= $total_pages ?> · <?= $total_rows ?> khách sạn</span>
</div>
<?php endif; ?>

<?php include("../includes/admin_footer.php"); ?>
