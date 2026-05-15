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

// Auto-hoàn thành: confirmed + check_out đã qua → completed, đồng thời trả lại số phòng
// Guard: chỉ xử lý booking có ngày hợp lệ (check_out IS NOT NULL AND check_out > check_in)
// để tránh side-effect từ dữ liệu bẩn
$conn->query("
    UPDATE bookings b
    JOIN rooms r ON b.room_id = r.id
    SET b.status = 'completed',
        r.quantity = r.quantity + 1
    WHERE b.status = 'confirmed'
      AND b.check_out IS NOT NULL
      AND b.check_in IS NOT NULL
      AND b.check_out > b.check_in
      AND b.check_out < CURDATE()
");

require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/../includes/activity_helper.php';
