<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$keyword   = trim($_GET['keyword']   ?? '');
$checkin   = $_GET['checkin']        ?? '';
$checkout  = $_GET['checkout']       ?? '';
$min_price = $_GET['min_price']      ?? '';
$max_price = $_GET['max_price']      ?? '';
$rating    = $_GET['rating']         ?? '';
$sort      = $_GET['sort']           ?? 'default';

// Nhận location_id từ home.php khi click địa điểm
$location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

// Lấy thông tin vùng đang lọc để hiển thị tiêu đề
$current_location = null;
if ($location_id > 0) {
    $loc_stmt = $conn->prepare("SELECT * FROM locations WHERE id = ?");
    $loc_stmt->bind_param("i", $location_id);
    $loc_stmt->execute();
    $current_location = $loc_stmt->get_result()->fetch_assoc();
}

// Lấy tất cả locations cho tab lọc nhanh
$all_locations = $conn->query("
    SELECT l.*, COUNT(h.id) AS hotel_count
    FROM locations l
    LEFT JOIN hotels h ON h.location_id = l.id AND h.is_active = 1
    GROUP BY l.id ORDER BY l.name ASC
")->fetch_all(MYSQLI_ASSOC);

$nights = 1;
if ($checkin && $checkout) {
    $n = intval((strtotime($checkout) - strtotime($checkin)) / 86400);
    if ($n > 0) $nights = $n;
}

$where  = ["h.is_active = 1"];
$params = [$nights];
$types  = "i";

if ($keyword) {
    $where[] = "(h.name LIKE ? OR h.address LIKE ? OR h.description LIKE ?)";
    $kw = "%$keyword%";
    array_push($params, $kw, $kw, $kw);
    $types .= "sss";
}
if ($rating) {
    $where[]  = "h.rating >= ?";
    $params[] = floatval($rating);
    $types   .= "d";
}

// Lọc theo location_id
if ($location_id > 0) {
    $where[]  = "h.location_id = ?";
    $params[] = $location_id;
    $types   .= "i";
}

$having = [];
if ($min_price !== '') {
    $having[] = "min_room_price >= ?";
    $params[] = intval($min_price);
    $types   .= "i";
}
if ($max_price !== '') {
    $having[] = "min_room_price <= ?";
    $params[] = intval($max_price);
    $types   .= "i";
}

$order = match($sort) {
    'price_asc'  => "min_room_price ASC",
    'price_desc' => "min_room_price DESC",
    'rating'     => "h.rating DESC",
    'popular'    => "h.review_count DESC",
    default      => "h.created_at DESC",
};

$where_sql  = implode(' AND ', $where);
$having_sql = $having ? "HAVING " . implode(' AND ', $having) : "";

$sql = "
    SELECT h.id, h.name, h.address, h.description, h.image,
        h.price, h.old_price, h.rating, h.review_count, h.is_weekend_deal,
        COALESCE(MIN(r.price), h.price) AS min_room_price,
        COALESCE(MIN(r.price), h.price) * ? AS total_price
    FROM hotels h
    LEFT JOIN rooms r ON r.hotel_id = h.id
    WHERE $where_sql
    GROUP BY h.id
    $having_sql
    ORDER BY $order
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$hotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total  = count($hotels);

$rating_label = function($r) {
    if ($r >= 9)   return ['Tuyệt vời', '#16a34a'];
    if ($r >= 8)   return ['Rất tốt',   '#0071c2'];
    if ($r >= 7)   return ['Tốt',        '#2d9cdb'];
    return ['Bình thường', '#718096'];
};
?>

<div class="hotel-wrapper">
<div class="container">

    <!-- PAGE HERO -->
    <div class="page-hero">
        <?php if ($current_location): ?>
            <!-- Breadcrumb + tiêu đề động theo vùng -->
            <div class="loc-breadcrumb">
                <a href="/tour_khach_san_project/pages/hotels.php">📍 Tất cả khách sạn</a>
                <span>›</span>
                <span><?= htmlspecialchars($current_location['name']) ?></span>
            </div>
            <h2 class="page-title">🏨 Khách sạn tại <?= htmlspecialchars($current_location['name']) ?></h2>
            <p class="page-subtitle"><?= htmlspecialchars($current_location['description']) ?></p>
        <?php else: ?>
            <h2 class="page-title">🏨 Danh sách khách sạn</h2>
            <p class="page-subtitle">Khám phá <?= $total ?> khách sạn tuyệt vời đang chờ bạn</p>
        <?php endif; ?>
    </div>

    <!-- Tabs lọc nhanh theo địa điểm -->
    <div class="loc-tabs-wrap">
        <div class="loc-tabs">
            <a href="/tour_khach_san_project/pages/hotels.php?sort=<?= urlencode($sort) ?>"
            class="loc-tab <?= $location_id === 0 ? 'active' : '' ?>">
                🗺️ Tất cả
            </a>
            <?php foreach ($all_locations as $loc): ?>
                <a href="/tour_khach_san_project/pages/hotels.php?location_id=<?= $loc['id'] ?>&sort=<?= urlencode($sort) ?>"
                class="loc-tab <?= $location_id === (int)$loc['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($loc['name']) ?>
                    <span class="loc-tab-count"><?= $loc['hotel_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- BỘ LỌC -->
    <div class="sf-wrapper">
    <form method="GET" action="" id="searchForm">

        <!-- Giữ location_id khi submit form -->
        <?php if ($location_id > 0): ?>
            <input type="hidden" name="location_id" value="<?= $location_id ?>">
        <?php endif; ?>

        <div class="sf-main-row">
            <div class="sf-field sf-keyword">
                <span class="sf-icon">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </span>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tên khách sạn, địa điểm...">
            </div>
            <div class="sf-field">
                <span class="sf-icon">📅</span>
                <input type="date" name="checkin" id="sf_checkin" value="<?= htmlspecialchars($checkin) ?>" onchange="calcNights()">
                <span class="sf-date-label">Nhận phòng</span>
            </div>
            <div class="sf-field">
                <span class="sf-icon">📅</span>
                <input type="date" name="checkout" id="sf_checkout" value="<?= htmlspecialchars($checkout) ?>" onchange="calcNights()">
                <span class="sf-date-label">Trả phòng</span>
            </div>
            <div class="sf-nights-badge" id="nightsBadge" style="<?= ($checkin && $checkout) ? '' : 'display:none' ?>">
                🌙 <span id="nightsText"><?= $nights ?> đêm</span>
            </div>
            <button type="submit" class="sf-search-btn">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                Tìm kiếm
            </button>
        </div>

        <div class="sf-filters-row">
            <div class="sf-filter-group">
                <div class="sf-filter-label">💰 Giá/đêm (VNĐ)</div>
                <div class="sf-price-inputs">
                    <input type="number" name="min_price" placeholder="Từ" min="0" step="50000" value="<?= htmlspecialchars($min_price) ?>">
                    <span>–</span>
                    <input type="number" name="max_price" placeholder="Đến" min="0" step="50000" value="<?= htmlspecialchars($max_price) ?>">
                </div>
                <div class="sf-price-presets">
                    <button type="button" class="sf-preset <?= ($min_price==='' && $max_price==='500000')?'active':'' ?>" onclick="setPrice(0,500000)">Dưới 500k</button>
                    <button type="button" class="sf-preset <?= ($min_price==='500000' && $max_price==='1000000')?'active':'' ?>" onclick="setPrice(500000,1000000)">500k–1tr</button>
                    <button type="button" class="sf-preset <?= ($min_price==='1000000' && $max_price==='2000000')?'active':'' ?>" onclick="setPrice(1000000,2000000)">1tr–2tr</button>
                    <button type="button" class="sf-preset <?= ($min_price==='2000000' && $max_price==='')?'active':'' ?>" onclick="setPrice(2000000,'')">Trên 2tr</button>
                </div>
            </div>

            <div class="sf-filter-group">
                <div class="sf-filter-label">⭐ Đánh giá</div>
                <div class="sf-rating-opts">
                    <?php foreach([['','Tất cả'],['7','7+ Tốt'],['8','8+ Rất tốt'],['9','9+ Tuyệt vời']] as [$val,$lbl]): ?>
                        <label class="sf-rating-opt <?= $rating===$val?'active':'' ?>">
                            <input type="radio" name="rating" value="<?= $val ?>" <?= $rating===$val?'checked':'' ?> onchange="this.form.submit()">
                            <?= $lbl ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sf-filter-group">
                <div class="sf-filter-label">↕ Sắp xếp</div>
                <select name="sort" onchange="this.form.submit()" class="sf-select">
                    <option value="default"    <?= $sort==='default'   ?'selected':'' ?>>Mặc định</option>
                    <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Giá tăng dần</option>
                    <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Giá giảm dần</option>
                    <option value="rating"     <?= $sort==='rating'    ?'selected':'' ?>>Đánh giá cao nhất</option>
                    <option value="popular"    <?= $sort==='popular'   ?'selected':'' ?>>Phổ biến nhất</option>
                </select>
            </div>

            <!-- Giữ location_id khi xóa bộ lọc -->
            <?php if($keyword||$checkin||$checkout||$min_price||$max_price||$rating||$sort!=='default'): ?>
            <a href="hotels.php<?= $location_id > 0 ? '?location_id='.$location_id : '' ?>" class="sf-reset-btn">✕ Xóa bộ lọc</a>
            <?php endif; ?>
        </div>
    </form>
    </div>

    <!-- Result bar hiển thị tên vùng -->
    <?php if($keyword || $min_price || $max_price || $rating || $checkin || $location_id > 0): ?>
    <div class="result-bar">
        <span>Tìm thấy <strong><?= $total ?></strong> khách sạn
            <?php if ($current_location): ?>
                tại <strong><?= htmlspecialchars($current_location['name']) ?></strong>
            <?php endif; ?>
        </span>
        <?php if($checkin && $checkout): ?>
            <span class="result-nights">📅 <?= date('d/m', strtotime($checkin)) ?> → <?= date('d/m/Y', strtotime($checkout)) ?> · <strong><?= $nights ?> đêm</strong></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- DANH SÁCH -->
    <?php if(empty($hotels)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <p>Không tìm thấy khách sạn phù hợp</p>
            <a href="hotels.php<?= $location_id > 0 ? '?location_id='.$location_id : '' ?>" class="sf-reset-btn" style="margin-top:14px;display:inline-flex">Xóa bộ lọc</a>
        </div>
    <?php else: ?>
        <div class="hotels-grid">
        <?php foreach($hotels as $row):
            $min_p   = $row['min_room_price'] ?? $row['price'];
            $old_p   = $row['old_price'] ?? 0;
            $total_p = $row['total_price'] ?? ($min_p * $nights);
            $discount = ($old_p > $min_p) ? round((1 - $min_p/$old_p)*100) : 0;
            [$rl_text, $rl_color] = $rating_label($row['rating']);
            $detail_url = "hotel_detail.php?id={$row['id']}" . ($checkin?"&checkin=$checkin":"") . ($checkout?"&checkout=$checkout":"");
        ?>
        <div class="hotel-card" onclick="window.location='<?= $detail_url ?>'" style="cursor:pointer">

            <div class="hotel-img">
                <img src="../assets/images/<?= htmlspecialchars($row['image'] ?? '') ?>"
                    alt="<?= htmlspecialchars($row['name']) ?>"
                    loading="lazy"
                    onerror="this.src='../assets/images/placeholder.jpg';this.onerror=null;">
                <?php if($discount > 0): ?>
                    <span class="discount-badge">-<?= $discount ?>%</span>
                <?php endif; ?>
                <?php if($row['is_weekend_deal'] ?? 0): ?>
                    <span class="deal-badge">🔥 Deal</span>
                <?php endif; ?>
                <div class="img-overlay">
                    <span>Xem chi tiết →</span>
                </div>
            </div>

            <div class="hotel-info">
                <h3><?= htmlspecialchars($row['name']) ?></h3>
                <p class="location">📍 <?= htmlspecialchars($row['address']) ?></p>
                <p class="desc"><?= htmlspecialchars(mb_substr($row['description'] ?? '', 0, 90)) ?>...</p>

                <div class="quick-amenities">
                    <span class="amenity-tag">📶 WiFi</span>
                    <span class="amenity-tag">🅿️ Đỗ xe</span>
                    <span class="amenity-tag">❄️ Điều hòa</span>
                    <span class="amenity-tag">🍳 Bữa sáng</span>
                </div>

                <div class="review-row">
                    <span class="rating-box"><?= number_format($row['rating'], 1) ?></span>
                    <span class="rating-label" style="color:<?= $rl_color ?>"><?= $rl_text ?></span>
                    <span class="review-count">· <?= number_format($row['review_count'] ?? 0) ?> đánh giá</span>
                </div>
            </div>

            <div class="hotel-price">
                <?php if($old_p > $min_p): ?>
                    <p class="old-price"><?= number_format($old_p, 0, ',', '.') ?>đ</p>
                <?php endif; ?>
                <?php if($discount > 0): ?>
                    <span class="save-badge">Tiết kiệm <?= $discount ?>%</span>
                <?php endif; ?>
                <p class="price"><?= number_format($min_p, 0, ',', '.') ?>đ<span class="per-night">/đêm</span></p>
                <?php if($nights > 1): ?>
                    <p class="total-price"><?= $nights ?> đêm: <strong><?= number_format($total_p, 0, ',', '.') ?>đ</strong></p>
                <?php endif; ?>
                <p class="price-note">✓ Đã gồm thuế & phí</p>

                <a href="<?= $detail_url ?>" class="btn-detail" onclick="event.stopPropagation()">
                    🔍 Xem chi tiết
                </a>
                <a href="payment.php?hotel_id=<?= $row['id'] ?><?= $checkin?'&checkin='.$checkin:'' ?><?= $checkout?'&checkout='.$checkout:'' ?>"
                class="btn-book" onclick="event.stopPropagation()">
                    Đặt ngay
                </a>
            </div>

        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>