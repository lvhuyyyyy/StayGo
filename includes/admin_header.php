<?php
// -- Bảo vệ toàn bộ admin --
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/security.php';

// FIX: Dùng admin_id và admin_role thay vì user_id và role
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: " . BASE_PATH . "/auth/login_admin.php");
    exit;
}

// -- Lấy pending_count để hiển thị badge --
$_r = isset($conn) ? $conn->query("SELECT COUNT(*) as t FROM bookings WHERE status='pending'") : false;
$pending_count = $_r ? (int)$_r->fetch_assoc()['t'] : 0;

// -- Lấy refund_pending để hiển thị badge hoàn tiền --
$_r = isset($conn) ? $conn->query("SELECT COUNT(*) as c FROM bookings WHERE refund_requested = 1") : false;
$refund_pending = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

// -- Lấy support_pending để hiển thị badge hỗ trợ --
$_r = isset($conn) ? $conn->query("SELECT COUNT(*) as c FROM support_requests WHERE status='pending'") : false;
$support_pending = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
unset($_r);

// -- Xác định trang hiện tại để highlight menu --
$current_page = basename($_SERVER['PHP_SELF']);

// -- Thông tin admin đang đăng nhập --
$admin_name   = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_avatar = strtoupper(mb_substr($_SESSION['admin_name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?? 'Admin' ?> - StayGo</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="/assets/js/admin.js" defer></script>
</head>
<body>
<div class="admin-wrapper">

<!-- ----------- SIDEBAR ----------- -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
            </svg>
        </div>
        <div>
            <h2>StayGo</h2>
            <span>Admin Panel</span>
        </div>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Tổng quan</div>

        <a href="/admin/dashboard.php"
           class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            Dashboard
        </a>

        <div class="menu-label">Quản lý</div>

        <a href="/admin/users.php"
           class="<?= $current_page === 'users.php' ? 'active' : '' ?>">
            Quản lý User
        </a>

        <a href="/admin/hotels.php"
           class="<?= $current_page === 'hotels.php' ? 'active' : '' ?>">
            Khách sạn
        </a>

        <a href="/admin/rooms.php"
           class="<?= $current_page === 'rooms.php' ? 'active' : '' ?>">
            Phòng
        </a>

        <a href="/admin/bookings.php"
           class="<?= $current_page === 'bookings.php' ? 'active' : '' ?>">
            Đặt phòng
            <?php if($pending_count > 0): ?>
                <span class="sidebar-badge"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>

        <a href="/admin/payments.php"
           class="<?= $current_page === 'payments.php' ? 'active' : '' ?>">
            Thanh toán
        </a>

        <a href="/admin/refund_requests.php"
           class="<?= $current_page === 'refund_requests.php' ? 'active' : '' ?>">
            Hoàn tiền
            <?php if($refund_pending > 0): ?>
                <span class="sidebar-badge"><?= $refund_pending ?></span>
            <?php endif; ?>
        </a>

        <div class="menu-label">Tài chính Platform</div>

        <a href="/admin/finance.php"
           class="<?= $current_page === 'finance.php' ? 'active' : '' ?>">
            💰 Hoa hồng & Sao kê
        </a>

        <a href="/admin/payout.php"
           class="<?= $current_page === 'payout.php' ? 'active' : '' ?>">
            <?php
            $payout_ready = 0;
            if (isset($conn)) {
                $pr = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE payout_status='READY'");
                if ($pr) $payout_ready = (int)$pr->fetch_assoc()['c'];
            }
            ?>
            💸 Giải ngân
            <?php if($payout_ready > 0): ?>
                <span class="sidebar-badge"><?= $payout_ready ?></span>
            <?php endif; ?>
        </a>

        <a href="/admin/blog_list.php"
           class="<?= in_array($current_page, ['blog_list.php', 'blog_form.php']) ? 'active' : '' ?>">
            Bài viết
        </a>

        <a href="/admin/reviews.php"
           class="<?= $current_page === 'reviews.php' ? 'active' : '' ?>">
            Đánh giá
        </a>

        <a href="/admin/support_requests.php"
           class="<?= $current_page === 'support_requests.php' ? 'active' : '' ?>">
            Hỗ trợ
            <?php if($support_pending > 0): ?>
                <span class="sidebar-badge"><?= $support_pending ?></span>
            <?php endif; ?>
        </a>

        <?php
        $dispute_open = 0;
        if (isset($conn)) {
            $dr = $conn->query("SELECT COUNT(*) as c FROM disputes WHERE status='OPEN'");
            if ($dr) $dispute_open = (int)$dr->fetch_assoc()['c'];
        }
        ?>
        <a href="/admin/disputes.php"
           class="<?= $current_page === 'disputes.php' ? 'active' : '' ?>">
            ⚖️ Khiếu nại
            <?php if($dispute_open > 0): ?>
                <span class="sidebar-badge"><?= $dispute_open ?></span>
            <?php endif; ?>
        </a>

        <div class="menu-label">Công cụ</div>

        <a href="/admin/hotel_stats.php"
           class="<?= $current_page === 'hotel_stats.php' ? 'active' : '' ?>">
            📊 Thống kê khách sạn
        </a>

        <a href="/admin/vouchers.php"
           class="<?= $current_page === 'vouchers.php' ? 'active' : '' ?>">
            🏷️ Voucher
        </a>

        <a href="/admin/activity_log.php"
           class="<?= $current_page === 'activity_log.php' ? 'active' : '' ?>">
            📋 Nhật ký hoạt động
        </a>

        <a href="/admin/site_settings.php"
           class="<?= $current_page === 'site_settings.php' ? 'active' : '' ?>">
            ⚙️ Cài đặt hệ thống
        </a>

        <a href="/admin/admin_ai.php"
           class="<?= $current_page === 'admin_ai.php' ? 'active' : '' ?>"
           style="background:<?= $current_page === 'admin_ai.php' ? '' : 'linear-gradient(135deg,#667eea18,#764ba218)' ?>;border:1.5px solid #e9ecff;margin-top:4px">
            🤖 AI Assistant
        </a>

        <a href="/admin/payment_ai.php"
           class="<?= $current_page === 'payment_ai.php' ? 'active' : '' ?>"
           style="background:<?= $current_page === 'payment_ai.php' ? '' : 'linear-gradient(135deg,#0f244418,#1e3a5f18)' ?>;border:1.5px solid #bfdbfe;margin-top:4px">
            💳 Payment AI
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/auth/logout_admin.php">
            Đăng xuất
        </a>
    </div>
</aside>

<!-- ----------- MAIN ----------- -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1><?= $page_title ?? 'Admin' ?></h1>
            <p><?= $page_subtitle ?? date('d/m/Y') ?></p>
        </div>
        <div class="topbar-right">
            <div class="topbar-admin">
                <div class="admin-avatar"><?= $admin_avatar ?></div>
                <?= $admin_name ?>
            </div>
        </div>
    </div>

    <!-- Nội dung trang bắt đầu từ đây -->
    <div class="content">