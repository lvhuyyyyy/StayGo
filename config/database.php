<?php
// Múi giờ Việt Nam cho toàn bộ ứng dụng
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Dùng biến môi trường nếu có (Railway), fallback về localhost (XAMPP)
$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'tour_khach_san';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

// PHP 8.1+ throws mysqli_sql_exception by default — restore pre-8.1 behaviour so
// @$conn->query() and prepare() return false on error instead of throwing.
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
// Đồng bộ múi giờ MySQL với PHP (UTC+7)
$conn->query("SET time_zone = '+07:00'");

// One-time migration: thêm cancel_free_days nếu chưa có
if (!$conn->query("SELECT cancel_free_days FROM hotels LIMIT 0")) {
    $conn->query("ALTER TABLE hotels ADD COLUMN cancel_free_days TINYINT UNSIGNED NOT NULL DEFAULT 1");
}

// P1: Dispute window — HOLDING chỉ chuyển READY sau N ngày hết dispute
// default 7 ngày, có thể cấu hình per-hotel qua dispute_window_days
if (!$conn->query("SELECT dispute_window_days FROM hotels LIMIT 0")) {
    $conn->query("ALTER TABLE hotels ADD COLUMN dispute_window_days TINYINT UNSIGNED NOT NULL DEFAULT 7");
}

// P2: Refund lifecycle — refund_status: none | pending | approved | rejected | processed
if (!$conn->query("SELECT refund_status FROM bookings LIMIT 0")) {
    $conn->query("ALTER TABLE bookings ADD COLUMN refund_status ENUM('none','pending','approved','rejected','processed') NOT NULL DEFAULT 'none'");
    // Backfill: refund_requested=1 → refund_status='pending'
    $conn->query("UPDATE bookings SET refund_status='pending' WHERE refund_requested=1 AND refund_status='none'");
}

// Auto-hoàn thành: confirmed + check_out qua dispute window → completed + tính payout
// Dispute window = dispute_window_days ở hotels (default 7 ngày)
$conn->query("
    UPDATE bookings b
    JOIN rooms r  ON b.room_id  = r.id
    JOIN hotels h ON r.hotel_id = h.id
    SET b.status           = 'completed',
        b.payout_status    = CASE WHEN COALESCE(b.payment_flow,'platform_collect') = 'hotel_collect' THEN 'HOLDING' ELSE 'READY' END,
        b.commission_rate  = CASE WHEN COALESCE(b.payment_flow,'platform_collect') = 'hotel_collect' THEN 0 ELSE COALESCE(b.commission_rate, h.commission_rate, 10) END,
        b.platform_revenue = CASE WHEN COALESCE(b.payment_flow,'platform_collect') = 'hotel_collect' THEN 0 ELSE ROUND(b.total_price * COALESCE(b.commission_rate, h.commission_rate, 10) / 100, 2) END,
        b.hotel_payout     = CASE WHEN COALESCE(b.payment_flow,'platform_collect') = 'hotel_collect' THEN 0 ELSE ROUND(b.total_price * (1 - COALESCE(b.commission_rate, h.commission_rate, 10) / 100), 2) END
    WHERE b.status    = 'confirmed'
      AND b.check_out IS NOT NULL AND b.check_in IS NOT NULL
      AND b.check_out > b.check_in
      AND DATE_ADD(b.check_out, INTERVAL COALESCE(h.dispute_window_days, 7) DAY) < NOW()
");

// Booking 'completed' nhưng payout_status vẫn HOLDING → tính lại commission + READY
// (sau dispute window tính từ check_out)
$conn->query("
    UPDATE bookings b
    JOIN rooms r  ON b.room_id  = r.id
    JOIN hotels h ON r.hotel_id = h.id
    SET b.commission_rate  = COALESCE(NULLIF(b.commission_rate, 0), h.commission_rate, 10),
        b.platform_revenue = ROUND(b.total_price * COALESCE(NULLIF(b.commission_rate, 0), h.commission_rate, 10) / 100, 2),
        b.hotel_payout     = ROUND(b.total_price * (1 - COALESCE(NULLIF(b.commission_rate, 0), h.commission_rate, 10) / 100), 2),
        b.payout_status    = 'READY'
    WHERE b.status        = 'completed'
      AND b.payout_status = 'HOLDING'
      AND b.payment_flow  = 'platform_collect'
      AND DATE_ADD(b.check_out, INTERVAL COALESCE(h.dispute_window_days, 7) DAY) < NOW()
");

// P2: Auto-approve hoàn tiền 100% (không phí hủy = không cần admin quyết định)
$conn->query("
    UPDATE bookings
    SET refund_status = 'approved'
    WHERE refund_requested = 1
      AND refund_status    = 'pending'
      AND refund_amount    = total_price
      AND status           = 'cancelled'
");

require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/../includes/activity_helper.php';
