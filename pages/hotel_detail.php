<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id       = (int)($_GET['id']      ?? 0);
$checkin  = $_GET['checkin']       ?? '';
$checkout = $_GET['checkout']      ?? '';
$nights   = 1;
if ($checkin && $checkout) {
    $n = intval((strtotime($checkout) - strtotime($checkin)) / 86400);
    if ($n > 0) $nights = $n;
}

if (!$id) { header("Location: hotels.php"); exit; }

// Khách sạn
$stmt = $conn->prepare("
    SELECT h.*, l.name AS location_name
    FROM hotels h
    LEFT JOIN locations l ON h.location_id = l.id
    WHERE h.id = ? AND h.is_active = 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$hotel = $stmt->get_result()->fetch_assoc();

if (!$hotel) { header("Location: hotels.php"); exit; }

// Kiểm tra đã yêu thích chưa
$is_favorite = false;
if (isset($_SESSION['user_id'])) {
    $fav_chk = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND hotel_id = ?");
    $fav_uid = (int) $_SESSION['user_id'];
    $fav_chk->bind_param("ii", $fav_uid, $id);
    $fav_chk->execute();
    $is_favorite = (bool) $fav_chk->get_result()->fetch_assoc();
}

// Bài viết cùng vùng
$loc_name_escaped = $conn->real_escape_string($hotel['location_name']);
$blogs_res    = $conn->query("
    SELECT id, title, thumb, DATE_FORMAT(created_at, '%d/%m/%Y') AS date
    FROM blog_posts
    WHERE (category LIKE '%$loc_name_escaped%' OR tags LIKE '%$loc_name_escaped%')
    AND is_active = 1
    ORDER BY created_at DESC
    LIMIT 3
");
$blogs_nearby = $blogs_res ? $blogs_res->fetch_all(MYSQLI_ASSOC) : [];

// Phòng
$rooms_stmt = $conn->prepare("SELECT * FROM rooms WHERE hotel_id = ? ORDER BY price ASC");
$rooms_stmt->bind_param("i", $id);
$rooms_stmt->execute();
$rooms = $rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Gallery từ bảng hotel_images
$gi_stmt = $conn->prepare("
    SELECT image, caption FROM hotel_images
    WHERE hotel_id = ?
    ORDER BY sort_order ASC, id ASC
");
$gi_stmt->bind_param("i", $id);
$gi_stmt->execute();
$gallery_rows = $gi_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Gộp: ảnh chính + ảnh gallery
$all_photos = [];
$all_photos[] = [
    'src'     => '../assets/images/' . ($hotel['image'] ?? ''),
    'caption' => $hotel['name'],
];
foreach ($gallery_rows as $gi) {
    $all_photos[] = [
        'src'     => '../assets/images/' . $gi['image'],
        'caption' => $gi['caption'] ?? '',
    ];
}
$total_photos = count($all_photos);

$min_room_price = !empty($rooms) ? min(array_column($rooms, 'price')) : $hotel['price'];
$max_room_price = !empty($rooms) ? max(array_column($rooms, 'price')) : $hotel['price'];

$rating_info = match(true) {
    $hotel['rating'] >= 9.5 => ['Trên cả tuyệt vời', '#15803d'],
    $hotel['rating'] >= 9   => ['Tuyệt vời',          '#16a34a'],
    $hotel['rating'] >= 8   => ['Rất tốt',             '#0071c2'],
    $hotel['rating'] >= 7   => ['Tốt',                 '#2d9cdb'],
    $hotel['rating'] >= 5   => ['Khá',                 '#d97706'],
    default                 => ['Bình thường',          '#718096'],
};
[$rating_lbl, $rating_color] = $rating_info;

// Cấu hình phòng map theo room_name từ DB
$room_config = [
    'Phòng Tiêu Chuẩn' => ['icon' => '🛏️', 'size' => '20m²', 'tag' => 'Tiêu chuẩn'],
    'Phòng Cao Cấp'    => ['icon' => '⭐',  'size' => '25m²', 'tag' => 'Cao cấp'],
    'Phòng Hạng Sang'  => ['icon' => '👑',  'size' => '35m²', 'tag' => 'Hạng sang'],
];

// Helper: đếm số khách từ bed_type
function guests_from_bed(string $bed): int {
    $b = mb_strtolower($bed);
    if (str_contains($b, 'sofa'))          return 4;
    if (str_contains($b, '2 giường đơn')) return 2;
    if (str_contains($b, '2 giường đôi')) return 4;
    if (str_contains($b, '2 giường lớn')) return 4;
    if (str_contains($b, '2 giường'))     return 4;
    if (str_contains($b, 'lớn'))          return 2;
    if (str_contains($b, 'đôi'))          return 2;
    if (str_contains($b, 'đơn'))          return 1;
    return 2;
}

// Helper: icon giường từ bed_type
function bed_icon(string $bed): string {
    $b = mb_strtolower($bed);
    if (str_contains($b, '2 giường')) return '🛏️🛏️';
    if (str_contains($b, 'lớn'))      return '🛏️';
    if (str_contains($b, 'đôi'))      return '🛏️';
    if (str_contains($b, 'đơn'))      return '🛌';
    return '🛏️';
}
?>

<!-- STICKY NAV TABS -->
<div class="hd-nav-bar" id="hdNavBar">
    <div class="hd-nav-container">
        <div class="hd-nav-tabs">
            <a href="#overview"  class="hd-nav-tab active">Tổng quan</a>
            <a href="#amenities" class="hd-nav-tab">Tiện nghi</a>
            <a href="#rooms"     class="hd-nav-tab">Phòng trống</a>
            <a href="#rules"     class="hd-nav-tab">Quy tắc</a>
            <a href="#reviews"   class="hd-nav-tab">Đánh giá</a>
        </div>
        <a href="#rooms" class="hd-nav-book">Đặt ngay →</a>
    </div>
</div>

<div class="hd-wrapper" id="overview">
<div class="hd-cont">

    <!-- HEADER -->
    <div class="hd-header">
        <div class="hd-title-wrap">
            <div class="hd-badges-row">
                <?php if($hotel['is_weekend_deal']): ?>
                    <span class="hd-tag-deal">🔥 Deal cuối tuần</span>
                <?php endif; ?>
                <?php if($discount > 0): ?>
                    <span class="hd-tag-discount">💰 Giảm <?= $discount ?>%</span>
                <?php endif; ?>
            </div>
            <h1 class="hd-name"><?= htmlspecialchars($hotel['name']) ?></h1>
            <div class="hd-sub-row">
                <span class="hd-loc">📍 <?= htmlspecialchars($hotel['address']) ?></span>
                <div class="hd-score-wrap">
                    <span class="hd-score-box"><?= number_format($hotel['rating'], 1) ?></span>
                    <span class="hd-score-lbl" style="color:<?= $rating_color ?>"><?= $rating_lbl ?></span>
                    <span class="hd-review-n"><?= number_format($hotel['review_count'] ?? 0) ?> đánh giá</span>
                </div>
            </div>
        </div>
        <div class="hd-header-cta">
            <button class="hd-fav-btn <?= $is_favorite ? 'hd-fav-active' : '' ?>"
                    id="hdFavBtn"
                    onclick="toggleFavorite(<?= $id ?>)"
                    title="<?= $is_favorite ? 'Bỏ yêu thích' : 'Lưu yêu thích' ?>">
                <svg id="hdFavIcon" width="20" height="20"
                     fill="<?= $is_favorite ? '#e53e3e' : 'none' ?>"
                     stroke="<?= $is_favorite ? '#e53e3e' : '#4a5568' ?>"
                     stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
                <span id="hdFavLabel"><?= $is_favorite ? 'Đã lưu' : 'Lưu yêu thích' ?></span>
            </button>
            <a href="#rooms" class="hd-cta-btn">Xem phòng & Đặt ngay</a>
        </div>
    </div>

    <!-- GALLERY -->
    <div class="hd-gallery">
        <div class="hd-gallery-main" onclick="openGallery(0)">
            <img id="hd-main-img"
                src="<?= htmlspecialchars($all_photos[0]['src']) ?>"
                alt="<?= htmlspecialchars($hotel['name']) ?>"
                onerror="this.src='../assets/images/placeholder.jpg'">
            <?php if($discount > 0): ?>
                <span class="hd-gallery-disc">-<?= $discount ?>%</span>
            <?php endif; ?>
            <?php if($total_photos > 1): ?>
                <div class="hd-gallery-count-badge">📷 <?= $total_photos ?> ảnh</div>
            <?php endif; ?>
        </div>

        <div class="hd-gallery-grid">
            <?php for ($i = 1; $i <= 4; $i++):
                $photo = $all_photos[$i] ?? $all_photos[0];
                $is_last = ($i === 4 && $total_photos > 5);
            ?>
            <div class="hd-thumb <?= $i===1?'hd-thumb-active':'' ?> <?= $is_last?'hd-thumb-more':'' ?>"
                onclick="switchMain(this,'<?= htmlspecialchars($photo['src'],ENT_QUOTES) ?>',<?= $i ?>)">
                <img src="<?= htmlspecialchars($photo['src']) ?>"
                    alt="Ảnh <?= $i ?>"
                    loading="lazy"
                    onerror="this.src='../assets/images/placeholder.jpg'">
                <?php if($is_last): ?>
                    <div class="hd-thumb-overlay">+<?= $total_photos - 4 ?> ảnh</div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div class="hd-lightbox" id="hdLightbox">
        <div class="hd-lb-backdrop" onclick="closeGallery()"></div>
        <div class="hd-lb-inner">
            <button class="hd-lb-close"  onclick="closeGallery()">✕</button>
            <button class="hd-lb-prev"   onclick="lbNav(-1)">‹</button>
            <button class="hd-lb-next"   onclick="lbNav(1)">›</button>
            <div class="hd-lb-img-wrap">
                <img id="hdLbImg" src="" alt="">
            </div>
            <div class="hd-lb-info">
                <div class="hd-lb-caption" id="hdLbCaption"></div>
                <div class="hd-lb-counter" id="hdLbCounter"></div>
            </div>
            <div class="hd-lb-strip" id="hdLbStrip">
                <?php foreach($all_photos as $k => $ph): ?>
                <img src="<?= htmlspecialchars($ph['src']) ?>"
                    class="hd-lb-tn"
                    data-index="<?= $k ?>"
                    onclick="openGallery(<?= $k ?>)"
                    loading="lazy"
                    onerror="this.style.display='none'"
                    alt="">
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="hd-grid">

        <!-- CỘT TRÁI -->
        <div class="hd-left">

            <!-- Giới thiệu -->
            <div class="hd-card" id="about">
                <h2 class="hd-card-title">📖 Giới thiệu</h2>
                <p class="hd-desc"><?= nl2br(htmlspecialchars($hotel['description'] ?? '')) ?></p>
            </div>

            <!-- Tiện nghi -->
            <div class="hd-card" id="amenities">
                <h2 class="hd-card-title">⭐ Các tiện nghi được ưa chuộng nhất</h2>
                <div class="hd-amenities-grid">
                    <?php foreach([
                        ['📶','WiFi miễn phí',        'Toàn bộ khách sạn'],
                        ['🚗','Chỗ đậu xe miễn phí',  'Trong khuôn viên'],
                        ['❄️','Điều hòa không khí',   'Tất cả các phòng'],
                        ['🍳','Bữa sáng kiểu Á',      'Phục vụ hàng ngày'],
                        ['🛎️','Lễ tân 24/7',          'Hỗ trợ mọi lúc'],
                        ['🚿','Phòng tắm riêng',       'Trong từng phòng'],
                        ['📺','TV màn hình phẳng',     'Tất cả các phòng'],
                        ['🧹','Dọn phòng hàng ngày',  'Dịch vụ đầy đủ'],
                        ['🚭','Không hút thuốc',       'Toàn bộ khu vực'],
                        ['🐾','Cho phép vật nuôi',     'Theo yêu cầu'],
                        ['🚐','Đưa đón sân bay',       'Miễn phí theo yêu cầu'],
                        ['🏊','Hồ bơi',                'Mở cửa 6:00–22:00'],
                    ] as [$icon,$name,$note]): ?>
                    <div class="hd-amenity-item">
                        <span class="hd-amen-icon"><?= $icon ?></span>
                        <div>
                            <div class="hd-amen-name"><?= $name ?></div>
                            <div class="hd-amen-note"><?= $note ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Phòng trống -->
            <div class="hd-card" id="rooms">
                <div class="hd-rooms-header-row">
                    <h2 class="hd-card-title" style="margin:0">🛏️ Phòng trống</h2>
                </div>

                <div class="hd-table">
                    <div class="hd-table-head">
                        <div class="hd-th hd-th-room">Loại phòng</div>
                        <div class="hd-th hd-th-guest">Khách</div>
                        <div class="hd-th hd-th-price">Giá/đêm</div>
                        <div class="hd-th hd-th-avail" style="text-align:center">Phòng trống</div>
                        <div class="hd-th hd-th-opt">Lựa chọn</div>
                        <div class="hd-th hd-th-btn"></div>
                    </div>

                    <?php if(empty($rooms)): ?>
                        <div class="hd-no-rooms">Chưa có thông tin phòng cho khách sạn này</div>
                    <?php else: ?>
                        <?php foreach ($rooms as $room):
                            $rn         = $room['room_name'] ?? '';
                            $rc         = $room_config[$rn] ?? ['icon' => '🛏️', 'size' => '20m²', 'tag' => 'Tiêu chuẩn'];
                            $bed        = $room['bed_type'] ?? '';
                            $num_guests = guests_from_bed($bed);
                            $price_per  = $room['price'];
                            $price_old  = round($price_per * 1.25);
                            $urgent     = (int)($room['quantity'] ?? 0) <= 2;
                        ?>
                        <div class="hd-room-row <?= $urgent ? 'hd-room-urgent' : '' ?>">
                            <div class="hd-room-info">
                                <div class="hd-room-name">
                                    <span class="hd-room-icon"><?= $rc['icon'] ?></span>
                                    <span class="hd-room-link"><?= htmlspecialchars($rn) ?></span>
                                </div>
                                <div class="hd-room-tags">
                                    <span class="hd-rtag">📐 <?= $rc['size'] ?></span>
                                    <?php if ($bed): ?>
                                        <span class="hd-rtag"><?= bed_icon($bed) ?> <?= htmlspecialchars($bed) ?></span>
                                    <?php endif; ?>
                                    <span class="hd-rtag">🚿 Phòng tắm riêng</span>
                                    <span class="hd-rtag">📶 WiFi</span>
                                    <span class="hd-rtag">📺 TV</span>
                                </div>
                            </div>

                            <div class="hd-room-guests">
                                <?php for ($g = 0; $g < $num_guests; $g++): ?><span>👤</span><?php endfor; ?>
                            </div>

                            <div class="hd-room-price-wrap">
                                <div class="hd-room-old"><?= number_format($price_old, 0, ',', '.') ?>đ</div>
                                <div class="hd-room-price"><?= number_format($price_per, 0, ',', '.') ?>đ</div>
                                <div class="hd-room-tax">Đã gồm thuế & phí</div>
                            </div>

                            <div class="hd-room-avail" style="text-align:center;padding:0 8px">
                                <?php $qty = (int)($room['quantity'] ?? 0); ?>
                                <?php if ($qty === 0): ?>
                                    <span style="display:inline-block;background:#fff5f5;color:#c53030;border:1px solid #fed7d7;border-radius:8px;padding:4px 10px;font-size:13px;font-weight:600">Hết phòng</span>
                                <?php elseif ($urgent): ?>
                                    <span style="display:inline-block;background:#fff8f0;color:#e05c1a;border:1px solid #fbd38d;border-radius:8px;padding:4px 10px;font-size:13px;font-weight:700">🔥 Còn <?= $qty ?></span>
                                <?php else: ?>
                                    <span style="display:inline-block;background:#f0fff4;color:#276749;border:1px solid #9ae6b4;border-radius:8px;padding:4px 10px;font-size:13px;font-weight:600">✅ <?= $qty ?> phòng</span>
                                <?php endif; ?>
                            </div>

                            <div class="hd-room-opts">
                                <div class="hd-opt-item hd-opt-free">✅ Hủy miễn phí</div>
                                <div class="hd-opt-item">💳 Không cần thẻ</div>
                                <div class="hd-opt-item">🍳 Bữa sáng không bắt buộc</div>
                            </div>

                            <div class="hd-room-action">
                                <button class="hd-btn-reserve"
                                    onclick="openBookModal(<?= $room['id'] ?>, '<?= htmlspecialchars($rn, ENT_QUOTES) ?>', <?= $price_per ?>)">
                                    Đặt phòng này
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quy tắc -->
            <div class="hd-card" id="rules">
                <h2 class="hd-card-title">📋 Quy tắc chung</h2>
                <div class="hd-rules">
                    <?php foreach([
                        ['🕐', 'Nhận phòng', 'Từ ' . ($hotel['checkin_time'] ?? '14:00')],
                        ['🕛', 'Trả phòng',  'Trước ' . ($hotel['checkout_time'] ?? '12:00')],
                        ['🚭','Hút thuốc',   'Không được phép'],
                        ['🐾','Vật nuôi',    'Được phép theo yêu cầu'],
                        ['💳','Thanh toán',  'Tiền mặt · VNPay · MoMo · Thẻ'],
                        ['👶','Trẻ em',      'Phù hợp với mọi lứa tuổi'],
                    ] as [$icon,$label,$val]): ?>
                    <div class="hd-rule-row">
                        <span class="hd-rule-icon"><?= $icon ?></span>
                        <span class="hd-rule-label"><?= $label ?></span>
                        <span class="hd-rule-val"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Đánh giá khách hàng -->
            <div class="hd-card">
                <?php $hotel_id = $id; include __DIR__ . '/../includes/review_section.php'; ?>
            </div>

        </div><!-- /.hd-left -->

        <!-- SIDEBAR -->
        <div class="hd-sidebar">
            <div class="hd-price-card">
                <div class="hd-pc-label">Giá chỉ từ</div>
                <div class="hd-pc-price"><?= number_format($min_room_price, 0, ',', '.') ?>đ<span>/đêm</span></div>
                <?php if($max_room_price > $min_room_price): ?>
                    <div style="font-size:13px;color:#718096;margin-top:2px">đến <?= number_format($max_room_price, 0, ',', '.') ?>đ/đêm</div>
                <?php endif; ?>
                <?php if($nights > 1): ?>
                    <div class="hd-pc-total"><?= $nights ?> đêm = <strong><?= number_format($min_room_price * $nights, 0, ',', '.') ?>đ</strong></div>
                <?php endif; ?>
                <a href="#rooms" class="hd-pc-btn">Chọn phòng & Đặt ngay</a>
                <div class="hd-pc-notes">
                    <div>✅ Hủy miễn phí trước 24h</div>
                    <div>✅ Không cần thẻ tín dụng</div>
                    <div>✅ Xác nhận tức thì</div>
                </div>
            </div>

            <div class="hd-score-card">
                <div class="hd-sc-top">
                    <span class="hd-sc-num"><?= number_format($hotel['rating'], 1) ?></span>
                    <div>
                        <div class="hd-sc-lbl" style="color:<?= $rating_color ?>"><?= $rating_lbl ?></div>
                        <div class="hd-sc-reviews"><?= number_format($hotel['review_count'] ?? 0) ?> đánh giá</div>
                    </div>
                </div>
                <div class="hd-sc-bars">
                    <?php
                    $r = $hotel['rating'];
                    foreach([
                        'Nhân viên' => min(10, round($r + 0.5, 1)),
                        'Vị trí'    => min(10, round($r + 0.3, 1)),
                        'Sạch sẽ'   => min(10, round($r - 0.2, 1)),
                        'Tiện nghi' => min(10, round($r - 0.4, 1)),
                        'Đáng giá'  => min(10, round($r - 0.1, 1)),
                    ] as $cat => $val): ?>
                    <div class="hd-sc-row">
                        <span class="hd-sc-cat"><?= $cat ?></span>
                        <div class="hd-sc-bar"><div class="hd-sc-fill" style="width:<?= $val * 10 ?>%"></div></div>
                        <span class="hd-sc-val"><?= number_format($val, 1) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hd-loc-card">
                <div class="hd-lc-title">📍 Vị trí</div>
                <div class="hd-lc-addr"><?= htmlspecialchars($hotel['address']) ?></div>
                <?php if(!empty($hotel['location_name'])): ?>
                    <div class="hd-lc-region">🗺️ <?= htmlspecialchars($hotel['location_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="hd-quick-card">
                <div class="hd-qc-title">⚡ Điểm nổi bật</div>
                <div class="hd-qc-list">
                    <div class="hd-qc-item">✅ Hoàn hảo cho kỳ nghỉ <?= $nights > 1 ? "$nights đêm" : "ngắn ngày" ?></div>
                    <div class="hd-qc-item">🏆 Địa điểm hàng đầu được đánh giá cao</div>
                    <div class="hd-qc-item">🍳 Có phục vụ bữa sáng</div>
                    <div class="hd-qc-item">🚗 Chỗ đậu xe riêng miễn phí</div>
                </div>
            </div>

            <!-- Bài viết liên quan cùng vùng -->
            <?php if (!empty($blogs_nearby)): ?>
            <div class="hd-widget-card">
                <div class="hd-wc-title">📝 Bài viết về <?= htmlspecialchars($hotel['location_name']) ?></div>
                <?php foreach ($blogs_nearby as $bl): ?>
                <a href="/pages/blog-detail.php?id=<?= $bl['id'] ?>"
                class="hd-wc-item">
                    <img src="<?= htmlspecialchars($bl['thumb']) ?>"
                        alt="<?= htmlspecialchars($bl['title']) ?>"
                        loading="lazy"
                        onerror="this.src='/assets/images/hotel1.jpg'">
                    <div>
                        <span class="hd-wc-loc">📅 <?= $bl['date'] ?></span>
                        <p class="hd-wc-name"><?= htmlspecialchars(mb_substr($bl['title'], 0, 50)) ?>...</p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.hd-sidebar -->

    </div><!-- /.hd-grid -->
</div>
</div>

<script>
const photos = <?= json_encode(array_values($all_photos)) ?>;
let lbIdx = 0;

function switchMain(el, src, idx) {
    document.getElementById('hd-main-img').src = src;
    document.querySelectorAll('.hd-thumb').forEach(t => t.classList.remove('hd-thumb-active'));
    el.classList.add('hd-thumb-active');
    lbIdx = idx;
}

function openGallery(idx) {
    lbIdx = Math.max(0, Math.min(idx, photos.length - 1));
    updateLb();
    document.getElementById('hdLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeGallery() {
    document.getElementById('hdLightbox').classList.remove('open');
    document.body.style.overflow = '';
}
function lbNav(dir) {
    lbIdx = (lbIdx + dir + photos.length) % photos.length;
    updateLb();
}
function updateLb() {
    const p = photos[lbIdx];
    const img = document.getElementById('hdLbImg');
    img.style.opacity = '0';
    setTimeout(() => { img.src = p.src; img.style.opacity = '1'; }, 120);
    document.getElementById('hdLbCaption').textContent = p.caption || '';
    document.getElementById('hdLbCounter').textContent = (lbIdx + 1) + ' / ' + photos.length;
    document.querySelectorAll('.hd-lb-tn').forEach((t, i) => t.classList.toggle('active', i === lbIdx));
    const tn = document.querySelectorAll('.hd-lb-tn')[lbIdx];
    if (tn) tn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

document.addEventListener('keydown', e => {
    if (!document.getElementById('hdLightbox').classList.contains('open')) return;
    if (e.key === 'ArrowRight') lbNav(1);
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'Escape')     closeGallery();
});

let tsX = 0;
document.getElementById('hdLightbox').addEventListener('touchstart', e => { tsX = e.touches[0].clientX; });
document.getElementById('hdLightbox').addEventListener('touchend',   e => {
    const diff = tsX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) lbNav(diff > 0 ? 1 : -1);
});

const hdSections = document.querySelectorAll('#overview, #amenities, #rooms, #rules, #reviews');
const hdTabs = document.querySelectorAll('.hd-nav-tab');
window.addEventListener('scroll', () => {
    let cur = '';
    hdSections.forEach(s => { if (window.scrollY >= s.offsetTop - 80) cur = s.id; });
    hdTabs.forEach(t => t.classList.toggle('active', t.getAttribute('href') === '#' + cur));
});

document.querySelectorAll('.hd-nav-tab, .hd-cta-btn, .hd-pc-btn, .hd-nav-book, a[href="#rooms"]').forEach(a => {
    a.addEventListener('click', e => {
        const href = a.getAttribute('href');
        if (href && href.startsWith('#')) {
            e.preventDefault();
            const el = document.querySelector(href);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

/* ===== YÊU THÍCH ===== */
function toggleFavorite(hotelId) {
    <?php if (!isset($_SESSION['user_id'])): ?>
        showLoginModal();
        return;
    <?php endif; ?>

    const btn   = document.getElementById('hdFavBtn');
    const icon  = document.getElementById('hdFavIcon');
    const label = document.getElementById('hdFavLabel');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('hotel_id', hotelId);

    fetch('/pages/toggle_favorite.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const added = data.action === 'added';
                icon.setAttribute('fill',   added ? '#e53e3e' : 'none');
                icon.setAttribute('stroke', added ? '#e53e3e' : '#4a5568');
                label.textContent = added ? 'Đã lưu' : 'Lưu yêu thích';
                btn.classList.toggle('hd-fav-active', added);
                btn.title = added ? 'Bỏ yêu thích' : 'Lưu yêu thích';
                showFavToast(data.message);
            } else {
                showFavToast(data.message || 'Có lỗi xảy ra.');
            }
        })
        .catch(() => showFavToast('Lỗi kết nối, thử lại.'))
        .finally(() => { btn.disabled = false; });
}

function showLoginModal() {
    document.getElementById('favLoginModal').style.display = 'flex';
}
function closeFavLoginModal() {
    document.getElementById('favLoginModal').style.display = 'none';
}
function goToLogin() {
    window.location.href = '/auth/login.php';
}

function showFavToast(msg) {
    let t = document.getElementById('hdFavToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'hdFavToast';
        t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(60px);background:#2d3748;color:#fff;padding:10px 22px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 4px 18px rgba(0,0,0,.2);opacity:0;transition:all .3s;z-index:9999;white-space:nowrap;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(-50%) translateY(60px)';
    }, 2800);
}
</script>

<style>
.hd-fav-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px;
    border-radius: 10px;
    border: 1.5px solid #cbd5e0;
    background: #fff;
    color: #4a5568;
    font-size: 14px; font-weight: 600;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s, transform 0.15s;
    white-space: nowrap;
}
.hd-fav-btn:hover { border-color: #e53e3e; background: #fff5f5; color: #e53e3e; }
.hd-fav-btn.hd-fav-active { border-color: #e53e3e; background: #fff5f5; color: #e53e3e; }
.hd-fav-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.hd-header-cta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

/* === MODAL ĐĂNG NHẬP YÊU THÍCH === */
.fav-modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
.fav-modal-box {
    background: #fff;
    border-radius: 16px;
    padding: 0;
    width: 360px;
    max-width: calc(100vw - 32px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: favModalIn 0.22s ease;
}
@keyframes favModalIn {
    from { opacity: 0; transform: scale(0.92) translateY(-12px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.fav-modal-header {
    background: linear-gradient(135deg, #0071c2, #005fa3);
    padding: 18px 22px 16px;
    display: flex; align-items: center; justify-content: space-between;
}
.fav-modal-title {
    display: flex; align-items: center; gap: 9px;
    color: #fff; font-size: 16px; font-weight: 700;
}
.fav-modal-close {
    background: rgba(255,255,255,0.18);
    border: none; border-radius: 50%;
    width: 28px; height: 28px;
    color: #fff; font-size: 16px; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.fav-modal-close:hover { background: rgba(255,255,255,0.32); }
.fav-modal-body {
    padding: 24px 22px 10px;
    text-align: center;
}
.fav-modal-icon { font-size: 44px; margin-bottom: 12px; }
.fav-modal-msg {
    font-size: 15px; color: #2d3748; font-weight: 500;
    margin-bottom: 6px; line-height: 1.5;
}
.fav-modal-sub { font-size: 13px; color: #718096; margin-bottom: 0; }
.fav-modal-footer {
    padding: 18px 22px 22px;
    display: flex; gap: 10px;
}
.fav-modal-btn-cancel {
    flex: 1; padding: 10px 0;
    border-radius: 9px;
    border: 1.5px solid #e2e8f0;
    background: #fff; color: #4a5568;
    font-size: 14px; font-weight: 600;
    cursor: pointer;
    transition: background 0.18s;
}
.fav-modal-btn-cancel:hover { background: #f7fafc; }
.fav-modal-btn-login {
    flex: 1; padding: 10px 0;
    border-radius: 9px;
    border: none;
    background: #0071c2; color: #fff;
    font-size: 14px; font-weight: 700;
    cursor: pointer;
    transition: background 0.18s;
}
.fav-modal-btn-login:hover { background: #005fa3; }
</style>

<!-- MODAL ĐĂNG NHẬP ĐỂ LƯU YÊU THÍCH -->
<div class="fav-modal-overlay" id="favLoginModal" onclick="if(event.target===this) closeFavLoginModal()">
    <div class="fav-modal-box">
        <div class="fav-modal-header">
            <div class="fav-modal-title">
                <img src="/assets/images/StayGo.png"
                     alt="StayGo" style="height:24px;object-fit:contain;">
                StayGo
            </div>
            <button class="fav-modal-close" onclick="closeFavLoginModal()">✕</button>
        </div>
        <div class="fav-modal-body">
            <div class="fav-modal-icon">🤍</div>
            <div class="fav-modal-msg">Bạn cần đăng nhập để lưu yêu thích</div>
            <div class="fav-modal-sub">Đăng nhập để lưu khách sạn và xem lại bất cứ lúc nào.</div>
        </div>
        <div class="fav-modal-footer">
            <button class="fav-modal-btn-cancel" onclick="closeFavLoginModal()">Hủy</button>
            <button class="fav-modal-btn-login"  onclick="goToLogin()">Đăng nhập</button>
        </div>
    </div>
</div>

<!-- MODAL CHỌN NGÀY ĐẶT PHÒNG -->
<div class="book-modal-overlay" id="bookModalOverlay" onclick="if(event.target===this) closeBookModal()">
    <div class="book-modal-box">
        <div class="book-modal-header">
            <div class="book-modal-title">📅 Chọn ngày đặt phòng</div>
            <button class="book-modal-close" onclick="closeBookModal()">✕</button>
        </div>
        <div class="book-modal-body">
            <div class="book-modal-room" id="bmRoomName"></div>
            <div class="book-modal-dates">
                <div class="book-modal-field">
                    <label>Ngày nhận phòng</label>
                    <input type="date" id="bmCheckin" onchange="calcBookTotal()">
                </div>
                <div class="book-modal-arrow">→</div>
                <div class="book-modal-field">
                    <label>Ngày trả phòng</label>
                    <input type="date" id="bmCheckout" onchange="calcBookTotal()">
                </div>
            </div>
            <div class="book-modal-summary" id="bmSummary" style="display:none">
                <div class="bms-row">
                    <span id="bmNightsLabel"></span>
                    <strong id="bmTotal"></strong>
                </div>
                <div class="bms-note">Đã gồm thuế & phí</div>
            </div>
        </div>
        <div class="book-modal-footer">
            <button class="bm-btn-cancel" onclick="closeBookModal()">Hủy</button>
            <button class="bm-btn-confirm" id="bmConfirmBtn" onclick="goToPayment()">Tiến hành đặt phòng →</button>
        </div>
    </div>
</div>

<script>
let _bmRoomId = 0, _bmPrice = 0, _bmHotelId = <?= $id ?>;
// Dates from search bar (may be empty string if not set)
const _urlCheckin  = <?= json_encode($checkin) ?>;
const _urlCheckout = <?= json_encode($checkout) ?>;

function openBookModal(roomId, roomName, pricePerNight) {
    _bmRoomId = roomId;
    _bmPrice  = pricePerNight;
    document.getElementById('bmRoomName').textContent = '🛏️ ' + roomName + ' — ' + pricePerNight.toLocaleString('vi-VN') + 'đ/đêm';
    const fmt = d => d.toISOString().split('T')[0];
    const today    = new Date();
    const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
    // Use dates from search bar if available, otherwise fall back to today/tomorrow
    const ciVal = _urlCheckin  || fmt(today);
    const coVal = _urlCheckout || fmt(tomorrow);
    document.getElementById('bmCheckin').value  = ciVal;
    document.getElementById('bmCheckin').min    = fmt(today);
    document.getElementById('bmCheckout').value = coVal;
    document.getElementById('bmCheckout').min   = ciVal;
    calcBookTotal();
    document.getElementById('bookModalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeBookModal() {
    document.getElementById('bookModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function calcBookTotal() {
    const ci = document.getElementById('bmCheckin').value;
    const co = document.getElementById('bmCheckout').value;
    // Ensure checkout > checkin
    if (ci && co) {
        const minCo = new Date(ci); minCo.setDate(minCo.getDate() + 1);
        document.getElementById('bmCheckout').min = minCo.toISOString().split('T')[0];
        if (new Date(co) <= new Date(ci)) {
            document.getElementById('bmCheckout').value = minCo.toISOString().split('T')[0];
        }
    }
    const nights = ci && co ? Math.max(1, Math.round((new Date(co) - new Date(ci)) / 86400000)) : 1;
    const total  = _bmPrice * nights;
    document.getElementById('bmNightsLabel').textContent = nights + ' đêm × ' + _bmPrice.toLocaleString('vi-VN') + 'đ';
    document.getElementById('bmTotal').textContent       = '= ' + total.toLocaleString('vi-VN') + 'đ';
    document.getElementById('bmSummary').style.display   = 'flex';
}

function goToPayment() {
    const ci = document.getElementById('bmCheckin').value;
    const co = document.getElementById('bmCheckout').value;
    if (!ci || !co) { alert('Vui lòng chọn ngày nhận và trả phòng!'); return; }
    window.location.href = '/pages/payment.php?hotel_id=' + _bmHotelId + '&room_id=' + _bmRoomId + '&checkin=' + ci + '&checkout=' + co;
}
</script>

<style>
.book-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 99999;
    align-items: center; justify-content: center;
}
.book-modal-overlay.open { display: flex; }
.book-modal-box {
    background: #fff; border-radius: 16px;
    width: 480px; max-width: calc(100vw - 32px);
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
    overflow: hidden; animation: bmIn .22s ease;
}
@keyframes bmIn { from { opacity:0; transform:scale(.93) translateY(-10px); } to { opacity:1; transform:none; } }
.book-modal-header {
    background: linear-gradient(135deg,#0071c2,#005fa3);
    padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;
}
.book-modal-title { color:#fff; font-size:16px; font-weight:700; }
.book-modal-close {
    background: rgba(255,255,255,.18); border:none; border-radius:50%;
    width:28px; height:28px; color:#fff; font-size:16px;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
}
.book-modal-close:hover { background:rgba(255,255,255,.32); }
.book-modal-body { padding: 20px; }
.book-modal-room {
    background:#f0f7ff; border-radius:10px; padding:10px 14px;
    font-size:14px; font-weight:600; color:#0071c2; margin-bottom:16px;
}
.book-modal-dates { display:flex; align-items:flex-end; gap:10px; }
.book-modal-field { flex:1; display:flex; flex-direction:column; gap:5px; }
.book-modal-field label { font-size:12px; font-weight:600; color:#4a5568; }
.book-modal-field input[type=date] {
    padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:9px;
    font-size:14px; color:#2d3748; width:100%; outline:none;
    transition: border-color .2s;
}
.book-modal-field input[type=date]:focus { border-color:#0071c2; }
.book-modal-arrow { font-size:18px; color:#a0aec0; padding-bottom:10px; }
.book-modal-summary {
    display:flex; flex-direction:column; gap:4px;
    background:#f7fafc; border-radius:10px;
    padding:12px 14px; margin-top:14px;
}
.bms-row { display:flex; align-items:center; justify-content:space-between; font-size:14px; }
.bms-row strong { font-size:18px; color:#0071c2; }
.bms-note { font-size:12px; color:#718096; }
.book-modal-footer {
    padding:16px 20px; display:flex; gap:10px;
    border-top:1px solid #f0f0f0;
}
.bm-btn-cancel {
    flex:1; padding:11px 0; border-radius:9px;
    border:1.5px solid #e2e8f0; background:#fff; color:#4a5568;
    font-size:14px; font-weight:600; cursor:pointer;
}
.bm-btn-cancel:hover { background:#f7fafc; }
.bm-btn-confirm {
    flex:2; padding:11px 0; border-radius:9px; border:none;
    background:#0071c2; color:#fff;
    font-size:14px; font-weight:700; cursor:pointer;
    transition: background .18s;
}
.bm-btn-confirm:hover { background:#005fa3; }
/* Fix nút Đặt phòng này dạng button */
.hd-btn-reserve {
    display:inline-block; padding:10px 16px; border-radius:9px;
    background:#0071c2; color:#fff; font-size:13px; font-weight:600;
    border:none; cursor:pointer; white-space:nowrap;
    transition: background .18s;
}
.hd-btn-reserve:hover { background:#005fa3; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>