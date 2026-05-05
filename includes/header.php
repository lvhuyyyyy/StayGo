<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ---- Kiểm tra chế độ bảo trì ----
$_maintenance_row = $conn->query("SELECT value FROM site_settings WHERE `key`='maintenance_mode' LIMIT 1")->fetch_assoc();
if (($_maintenance_row['value'] ?? '0') === '1') {
    // Admin đang đăng nhập thì bỏ qua
    if (empty($_SESSION['admin_id'])) {
        http_response_code(503);
        header('Retry-After: 3600');
        ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo trì hệ thống – StayGo</title>
    <link rel="icon" type="image/png" href="/assets/images/StayGo.png">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Be Vietnam Pro',sans-serif;background:linear-gradient(135deg,#1e3a5f 0%,#1e73be 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:#fff;border-radius:24px;padding:52px 44px;max-width:480px;width:100%;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.25)}
        .icon{font-size:64px;margin-bottom:20px;animation:spin 3s linear infinite}
        @keyframes spin{0%,100%{transform:rotate(0deg)}50%{transform:rotate(15deg)}}
        h1{font-size:26px;font-weight:800;color:#1a202c;margin-bottom:10px}
        p{font-size:14.5px;color:#718096;line-height:1.7;margin-bottom:6px}
        .badge{display:inline-block;padding:6px 18px;background:#ebf8ff;color:#1e73be;border-radius:20px;font-size:12.5px;font-weight:700;margin-top:20px}
        .brand{font-size:22px;font-weight:800;color:#1e73be;margin-bottom:28px;letter-spacing:-0.5px}
    </style>
</head>
<body>
<div class="card">
    <div class="brand">StayGo</div>
    <div class="icon">🔧</div>
    <h1>Đang bảo trì hệ thống</h1>
    <p>Website hiện đang được nâng cấp để mang lại trải nghiệm tốt hơn cho bạn.</p>
    <p>Vui lòng quay lại sau ít phút.</p>
    <div class="badge">⏱ Dự kiến hoàn tất trong thời gian ngắn</div>
</div>
</body>
</html>
        <?php
        exit;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

// Active cho cả trang danh sách lẫn trang chi tiết blog
$blog_pages = ['blog-list.php', 'blog-detail.php'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'StayGo' ?></title>
    <link rel="icon" type="image/png" href="/assets/images/StayGo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/main.js" defer></script>
    <script src="/assets/js/payment.js" defer></script>
</head>
<body>

<header class="header">
    <div class="container header-flex">

        <!-- LOGO -->
        <a href="/" class="logo-link">
            <img src="/assets/images/StayGo.png"
                alt="StayGo"
                style="width:auto;height:60px;object-fit:contain;">
        </a>

        <nav>
            <a href="/"
            class="<?= in_array($current_page, ['index.php','home.php']) ? 'active' : '' ?>">
            Trang chủ
            </a>
            <a href="/pages/hotels.php"
            class="<?= $current_page === 'hotels.php' ? 'active' : '' ?>">
            Khách sạn
            </a>
            <a href="/pages/blog-list.php"
            class="<?= in_array($current_page, $blog_pages) ? 'active' : '' ?>">
            Bài Viết
            </a>
            <a href="/pages/deals.php"
            class="<?= $current_page === 'deals.php' ? 'active' : '' ?>">
            Ưu Đãi
            </a>

            <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'admin'): ?>
                <?php
                $user_id = (int) $_SESSION['user_id'];
                if (!isset($_SESSION['user_cache'])) {
                    $stmt_hdr = $conn->prepare("SELECT full_name, email, role FROM users WHERE id = ?");
                    $stmt_hdr->bind_param("i", $user_id);
                    $stmt_hdr->execute();
                    $_SESSION['user_cache'] = $stmt_hdr->get_result()->fetch_assoc();
                    $stmt_hdr->close();
                }
                $user = $_SESSION['user_cache'];
                $display_name = htmlspecialchars($user['full_name'] ?? $_SESSION['email']);
                $first_char = mb_strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1));
                ?>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-trigger" onclick="toggleDropdown()">
                        Xin chào, <?= $display_name ?>
                        <svg class="chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z"/>
                        </svg>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">
                            <div class="dh-avatar"><?= $first_char ?></div>
                            <div class="dh-info">
                                <div class="dh-name"><?= $display_name ?></div>
                                <div class="dh-role"><?= htmlspecialchars($user['role'] ?? 'user') ?></div>
                            </div>
                        </div>
                        <div class="dropdown-items">
                            <a href="/pages/profile.php" class="dropdown-item">
                                <span class="item-icon"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg></span>
                                Thông tin tài khoản
                            </a>
                            <a href="/pages/edit_profile.php" class="dropdown-item">
                                <span class="item-icon"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></span>
                                Chỉnh sửa thông tin
                            </a>
                            <a href="/pages/change_password.php" class="dropdown-item">
                                <span class="item-icon"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg></span>
                                Đổi mật khẩu
                            </a>
                            <a href="/pages/my_bookings.php" class="dropdown-item">
                                <span class="item-icon"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
                                Lịch sử đặt phòng
                            </a>
                            <a href="/pages/my_favorites.php" class="dropdown-item">
                                <span class="item-icon"><svg width="15" height="15" fill="#e53e3e" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></span>
                                Yêu thích
                            </a>
                            <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <div class="dropdown-divider"></div>
                            <a href="dashboard.php" class="dropdown-item">
                                <span class="item-icon"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                                Admin Panel
                            </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="/auth/logout.php" class="dropdown-item danger">
                                <span class="item-icon danger-icon"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#e53e3e" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg></span>
                                Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="/auth/login.php" class="btn-login-nav">Đăng nhập</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$show_hero = in_array($current_page, ['index.php','home.php'])
        || $uri === '/tour_khach_san_project'
        || $uri === '/';

if ($show_hero):
// Lấy danh sách địa điểm + số khách sạn từ DB (tự động cập nhật khi thêm mới)
$hero_locations = [];
$hl = $conn->query("
    SELECT l.id, l.name, COUNT(h.id) AS hotel_count
    FROM locations l
    LEFT JOIN hotels h ON h.location_id = l.id AND h.is_active = 1
    GROUP BY l.id, l.name
    ORDER BY l.name
");
if ($hl) $hero_locations = $hl->fetch_all(MYSQLI_ASSOC);
?>
<div class="hero-search-section">
    <div class="hero-search-title">Tìm khách sạn hoàn hảo cho bạn</div>
    <div class="hero-search-sub">Hàng trăm khách sạn tại Kon Tum, Măng Đen & Quảng Ngãi</div>
    <form action="/pages/hotels.php" method="GET" class="hero-search-box" id="heroSearchForm">
        <input type="hidden" name="location_id" id="heroLocationId" value="">
        <div class="hsb-field hsb-keyword" style="position:relative;">
            <span class="hsb-label">📍 Địa điểm / Tên khách sạn</span>
            <input class="hsb-input" type="text" name="keyword" id="heroKeyword"
                   placeholder="Tên KS, địa điểm..."
                   autocomplete="off"
                   onfocus="heroShowDropdown()"
                   oninput="heroFilterDropdown(this.value)"
                   onblur="setTimeout(heroHideDropdown, 200)">
            <!-- Dropdown địa điểm -->
            <div class="hsb-dropdown" id="heroDropdown" style="display:none;">
                <div class="hsb-dropdown-title">Địa điểm phổ biến</div>
                <?php foreach ($hero_locations as $loc): ?>
                <div class="hsb-dropdown-item"
                     data-id="<?= $loc['id'] ?>"
                     data-name="<?= htmlspecialchars($loc['name']) ?>"
                     onmousedown="heroSelectLocation(<?= $loc['id'] ?>, '<?= htmlspecialchars($loc['name'], ENT_QUOTES) ?>')">
                    <span class="hsb-di-icon">📍</span>
                    <span class="hsb-di-name"><?= htmlspecialchars($loc['name']) ?></span>
                    <span class="hsb-di-count"><?= $loc['hotel_count'] ?> khách sạn</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hsb-field">
            <span class="hsb-label">📅 Nhận phòng</span>
            <input class="hsb-input" type="date" name="checkin" id="h_checkin" onchange="heroCalcNights()">
        </div>
        <div class="hsb-field">
            <span class="hsb-label">📅 Trả phòng</span>
            <input class="hsb-input" type="date" name="checkout" id="h_checkout" onchange="heroCalcNights()">
        </div>
        <div class="hsb-nights" id="heroNights" style="display:none; padding:0 12px; white-space:nowrap; color:#2b6cb0; font-size:13px; font-weight:700; font-family:'Be Vietnam Pro',sans-serif;">
            🌙 <span id="heroNightsText">1 đêm</span>
        </div>
        <button type="submit" class="hsb-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            Tìm kiếm
        </button>
    </form>
    <div class="hero-quick-tags">
        <a href="/pages/hotels.php?rating=9">⭐ Tuyệt vời 9+</a>
        <a href="/pages/hotels.php?max_price=500000">💰 Dưới 500k</a>
        <a href="/pages/hotels.php?sort=popular">🔥 Phổ biến nhất</a>
        <a href="/pages/hotels.php?sort=price_asc">📊 Giá thấp nhất</a>
    </div>
</div>
<?php endif; ?>
<style>
/* === HERO SEARCH DROPDOWN === */
.hsb-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0; right: 0;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    border: 1px solid #e2e8f0;
    z-index: 9999;
    overflow: hidden;
}
.hsb-dropdown-title {
    padding: 10px 16px 6px;
    font-size: 11px;
    font-weight: 700;
    color: #a0aec0;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.hsb-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    cursor: pointer;
    transition: background 0.15s;
}
.hsb-dropdown-item:hover {
    background: #ebf4ff;
}
.hsb-di-icon { font-size: 16px; flex-shrink: 0; }
.hsb-di-name { flex: 1; font-size: 14px; font-weight: 600; color: #1a202c; }
.hsb-di-count { font-size: 12px; color: #a0aec0; white-space: nowrap; }
</style>
<script>
/* === HERO DROPDOWN LOCATION === */
function heroShowDropdown() {
    const dd = document.getElementById('heroDropdown');
    if (dd) dd.style.display = 'block';
}
function heroHideDropdown() {
    const dd = document.getElementById('heroDropdown');
    if (dd) dd.style.display = 'none';
}
function heroFilterDropdown(val) {
    // Khi gõ tự do → xóa location_id đã chọn
    document.getElementById('heroLocationId').value = '';
    const items = document.querySelectorAll('.hsb-dropdown-item');
    const q = val.toLowerCase().trim();
    let any = false;
    items.forEach(item => {
        const name = item.dataset.name.toLowerCase();
        const show = !q || name.includes(q);
        item.style.display = show ? 'flex' : 'none';
        if (show) any = true;
    });
    document.getElementById('heroDropdown').style.display = any ? 'block' : 'none';
}
function heroSelectLocation(id, name) {
    document.getElementById('heroLocationId').value = id;
    document.getElementById('heroKeyword').value = name;
    heroHideDropdown();
    // Focus sang ô ngày nhận phòng để người dùng chọn tiếp
    document.getElementById('h_checkin').focus();
}

function heroCalcNights() {
    const ci    = document.getElementById('h_checkin')?.value;
    const co    = document.getElementById('h_checkout')?.value;
    const badge = document.getElementById('heroNights');
    const txt   = document.getElementById('heroNightsText');
    if (!ci || !co) { if(badge) badge.style.display = 'none'; return; }
    const n = Math.round((new Date(co) - new Date(ci)) / 86400000);
    if (n > 0) { txt.textContent = n + ' đêm'; badge.style.display = 'flex'; badge.style.alignItems = 'center'; }
    else badge.style.display = 'none';
}
function toggleDropdown() {
    document.getElementById('userDropdown')?.classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const d = document.getElementById('userDropdown');
    if (d && !d.contains(e.target)) d.classList.remove('open');
});
</script>