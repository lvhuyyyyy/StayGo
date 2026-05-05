<?php
include("../config/database.php");

$page_title    = 'Phòng';
$page_subtitle = 'Danh sách tất cả loại phòng';
include("../includes/admin_header.php");

// ---------- Tham số ----------
$search   = trim($_GET['search'] ?? '');
$per_page = 15;
$page     = max(1, (int)($_GET['p'] ?? 1));

$where = '';
if ($search) {
    $sw    = $conn->real_escape_string($search);
    $where = "WHERE h.name LIKE '%$sw%' OR r.room_name LIKE '%$sw%'";
}

$total_rows  = $conn->query("SELECT COUNT(*) as c FROM rooms r LEFT JOIN hotels h ON r.hotel_id = h.id $where")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$result = $conn->query("
    SELECT r.*, h.name AS hotel_name
    FROM rooms r
    LEFT JOIN hotels h ON r.hotel_id = h.id
    $where
    ORDER BY r.hotel_id ASC, r.id ASC
    LIMIT $per_page OFFSET $offset
");

function rooms_qs($overrides = []) {
    $base    = ['search' => $_GET['search'] ?? ''];
    $merged  = array_merge($base, $overrides);
    $filtered = [];
    foreach ($merged as $k => $v) { if ($v !== '') $filtered[$k] = $v; }
    return http_build_query($filtered);
}
?>

<div class="page-header">
    <h2>🛏️ Danh sách phòng</h2>
    <a href="edit_room.php" class="btn btn-add">+ Thêm phòng</a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:10px 16px;border-radius:8px;margin-bottom:14px">
        <?php
        $success_msgs = ['deleted' => 'Đã xóa phòng thành công!', 'added' => 'Đã thêm phòng thành công!', 'updated' => 'Đã cập nhật phòng thành công!'];
        echo $success_msgs[$_GET['success']] ?? 'Thao tác thành công!';
        ?>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div style="background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:10px 16px;border-radius:8px;margin-bottom:14px">
        <?php
        $error_msgs = [
            'invalid_id'   => 'ID không hợp lệ.',
            'not_found'    => 'Không tìm thấy phòng.',
            'has_bookings' => 'Không thể xóa phòng đang có ' . htmlspecialchars($_GET['count'] ?? '') . ' booking chưa hoàn thành.',
            'failed'       => 'Xóa thất bại, vui lòng thử lại.',
        ];
        echo $error_msgs[$_GET['error']] ?? 'Có lỗi xảy ra.';
        ?>
    </div>
<?php endif; ?>

<form method="GET" action="">
    <div class="search-bar">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search"
            placeholder="Tìm theo tên khách sạn hoặc tên phòng..."
            value="<?= htmlspecialchars($search) ?>">
        <?php if ($search): ?>
            <span class="search-result-info">Tìm thấy <strong><?= $total_rows ?></strong> phòng</span>
            <a href="rooms.php" class="btn-reset">✕ Xóa</a>
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
                <th>Khách sạn</th>
                <th>Loại phòng</th>
                <th>Giá tiền</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="6" style="text-align:center;color:#a0aec0;padding:40px">
                    <?= $search ? 'Không tìm thấy phòng nào với từ khóa "<strong>' . htmlspecialchars($search) . '</strong>"' : 'Chưa có phòng nào' ?>
                </td>
            </tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()):
                $hotel_name = htmlspecialchars($row['hotel_name'] ?? '-');
                $room_name  = htmlspecialchars($row['room_name']);
                if ($search) {
                    $kw = preg_quote(htmlspecialchars($search), '/');
                    $hotel_name = preg_replace('/(' . $kw . ')/i', '<span class="search-highlight">$1</span>', $hotel_name);
                    $room_name  = preg_replace('/(' . $kw . ')/i', '<span class="search-highlight">$1</span>', $room_name);
                }
            ?>
            <tr>
                <td class="td-order"><?= $row['id'] ?></td>
                <td class="td-name"><?= $hotel_name ?></td>
                <td style="color:#2d3748"><?= $room_name ?></td>
                <td class="td-price"><?= number_format($row['price'], 0, ',', '.') ?>đ</td>
                <td>
                    <?php if (!isset($row['is_active']) || $row['is_active'] == 1): ?>
                        <span class="status-badge" style="color:#276749;background:#f0fff4">Hoạt động</span>
                    <?php else: ?>
                        <span class="status-badge" style="color:#c53030;background:#fff5f5">Tạm dừng</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="edit_room.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-edit">Sửa</a>
                    <a href="delete_room.php?id=<?= $row['id'] ?>"
                       class="btn btn-delete"
                       onclick="return confirm('Bạn có chắc muốn xóa phòng \"<?= addslashes($row['room_name']) ?>\" không?')">Xóa</a>
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
        <a href="rooms.php?<?= rooms_qs(['p' => $page - 1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">‹ Trước</a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
    if ($start > 1): ?>
        <a href="rooms.php?<?= rooms_qs(['p' => 1]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">1</a>
        <?php if ($start > 2): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="rooms.php?<?= rooms_qs(['p' => $i]) ?>"
           style="padding:7px 12px;border-radius:8px;border:1.5px solid <?= $i == $page ? '#1e73be' : '#e2e8f0' ?>;color:<?= $i == $page ? '#fff' : '#4a5568' ?>;background:<?= $i == $page ? '#1e73be' : '#fff' ?>;text-decoration:none;font-size:13px;font-weight:<?= $i == $page ? '700' : '400' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
    <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
        <a href="rooms.php?<?= rooms_qs(['p' => $total_pages]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff"><?= $total_pages ?></a>
    <?php endif; ?>
    <?php if ($page < $total_pages): ?>
        <a href="rooms.php?<?= rooms_qs(['p' => $page + 1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">Tiếp ›</a>
    <?php endif; ?>
    <span style="font-size:12px;color:#a0aec0;margin-left:8px">Trang <?= $page ?>/<?= $total_pages ?> · <?= $total_rows ?> phòng</span>
</div>
<?php endif; ?>

<?php include("../includes/admin_footer.php"); ?>
