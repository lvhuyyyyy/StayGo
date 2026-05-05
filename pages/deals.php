<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// KHÔNG chặn session – ai cũng xem được trang ưu đãi
// Chỉ kiểm tra đăng nhập khi nhấn "Đặt ngay" (xử lý bằng JS bookNow)
$is_logged_in = isset($_SESSION['user_id']) ? 'true' : 'false';

// -- Bộ lọc --
$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort']   ?? 'discount_desc';

$where = ["h.is_active = 1", "h.old_price > h.price"];

if ($filter === 'weekend')   $where[] = "h.is_weekend_deal = 1";
if ($filter === 'top_rated') $where[] = "h.rating >= 9";
if ($filter === 'discount')  $where[] = "ROUND((1 - h.price/h.old_price)*100) >= 20";

$order = match($sort) {
    'price_asc'     => "h.price ASC",
    'price_desc'    => "h.price DESC",
    'rating'        => "h.rating DESC",
    'discount_desc' => "discount_pct DESC",
    default         => "discount_pct DESC",
};

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT h.*, l.name AS location_name,
        COALESCE(MIN(r.price), h.price) AS min_room_price,
        ROUND((1 - h.price / h.old_price) * 100) AS discount_pct
    FROM hotels h
    LEFT JOIN locations l ON h.location_id = l.id
    LEFT JOIN rooms r ON r.hotel_id = h.id
    WHERE $where_sql
    GROUP BY h.id
    ORDER BY $order
";

$hotels       = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$total        = count($hotels);
$max_discount = !empty($hotels) ? max(array_column($hotels, 'discount_pct')) : 0;
$avg_discount = !empty($hotels) ? round(array_sum(array_column($hotels, 'discount_pct')) / $total) : 0;

$rating_label = function($r) {
    return match(true) {
        $r >= 9 => 'Tuyệt vời',
        $r >= 8 => 'Rất tốt',
        $r >= 7 => 'Tốt',
        default => 'Bình thường'
    };
};
?>

<?php if (!isset($_SESSION['user_id'])): ?>
<!-- Banner gợi ý cho khách chưa đăng nhập -->
<div class="guest-hint-bar">
    🎁 <strong>Tạo tài khoản miễn phí</strong> để nhận <strong>giảm 10%</strong> cho lần đặt phòng đầu tiên!
    <div class="guest-hint-actions">
        <a href="/tour_khach_san_project/auth/register.php" class="hint-btn-register">Tạo tài khoản</a>
        <a href="/tour_khach_san_project/auth/login.php" class="hint-btn-login">Đăng nhập</a>
    </div>
</div>
<?php endif; ?>

<!-- HERO BANNER -->
<div class="deals-hero">
    <div class="deals-hero-inner">
        <div class="deals-hero-text">
            <div class="deals-hero-tag">⏰ Ưu đãi có hạn</div>
            <h1>Ưu đãi đặc biệt</h1>
            <p>Tiết kiệm lớn tại các khách sạn hàng đầu – Đặt ngay trước khi hết!</p>
            <div class="deals-hero-stats">
                <div class="dhs-item">
                    <strong><?= $total ?></strong>
                    <span>Khách sạn giảm giá</span>
                </div>
                <div class="dhs-divider"></div>
                <div class="dhs-item">
                    <strong>Đến <?= $max_discount ?>%</strong>
                    <span>Giảm tối đa</span>
                </div>
                <div class="dhs-divider"></div>
                <div class="dhs-item">
                    <strong>TB <?= $avg_discount ?>%</strong>
                    <span>Mức giảm trung bình</span>
                </div>
            </div>
        </div>
        <div class="deals-hero-img">
            <img src="/tour_khach_san_project/assets/images/promo.jpg" alt="">
        </div>
    </div>
</div>

<!-- PROMO TAGS -->
<div class="deals-promo-bar">
    <div class="deals-container">
        <div class="promo-tags">
            <div class="ptag ptag-red">⚡ Flash Sale – Giảm đến <?= $max_discount ?>%</div>
            <div class="ptag ptag-green">✅ Miễn phí hủy phòng</div>
            <div class="ptag ptag-blue">🔒 Giá tốt nhất đảm bảo</div>
            <div class="ptag ptag-orange">🎉 Ưu đãi Đầu Năm 2026 – Đến 1/4/2026</div>
        </div>
    </div>
</div>

<!-- FILTER + SORT -->
<div class="deals-container" style="padding-top:28px">

    <div class="deals-filter-bar">
        <div class="deal-tabs">
            <?php foreach([
                ['all',       '🏷️ Tất cả ưu đãi'],
                ['weekend',   '🌅 Cuối tuần'],
                ['discount',  '💥 Giảm ≥ 20%'],
                ['top_rated', '⭐ Đánh giá 9+'],
            ] as [$key, $label]): ?>
                <a href="?filter=<?= $key ?>&sort=<?= $sort ?>"
                   class="deal-tab <?= $filter===$key?'active':'' ?>">
                    <?= $label ?>
                    <?php if($key==='all'): ?>
                        <span class="tab-num"><?= $total ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="deal-sort">
            <select onchange="location.href='?filter=<?= $filter ?>&sort='+this.value" class="sort-select">
                <option value="discount_desc" <?= $sort==='discount_desc'?'selected':'' ?>>Giảm nhiều nhất</option>
                <option value="price_asc"     <?= $sort==='price_asc'?'selected':'' ?>>Giá tăng dần</option>
                <option value="price_desc"    <?= $sort==='price_desc'?'selected':'' ?>>Giá giảm dần</option>
                <option value="rating"        <?= $sort==='rating'?'selected':'' ?>>Đánh giá cao nhất</option>
            </select>
        </div>
    </div>

    <!-- HOTEL GRID -->
    <?php if(empty($hotels)): ?>
        <div class="deals-empty">
            <div style="font-size:48px">🔍</div>
            <p>Không tìm thấy ưu đãi phù hợp</p>
            <a href="deals.php" class="deal-tab active" style="margin-top:12px">Xem tất cả</a>
        </div>
    <?php else: ?>
    <div class="deals-grid">
    <?php foreach($hotels as $h):
        $discount = $h['discount_pct'];
        $min_p    = $h['min_room_price'];
        $saved    = $h['old_price'] - $min_p;
        $rl       = $rating_label($h['rating']);
        $is_hot   = $discount >= 20;
        $is_new   = ($h['is_weekend_deal'] ?? 0);
    ?>
        <div class="deal-card-v2 <?= $is_hot ? 'hot' : '' ?>">

            <div class="dcv2-img">
                <img src="/tour_khach_san_project/assets/images/<?= htmlspecialchars($h['image'] ?? '') ?>"
                    alt="<?= htmlspecialchars($h['name']) ?>"
                    loading="lazy"
                    onerror="this.style.background='#e2e8f0'">
                <div class="dcv2-badges">
                    <span class="badge-discount">-<?= $discount ?>%</span>
                    <?php if($is_hot): ?><span class="badge-hot">🔥 HOT</span><?php endif; ?>
                    <?php if($is_new): ?><span class="badge-weekend">🌅 Cuối tuần</span><?php endif; ?>
                </div>
                <div class="dcv2-save-ribbon">
                    Tiết kiệm <?= number_format($saved, 0, ',', '.') ?>đ
                </div>
            </div>

            <div class="dcv2-body">
                <div class="dcv2-location">📍 <?= htmlspecialchars($h['location_name'] ?? '') ?></div>
                <h3 class="dcv2-name"><?= htmlspecialchars($h['name']) ?></h3>
                <p class="dcv2-desc"><?= htmlspecialchars(mb_substr($h['description'] ?? '', 0, 80)) ?>...</p>
                <div class="dcv2-rating">
                    <span class="dcv2-score"><?= number_format($h['rating'], 1) ?></span>
                    <span class="dcv2-rlabel"><?= $rl ?></span>
                    <span class="dcv2-rcount"><?= number_format($h['review_count'] ?? 0) ?> đánh giá</span>
                </div>
            </div>

            <div class="dcv2-footer">
                <div class="dcv2-price-wrap">
                    <span class="dcv2-old"><?= number_format($h['old_price'], 0, ',', '.') ?>đ</span>
                    <div class="dcv2-new">
                        <?= number_format($min_p, 0, ',', '.') ?>đ
                        <span class="dcv2-per">/đêm</span>
                    </div>
                </div>
                <div class="dcv2-btn-group">
                    <a href="/tour_khach_san_project/pages/hotel_detail.php?id=<?= $h['id'] ?>"
                       class="dcv2-btn-detail">🔍 Xem chi tiết</a>
                    <!-- Gọi bookNow() để kiểm tra đăng nhập trước khi đặt -->
                    <a href="javascript:void(0)"
                       onclick="bookNow('/tour_khach_san_project/pages/payment.php?hotel_id=<?= $h['id'] ?>')"
                       class="dcv2-btn">Đặt ngay</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- CTA Banner -->
    <div class="deals-cta">
        <div class="deals-cta-text">
            <h3>🏨 Không bỏ lỡ ưu đãi nào!</h3>
            <p>Khám phá toàn bộ danh sách khách sạn để tìm lựa chọn hoàn hảo cho bạn</p>
        </div>
        <a href="/tour_khach_san_project/pages/hotels.php" class="deals-cta-btn">
            Xem tất cả khách sạn →
        </a>
    </div>

</div>

<!-- JS bookNow - dùng chung cho deals.php -->
<script>
const isLoggedIn = <?= $is_logged_in ?>;

function bookNow(url) {
    if (isLoggedIn) {
        window.location.href = url;
    } else {
        if (confirm('Bạn cần đăng nhập để đặt phòng.\nĐăng nhập ngay?')) {
            window.location.href = '/tour_khach_san_project/auth/login.php';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>