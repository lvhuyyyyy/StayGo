<?php
include("../config/database.php");

$page_title    = 'Quản Lý Đánh Giá';
$page_subtitle = 'Xem và xoá đánh giá của khách hàng';

// -- Lọc theo khách sạn & số sao --
$filter_hotel = isset($_GET['hotel_id']) ? (int)$_GET['hotel_id'] : 0;
$filter_star  = isset($_GET['star'])     ? (int)$_GET['star']     : 0;

// -- Danh sách khách sạn để filter --
$hotels_list = $conn->query("SELECT id, name FROM hotels WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// -- Lấy đánh giá --
$where = "WHERE r.is_active = 1";
if ($filter_hotel) $where .= " AND r.hotel_id = $filter_hotel";
if ($filter_star)  $where .= " AND r.rating = $filter_star";

$reviews = $conn->query("
    SELECT r.id, r.rating, r.comment, r.created_at,
        u.full_name, u.email,
        h.name AS hotel_name, h.id AS hotel_id,
        b.order_code
    FROM   reviews  r
    JOIN   users    u ON u.id = r.user_id
    JOIN   hotels   h ON h.id = r.hotel_id
    JOIN   bookings b ON b.id = r.booking_id
    $where
    ORDER  BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// -- Thống kê tổng --
$total_stats = $conn->query("
    SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS avg_r
    FROM reviews WHERE is_active = 1
")->fetch_assoc();

include("../includes/admin_header.php");
?>

<div class="page-header">
    <h2>⭐ Quản lý đánh giá</h2>
</div>

<!-- -- Thống kê nhanh -- -->
<div class="rv-admin-stats">
    <div class="rv-stat-card">
        <div class="rv-stat-num"><?= (int)$total_stats['total'] ?></div>
        <div class="rv-stat-label">Tổng đánh giá</div>
    </div>
    <div class="rv-stat-card accent">
        <div class="rv-stat-num"><?= number_format($total_stats['avg_r'] ?? 0, 1) ?> ⭐</div>
        <div class="rv-stat-label">Điểm TB toàn hệ thống</div>
    </div>
    <?php for ($s = 5; $s >= 1; $s--):
        $cnt = $conn->query("SELECT COUNT(*) AS c FROM reviews WHERE rating = $s AND is_active = 1")->fetch_assoc()['c'];
    ?>
    <div class="rv-stat-card">
        <div class="rv-stat-num"><?= (int)$cnt ?></div>
        <div class="rv-stat-label"><?= $s ?> sao</div>
    </div>
    <?php endfor; ?>
</div>

<!-- -- Bộ lọc -- -->
<div class="section-card" style="padding:16px 20px;margin-bottom:16px">
    <form method="GET" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <select name="hotel_id" class="rv-filter-select" onchange="this.form.submit()">
            <option value="">Tất cả khách sạn</option>
            <?php foreach ($hotels_list as $h): ?>
            <option value="<?= $h['id'] ?>" <?= $filter_hotel == $h['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($h['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="star" class="rv-filter-select" onchange="this.form.submit()">
            <option value="">Tất cả số sao</option>
            <?php for ($s = 5; $s >= 1; $s--): ?>
            <option value="<?= $s ?>" <?= $filter_star == $s ? 'selected' : '' ?>><?= $s ?> sao</option>
            <?php endfor; ?>
        </select>

        <?php if ($filter_hotel || $filter_star): ?>
        <a href="reviews.php" class="btn btn-reset-filter">✕ Xoá bộ lọc</a>
        <?php endif; ?>

        <span style="font-size:12.5px;color:#a0aec0;margin-left:auto">
            Hiển thị <strong><?= count($reviews) ?></strong> đánh giá
        </span>
    </form>
</div>

<!-- -- Bảng đánh giá -- -->
<div class="section-card">
    <?php if (empty($reviews)): ?>
    <div style="text-align:center;padding:60px;color:#a0aec0">
        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.2" style="margin-bottom:12px;display:block;margin-left:auto;margin-right:auto">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
        </svg>
        Chưa có đánh giá nào.
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Khách hàng</th>
                <th>Khách sạn</th>
                <th>Booking</th>
                <th>Đánh giá</th>
                <th>Nhận xét</th>
                <th>Thời gian</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reviews as $i => $rv): ?>
            <tr id="rv-row-<?= $rv['id'] ?>">
                <td class="td-order"><?= $i + 1 ?></td>
                <td>
                    <div class="rv-user-cell">
                        <div class="rv-mini-avatar">
                            <?= mb_strtoupper(mb_substr($rv['full_name'] ?: $rv['email'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="td-name"><?= htmlspecialchars($rv['full_name'] ?: 'Ẩn danh') ?></div>
                            <div style="font-size:11px;color:#a0aec0"><?= htmlspecialchars($rv['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <a href="/tour_khach_san_project/pages/hotel_detail.php?id=<?= $rv['hotel_id'] ?>"
                    target="_blank" class="rv-hotel-link">
                        <?= htmlspecialchars($rv['hotel_name']) ?>
                    </a>
                </td>
                <td>
                    <span class="rv-order-code"><?= htmlspecialchars($rv['order_code'] ?? '#'.$rv['id']) ?></span>
                </td>
                <td>
                    <div class="rv-stars-cell">
                        <?php for ($i2 = 1; $i2 <= 5; $i2++): ?>
                        <svg class="rv-s <?= $i2 <= $rv['rating'] ? 'on' : '' ?>" viewBox="0 0 24 24" width="14" height="14">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <?php endfor; ?>
                        <span style="font-size:12px;font-weight:700;color:#2d3748;margin-left:4px"><?= $rv['rating'] ?></span>
                    </div>
                </td>
                <td>
                    <div style="font-size:12.5px;color:#4a5568;max-width:220px;line-height:1.5"
                        title="<?= htmlspecialchars($rv['comment']) ?>">
                        <?= htmlspecialchars(mb_substr($rv['comment'], 0, 70)) ?><?= mb_strlen($rv['comment']) > 70 ? '...' : '' ?>
                    </div>
                </td>
                <td style="font-size:12px;color:#718096;white-space:nowrap">
                    <?= date('d/m/Y H:i', strtotime($rv['created_at'])) ?>
                </td>
                <td>
                    <button class="btn btn-delete"
                            onclick="deleteReview(<?= $rv['id'] ?>, '<?= htmlspecialchars(addslashes($rv['hotel_name'])) ?>')">
                        Xoá
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- -- Toast thông báo -- -->
<div class="rv-toast" id="rv-toast"></div>

<?php include("../includes/admin_footer.php"); ?>