<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_PATH . "/auth/login.php");
    exit;
}

// Yêu cầu POST + CSRF để chống tấn công CSRF qua link GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_bookings.php?error=invalid");
    exit;
}
csrf_verify();

$user_id    = (int)$_SESSION['user_id'];   // Fix #1: cast int từ session
$booking_id = (int)($_POST['id'] ?? 0);    // Fix #1: POST thay GET

if (!$booking_id || !$user_id) {
    header("Location: my_bookings.php?error=invalid");
    exit;
}

// Fix #1: prepared statement — lấy booking + cancel_free_days + payment_flow
$stmt = $conn->prepare("
    SELECT b.id, b.status, b.total_price, b.refund_requested,
           b.check_in, b.payment_method, b.payment_flow, b.payout_status,
           COALESCE(h.cancel_free_days, 1) AS cancel_free_days
    FROM bookings b
    LEFT JOIN rooms r  ON b.room_id  = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'
");
$stmt->bind_param('ii', $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: my_bookings.php?error=notfound");
    exit;
}

// Đã có yêu cầu hoàn tiền rồi
if ((int)($booking['refund_requested'] ?? 0) > 0) {
    header("Location: my_bookings.php?error=already_requested");
    exit;
}

// Fix #2: hotel_collect — platform chưa thu tiền → không cần refund qua hệ thống
if (($booking['payment_flow'] ?? '') === 'hotel_collect') {
    header("Location: my_bookings.php?error=hotel_collect_no_refund");
    exit;
}

// Fix #2: tính refund dựa theo cancel_free_days của hotel (không hardcode 80%)
$free_days       = (int)($booking['cancel_free_days'] ?? 1);
$days_to_checkin = (strtotime($booking['check_in']) - time()) / 86400;

// Phải còn ít nhất 24h đến check-in
if ($days_to_checkin * 24 <= 24) {
    header("Location: my_bookings.php?error=too_late");
    exit;
}

if ($days_to_checkin >= $free_days) {
    $refund_amount = (float)$booking['total_price'];       // trong thời hạn miễn phí → 100%
} else {
    $refund_amount = round((float)$booking['total_price'] * 0.8, 2); // quá thời hạn → 80%
}

// Fix #1: UPDATE dùng prepared statement
$now = date('Y-m-d H:i:s');
$upd = $conn->prepare("
    UPDATE bookings
    SET refund_requested    = 1,
        refund_requested_at = ?,
        refund_amount       = ?,
        refund_status       = 'pending'
    WHERE id = ? AND refund_requested = 0
");
$upd->bind_param('sdi', $now, $refund_amount, $booking_id);
$upd->execute();

header("Location: my_bookings.php?refund=requested");
exit;
