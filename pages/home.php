<?php
// home.php - không cần kiểm tra đăng nhập, ai cũng đặt được từ trang chủ
?>

<!-- ----------------------------------------
    ƯU ĐÃI BANNER
---------------------------------------- -->
<section class="promo-wrapper">
    <div class="container">
        <h2 class="promo-title">Ưu đãi</h2>
        <p class="promo-desc">Khuyến mãi, giảm giá và ưu đãi đặc biệt dành riêng cho bạn</p>
        <div class="promo-card">
            <div class="promo-left">
                <span class="promo-small">Ưu Đãi Đầu Năm 2026</span>
                <h3>Giảm giá ít nhất 10%</h3>
                <p>Tiết kiệm cho đặt lưu trú tiếp theo với Ưu Đãi Đầu Năm 2026. Đặt ngay, lưu trú theo ý muốn đến 1/4/2026.</p>
                <a href="/pages/deals.php" class="promo-button">Khám phá ưu đãi</a>
            </div>
            <div class="promo-right">
                <img src="/assets/images/promo.jpg" alt="Khuyến mãi">
            </div>
        </div>
    </div>
</section>

<!-- ----------------------------------------
    ĐỊA ĐIỂM ĐẾN
---------------------------------------- -->
<section class="destinations">
    <div class="container">
        <h1>Địa điểm đến đang thịnh hành tại tỉnh Quảng Ngãi</h1>
        <div class="destination-grid">
            <?php
            $result = $conn->query("
                SELECT l.*, COUNT(h.id) AS hotel_count
                FROM locations l
                LEFT JOIN hotels h ON h.location_id = l.id AND h.is_active = 1
                GROUP BY l.id
                ORDER BY hotel_count DESC
            ");
            while ($row = $result->fetch_assoc()): ?>
                <!-- Click vào card → mở trang khách sạn lọc theo vùng -->
                <a href="/pages/hotels.php?location_id=<?= $row['id'] ?>"
                class="destination-card"
                style="background-image: url('/assets/images/<?= htmlspecialchars($row['image']) ?>');">
                    <div class="destination-overlay">
                        <span class="dest-hotel-count">🏨 <?= $row['hotel_count'] ?> khách sạn</span>
                        <h3><?= htmlspecialchars($row['name']) ?></h3>
                        <p><?= htmlspecialchars($row['description']) ?></p>
                        <span class="dest-explore">Xem ngay →</span>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- ----------------------------------------
    ƯU ĐÃI CUỐI TUẦN
---------------------------------------- -->
<section class="weekend-deals">
    <div class="container">
        <h2>Ưu đãi cho cuối tuần</h2>
        <p class="muted-text">Những ưu đãi đặc biệt chỉ có vào cuối tuần</p>

        <?php
        $result = $conn->query("
            SELECT h.*, l.name AS location_name
            FROM hotels h JOIN locations l ON h.location_id = l.id
            WHERE h.is_weekend_deal = 1 AND h.is_active = 1
            ORDER BY h.rating DESC LIMIT 3
        ");
        ?>

        <div class="deal-grid">
        <?php while ($row = $result->fetch_assoc()):
            $discount = 0;
            if (!empty($row['old_price']) && $row['old_price'] > $row['price'])
                $discount = round((($row['old_price'] - $row['price']) / $row['old_price']) * 100);
        ?>
            <div class="deal-card">
                <div class="img-wrap">
                    <?php if ($discount > 0): ?>
                        <span class="discount-badge">-<?= $discount ?>%</span>
                    <?php endif; ?>
                    <span class="deal-badge">🔥 Deal</span>
                    <img src="/assets/images/<?= htmlspecialchars($row['image']) ?>" alt="" loading="lazy">
                </div>
                <div class="deal-body">
                    <div class="deal-location">📍 <?= htmlspecialchars($row['location_name']) ?>, Việt Nam</div>
                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                    <?php if (!empty($row['rating']) && $row['rating'] > 0): ?>
                        <div class="deal-rating">
                            <span class="score"><?= number_format($row['rating'],1) ?></span>
                            <strong><?= htmlspecialchars($row['review_text'] ?? '') ?></strong>
                            <span class="reviews"><?= $row['review_count'] ?> đánh giá</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="deal-price">
                    <div class="price-left">
                        <?php if ($row['old_price'] > $row['price']): ?>
                            <span class="old"><?= number_format($row['old_price'],0,',','.') ?>đ</span>
                        <?php endif; ?>
                        <span class="new"><?= number_format($row['price'],0,',','.') ?>đ</span>
                        <span class="per">/đêm</span>
                    </div>
                    <div class="card-actions">
                        <a href="/pages/hotel_detail.php?id=<?= $row['id'] ?>"
                        class="btn-outline">🔍 Xem chi tiết</a>
                        <a href="/pages/payment.php?hotel_id=<?= $row['id'] ?>"
                        class="btn-deal">Đặt ngay</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- ----------------------------------------
    KHÁCH SẠN NỔI BẬT
---------------------------------------- -->
<section class="featured-hotels">
    <div class="container">
        <div class="fh-header">
            <div>
                <h2 class="fh-title">Khách sạn nổi bật</h2>
                <p class="fh-sub">Những lựa chọn được yêu thích nhất</p>
            </div>
            <a href="/pages/hotels.php" class="fh-view-all">
                Xem tất cả
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
            </a>
        </div>

        <?php
        $res_feat = $conn->query("
            SELECT h.*, l.name AS location_name,
                COALESCE(MIN(r.price), h.price) AS min_room_price
            FROM hotels h JOIN locations l ON h.location_id = l.id
            LEFT JOIN rooms r ON r.hotel_id = h.id
            WHERE h.is_active = 1
            GROUP BY h.id ORDER BY h.rating DESC, h.review_count DESC LIMIT 3
        ");
        ?>

        <div class="fh-grid">
        <?php while ($f = $res_feat->fetch_assoc()):
            $discount_f = ($f['old_price'] > $f['min_room_price'])
                ? round((1 - $f['min_room_price'] / $f['old_price']) * 100) : 0;
            $rating_lbl = match(true) {
                $f['rating'] >= 9 => 'Tuyệt vời',
                $f['rating'] >= 8 => 'Rất tốt',
                $f['rating'] >= 7 => 'Tốt',
                default           => 'Bình thường'
            };
        ?>
            <div class="fh-card">
                <div class="fh-img-wrap">
                    <img src="/assets/images/<?= htmlspecialchars($f['image'] ?? '') ?>"
                        alt="<?= htmlspecialchars($f['name']) ?>"
                        loading="lazy"
                        onerror="this.style.background='#e2e8f0'">
                    <?php if ($discount_f > 0): ?>
                        <span class="fh-discount">-<?= $discount_f ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="fh-body">
                    <div class="fh-location">📍 <?= htmlspecialchars($f['location_name']) ?></div>
                    <h3 class="fh-name"><?= htmlspecialchars($f['name']) ?></h3>
                    <p class="fh-desc"><?= htmlspecialchars(mb_substr($f['description'] ?? '', 0, 70)) ?>...</p>
                    <div class="fh-rating">
                        <span class="fh-score"><?= number_format($f['rating'], 1) ?></span>
                        <span class="fh-rlabel"><?= $rating_lbl ?></span>
                        <span class="fh-rcount"><?= number_format($f['review_count'] ?? 0) ?> đánh giá</span>
                    </div>
                </div>
                <div class="fh-footer">
                    <div class="fh-price-wrap">
                        <?php if ($f['old_price'] > $f['min_room_price']): ?>
                            <span class="fh-old"><?= number_format($f['old_price'], 0, ',', '.') ?>đ</span>
                        <?php endif; ?>
                        <span class="fh-price"><?= number_format($f['min_room_price'], 0, ',', '.') ?>đ</span>
                        <span class="fh-per">/đêm</span>
                    </div>
                    <div class="card-actions">
                        <a href="/pages/hotel_detail.php?id=<?= $f['id'] ?>"
                        class="btn-outline">🔍 Xem chi tiết</a>
                        <a href="/pages/payment.php?hotel_id=<?= $f['id'] ?>"
                        class="fh-book-btn">Đặt ngay</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- ----------------------------------------
    BLOG DU LỊCH
---------------------------------------- -->
<?php include 'pages/blog-section.php'; ?>

<!-- ĐỊA ĐIỂM THAM QUAN NỔI BẬT: tạm ẩn, dữ liệu vẫn còn trong DB -->