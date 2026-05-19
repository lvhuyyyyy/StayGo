<?php
// Auth guard cho Hotel Portal
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['hotel_id'])) {
    header("Location: /hotel/login.php");
    exit;
}

// Kiểm tra partner_status — nếu bị suspend thì không cho vào
if (isset($_SESSION['hotel_status']) && $_SESSION['hotel_status'] !== 'ACTIVE') {
    session_destroy();
    header("Location: /hotel/login.php?err=suspended");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$hotel_id   = (int)$_SESSION['hotel_id'];
$hotel_name = htmlspecialchars($_SESSION['hotel_name'] ?? 'Hotel');
$hotel_init = strtoupper(mb_substr($_SESSION['hotel_name'] ?? 'H', 0, 1));
$current_page = basename($_SERVER['PHP_SELF']);

// Badge: pending bookings cho hotel này
$pending_bookings = 0;
$pb = $conn->query("
    SELECT COUNT(*) as c FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id AND b.status = 'pending'
");
if ($pb) $pending_bookings = (int)$pb->fetch_assoc()['c'];

// Badge: payout READY
$payout_ready = 0;
$pr = $conn->query("
    SELECT COUNT(*) as c FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id AND b.payout_status = 'READY'
");
if ($pr) $payout_ready = (int)$pr->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Hotel Portal' ?> - StayGo Partner</title>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f4f8; color: #2d3748; }

    .hotel-wrapper { display: flex; min-height: 100vh; }

    /* Sidebar */
    .hotel-sidebar {
        width: 240px; background: linear-gradient(180deg, #1a365d 0%, #2d3748 100%);
        color: #fff; display: flex; flex-direction: column; position: fixed; top: 0; left: 0;
        height: 100vh; z-index: 100; overflow-y: auto;
    }
    .hotel-brand {
        padding: 20px 16px; display: flex; align-items: center; gap: 10px;
        border-bottom: 1px solid rgba(255,255,255,.12);
    }
    .hotel-brand-icon {
        width: 38px; height: 38px; background: linear-gradient(135deg, #3182ce, #63b3ed);
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex-shrink: 0;
    }
    .hotel-brand h2 { font-size: 16px; font-weight: 800; line-height: 1.2; }
    .hotel-brand span { font-size: 11px; opacity: .6; }

    .hotel-nav { flex: 1; padding: 12px 0; }
    .nav-label {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
        color: rgba(255,255,255,.4); padding: 12px 16px 4px;
    }
    .hotel-nav a {
        display: flex; align-items: center; gap: 10px; padding: 10px 16px;
        color: rgba(255,255,255,.75); text-decoration: none; font-size: 13.5px;
        font-weight: 500; border-radius: 8px; margin: 2px 8px; transition: .15s;
        position: relative;
    }
    .hotel-nav a:hover { background: rgba(255,255,255,.1); color: #fff; }
    .hotel-nav a.active { background: rgba(49,130,206,.35); color: #fff; }
    .hotel-badge {
        background: #e53e3e; color: #fff; font-size: 10px; font-weight: 700;
        padding: 2px 6px; border-radius: 10px; margin-left: auto;
    }

    .hotel-sidebar-footer {
        padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.12);
    }
    .hotel-sidebar-footer a {
        display: block; color: rgba(255,255,255,.6); text-decoration: none;
        font-size: 13px; padding: 8px 12px; border-radius: 8px;
        transition: .15s;
    }
    .hotel-sidebar-footer a:hover { background: rgba(255,255,255,.1); color: #fff; }

    /* Main */
    .hotel-main { margin-left: 240px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    .hotel-topbar {
        background: #fff; border-bottom: 1px solid #e2e8f0; padding: 14px 28px;
        display: flex; align-items: center; justify-content: space-between;
        position: sticky; top: 0; z-index: 50;
    }
    .hotel-topbar h1 { font-size: 20px; font-weight: 800; color: #1a365d; }
    .hotel-topbar p { font-size: 12.5px; color: #a0aec0; margin-top: 2px; }
    .hotel-topbar-right { display: flex; align-items: center; gap: 12px; }
    .hotel-avatar {
        width: 36px; height: 36px; background: linear-gradient(135deg, #3182ce, #63b3ed);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 700; color: #fff;
    }
    .hotel-topbar-name { font-size: 13px; font-weight: 600; color: #2d3748; }

    .hotel-content { padding: 24px 28px; flex: 1; }

    /* Cards */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .stat-card.blue { background: linear-gradient(135deg, #3182ce, #2b6cb0); color: #fff; }
    .stat-card.green { background: linear-gradient(135deg, #38a169, #276749); color: #fff; }
    .stat-card.orange { background: linear-gradient(135deg, #dd6b20, #c05621); color: #fff; }
    .stat-card.purple { background: linear-gradient(135deg, #6b46c1, #553c9a); color: #fff; }
    .stat-num { font-size: 28px; font-weight: 800; line-height: 1; }
    .stat-label { font-size: 12px; margin-top: 4px; opacity: .85; font-weight: 500; }

    /* Tables */
    .card { background: #fff; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 20px; }
    .card-header { padding: 16px 20px; border-bottom: 1px solid #f0f4f8; display: flex; align-items: center; justify-content: space-between; }
    .card-title { font-size: 15px; font-weight: 700; color: #1a365d; }
    .card-body { padding: 0; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f7fafc; padding: 10px 16px; text-align: left; font-size: 11.5px; font-weight: 700; color: #718096; text-transform: uppercase; letter-spacing: .4px; }
    td { padding: 12px 16px; border-top: 1px solid #f0f4f8; font-size: 13.5px; vertical-align: middle; }
    tr:hover td { background: #f7fafc; }

    /* Badges */
    .badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-confirmed { background: #dbeafe; color: #1e40af; }
    .badge-completed { background: #d1fae5; color: #065f46; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }
    .badge-checked_in { background: #ede9fe; color: #5b21b6; }
    .badge-holding { background: #fef3c7; color: #92400e; }
    .badge-ready { background: #d1fae5; color: #065f46; }
    .badge-frozen { background: #fee2e2; color: #991b1b; }
    .badge-paid { background: #dbeafe; color: #1e40af; }

    /* Buttons */
    .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; transition: .15s; }
    .btn-primary { background: #3182ce; color: #fff; }
    .btn-primary:hover { background: #2b6cb0; }
    .btn-success { background: #38a169; color: #fff; }
    .btn-success:hover { background: #276749; }
    .btn-danger { background: #e53e3e; color: #fff; }
    .btn-danger:hover { background: #c53030; }
    .btn-sm { padding: 5px 11px; font-size: 12px; }
    .btn-outline { background: transparent; border: 1.5px solid #e2e8f0; color: #4a5568; }
    .btn-outline:hover { background: #f7fafc; }

    /* Alert */
    .alert { padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-bottom: 16px; }
    .alert-success { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; }
    .alert-error { background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; }
    .alert-info { background: #ebf8ff; border: 1px solid #90cdf4; color: #2b6cb0; }

    /* Form */
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 700; color: #4a5568; margin-bottom: 6px; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px;
        font-size: 14px; font-family: inherit; box-sizing: border-box; transition: border-color .2s;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none; border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,.1);
    }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-row { padding: 20px; }
    </style>
</head>
<body>
<div class="hotel-wrapper">

<!-- Sidebar -->
<aside class="hotel-sidebar">
    <div class="hotel-brand">
        <div class="hotel-brand-icon">🏨</div>
        <div>
            <h2>StayGo</h2>
            <span>Hotel Partner</span>
        </div>
    </div>

    <nav class="hotel-nav">
        <div class="nav-label">Tổng quan</div>
        <a href="/hotel/dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            📊 Dashboard
        </a>

        <div class="nav-label">Đặt phòng</div>
        <a href="/hotel/bookings.php" class="<?= $current_page === 'bookings.php' ? 'active' : '' ?>">
            📋 Quản lý đặt phòng
            <?php if ($pending_bookings > 0): ?>
                <span class="hotel-badge"><?= $pending_bookings ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-label">Tài chính</div>
        <a href="/hotel/payouts.php" class="<?= $current_page === 'payouts.php' ? 'active' : '' ?>">
            💰 Lịch sử giải ngân
            <?php if ($payout_ready > 0): ?>
                <span class="hotel-badge"><?= $payout_ready ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-label">Phân tích</div>
        <a href="/hotel/analytics.php" class="<?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            📈 Hiệu suất & KPI
        </a>
        <a href="/hotel/reviews.php" class="<?= $current_page === 'reviews.php' ? 'active' : '' ?>">
            ⭐ Đánh giá khách
            <?php
            $unresponded = 0;
            if (@$conn->query("SELECT hotel_response FROM reviews LIMIT 0")) {
                $ur = $conn->query("SELECT COUNT(*) c FROM reviews WHERE hotel_id=$hotel_id AND is_active=1 AND (hotel_response IS NULL OR hotel_response='')");
                if ($ur) $unresponded = (int)$ur->fetch_assoc()['c'];
            }
            ?>
            <?php if ($unresponded > 0): ?>
                <span class="hotel-badge"><?= $unresponded ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-label">Quản lý</div>
        <a href="/hotel/rooms.php" class="<?= $current_page === 'rooms.php' ? 'active' : '' ?>">
            🛏️ Phòng của tôi
        </a>
        <a href="/hotel/images.php" class="<?= $current_page === 'images.php' ? 'active' : '' ?>">
            🖼️ Ảnh khách sạn
        </a>
        <a href="/hotel/profile.php" class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
            ⚙️ Thông tin khách sạn
        </a>

        <div class="nav-label">AI</div>
        <a href="/hotel/hotel_ai.php"
           class="<?= $current_page === 'hotel_ai.php' ? 'active' : '' ?>"
           style="background:<?= $current_page === 'hotel_ai.php' ? '' : 'rgba(49,130,206,.12)' ?>">
            🤖 AI Assistant
        </a>
    </nav>

    <div class="hotel-sidebar-footer">
        <a href="/hotel/logout.php">🚪 Đăng xuất</a>
    </div>
</aside>

<!-- Main -->
<div class="hotel-main">
    <div class="hotel-topbar">
        <div>
            <h1><?= $page_title ?? 'Dashboard' ?></h1>
            <p><?= $page_subtitle ?? date('d/m/Y') ?></p>
        </div>
        <div class="hotel-topbar-right">
            <div class="hotel-avatar"><?= $hotel_init ?></div>
            <span class="hotel-topbar-name"><?= $hotel_name ?></span>
        </div>
    </div>
    <div class="hotel-content">
