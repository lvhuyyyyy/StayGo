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

// One-time migration: thêm cancel_free_days nếu chưa có (tương thích mọi MySQL version)
if (!$conn->query("SELECT cancel_free_days FROM hotels LIMIT 0")) {
    $conn->query("ALTER TABLE hotels ADD COLUMN cancel_free_days TINYINT UNSIGNED NOT NULL DEFAULT 1");
}

// Auto-hoàn thành: confirmed + check_out đã qua → completed + tính payout
// Dùng JOIN để lấy commission_rate từ hotel, tính hotel_payout và platform_revenue
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
      AND b.check_out IS NOT NULL
      AND b.check_in  IS NOT NULL
      AND b.check_out > b.check_in
      AND b.check_out < CURDATE()
");

require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/../includes/activity_helper.php';
