<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

$booking_id = (int)($_GET['id'] ?? 0);
$user_id    = $_SESSION['user_id'];

if (!$booking_id) {
    header("Location: my_bookings.php?error=invalid");
    exit();
}

// Cho hủy booking pending hoặc confirmed thuộc về user này
$stmt = $conn->prepare("
    SELECT b.id, b.room_id, b.status, b.total_price, b.refund_requested,
           b.check_in, b.payment_method, b.payment_flow,
           COALESCE(h.cancel_free_days, 1) AS cancel_free_days
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending','confirmed')
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: my_bookings.php?error=notfound");
    exit();
}

// Không hủy nếu đã gửi yêu cầu hoàn tiền
if ((int)($booking['refund_requested'] ?? 0) > 0) {
    header("Location: my_bookings.php?error=already_requested");
    exit();
}

// Confirmed booking không được hủy nếu đã qua hạn miễn phí theo cancel_free_days của khách sạn
if ($booking['status'] === 'confirmed') {
    $free_days       = (int)($booking['cancel_free_days'] ?? 1);
    $days_to_checkin = (strtotime($booking['check_in']) - time()) / 86400;
    if ($days_to_checkin < $free_days) {
        header("Location: my_bookings.php?error=too_late");
        exit();
    }
}

$room_id = (int)$booking['room_id'];

if ($booking['status'] === 'pending') {
    // Kiểm tra xem đã có payment 'paid' chưa (bank transfer đã chuyển khoản)
    $pay_res = $conn->query("SELECT payment_status, payment_method FROM payments WHERE booking_id = $booking_id ORDER BY id DESC LIMIT 1");
    $pay_row = $pay_res ? $pay_res->fetch_assoc() : null;
    $already_paid = $pay_row && $pay_row['payment_status'] === 'paid';

    if ($already_paid) {
        // Đã thu tiền (bank transfer xác nhận) → hoàn 100%
        $refund_amt = (float)$booking['total_price'];
        $now = date('Y-m-d H:i:s');
        $stmt3 = $conn->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                refund_requested    = 1,
                refund_requested_at = ?,
                refund_amount       = ?
            WHERE id = ?
        ");
        $stmt3->bind_param("sdi", $now, $refund_amt, $booking_id);
        $stmt3->execute();
    } else {
        // Chưa thu tiền (VNPay/MoMo thất bại, bank chưa chuyển, hotel pay) → free cancel
        $conn->query("UPDATE bookings SET status = 'cancelled' WHERE id = $booking_id");
    }
} else {
    // Confirmed → kiểm tra payment_flow trước khi xử lý hoàn tiền
    if (($booking['payment_flow'] ?? '') === 'hotel_collect') {
        // Thanh toán tại quầy: platform chưa thu tiền → hủy tự do, không hoàn
        $conn->query("UPDATE bookings SET status = 'cancelled' WHERE id = $booking_id");
    } else {
        // platform_collect: đã thu tiền → hủy có phí 20%, yêu cầu admin hoàn 80%
        $refund_amount = round($booking['total_price'] * 0.8);
        $now = date('Y-m-d H:i:s');
        $stmt2 = $conn->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                refund_requested = 1,
                refund_amount = ?,
                refund_requested_at = ?
            WHERE id = ?
        ");
        $stmt2->bind_param("dsi", $refund_amount, $now, $booking_id);
        $stmt2->execute();
    }
}

header("Location: my_bookings.php?cancelled=1");
exit();

