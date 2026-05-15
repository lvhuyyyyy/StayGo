<?php
session_start();
include "../config/database.php";
include "../includes/activity_helper.php";

$id     = isset($_GET['id'])     ? (int)$_GET['id']                          : 0;
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Map các giá trị cũ (paid/cancel) sang giá trị chuẩn
$status_alias = [
    'paid'       => 'confirmed',
    'cancel'     => 'cancelled',
    'confirmed'  => 'confirmed',
    'cancelled'  => 'cancelled',
    'completed'  => 'completed',
    'checked_in' => 'checked_in',
];

$status = $status_alias[$status] ?? '';

$allowed = ['confirmed', 'cancelled', 'completed', 'checked_in'];

if (!$id || !in_array($status, $allowed)) {
    header("Location: bookings.php?error=invalid");
    exit;
}

// Lấy đầy đủ thông tin booking
$booking = $conn->query("
    SELECT b.*, r.hotel_id, h.commission_rate AS h_commission_rate
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.id = $id
")->fetch_assoc();

if (!$booking) {
    header("Location: bookings.php?error=notfound");
    exit;
}

// Không cho thay đổi nếu đã hủy hoặc hoàn thành
if (in_array($booking['status'], ['cancelled', 'completed'])) {
    header("Location: bookings.php?error=already_done");
    exit;
}

$admin_id   = (int)($_SESSION['admin_id'] ?? 0);
$admin_name = $conn->real_escape_string($_SESSION['admin_name'] ?? 'Admin');

// ── Cập nhật trạng thái ──────────────────────────────────────────────
if ($status === 'completed') {
    $payment_flow = $booking['payment_flow'] ?? 'platform_collect';

    if ($payment_flow === 'hotel_collect') {
        // Hotel thu tiền trực tiếp — platform không có gì để giải ngân
        $commission_rate = 0.0;
        $platform_rev    = 0.0;
        $hotel_payout    = 0.0;

        $conn->query("
            UPDATE bookings
            SET status           = 'completed',
                commission_rate  = 0,
                platform_revenue = 0,
                hotel_payout     = 0
            WHERE id = $id
        ");
        // payout_status giữ nguyên 'HOLDING' — nghĩa là "không áp dụng"
        // (các query finance/payout đã filter payment_flow='platform_collect' nên không xuất hiện)
    } else {
        // platform_collect — tính commission bình thường
        // Ưu tiên commission_rate đã snapshot khi đặt phòng, fallback hotel rate
        $commission_rate = (float)($booking['commission_rate'] ?: $booking['h_commission_rate'] ?? 10.00);
        $total           = (float)$booking['total_price'];
        $platform_rev    = round($total * $commission_rate / 100, 2);
        $hotel_payout    = round($total - $platform_rev, 2);

        $conn->query("
            UPDATE bookings
            SET status           = 'completed',
                payout_status    = 'READY',
                commission_rate  = $commission_rate,
                platform_revenue = $platform_rev,
                hotel_payout     = $hotel_payout
            WHERE id = $id
        ");
    }

    // Trả phòng
    $room_id = (int)$booking['room_id'];
    $conn->query("UPDATE rooms SET quantity = quantity + 1 WHERE id = $room_id");

    // Ghi booking_log
    $meta_esc = $conn->real_escape_string(json_encode([
        'payment_flow'     => $payment_flow,
        'commission_rate'  => $commission_rate,
        'platform_revenue' => $platform_rev,
        'hotel_payout'     => $hotel_payout,
    ]));
    $log_desc = ($payment_flow === 'hotel_collect')
        ? 'Booking hoàn thành — hotel_collect, không giải ngân qua platform'
        : 'Booking hoàn thành — mở khoá giải ngân';
    $conn->query("
        INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description, metadata)
        VALUES ($id, 'ADMIN', $admin_id, '$admin_name', 'BOOKING_COMPLETED', '$log_desc', '$meta_esc')
    ");

} elseif ($status === 'confirmed') {
    $conn->query("UPDATE bookings SET status='confirmed' WHERE id=$id");

    $conn->query("
        INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
        VALUES ($id, 'ADMIN', $admin_id, '$admin_name', 'BOOKING_CONFIRMED', 'Admin xác nhận đơn đặt phòng')
    ");

} elseif ($status === 'checked_in') {
    $conn->query("UPDATE bookings SET status='checked_in' WHERE id=$id");

    $conn->query("
        INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
        VALUES ($id, 'ADMIN', $admin_id, '$admin_name', 'GUEST_CHECKED_IN', 'Xác nhận khách đã nhận phòng')
    ");

} elseif ($status === 'cancelled') {
    $conn->query("UPDATE bookings SET status='cancelled' WHERE id=$id");

    // Trả phòng
    if (in_array($booking['status'], ['pending', 'confirmed', 'checked_in'])) {
        $room_id = (int)$booking['room_id'];
        $conn->query("UPDATE rooms SET quantity = quantity + 1 WHERE id = $room_id");
    }

    // Nếu booking đã confirmed + platform_collect → tạo refund request 100%
    // hotel_collect: platform không giữ tiền, không cần refund qua hệ thống
    if ($booking['status'] === 'confirmed') {
        $is_prepaid = ($booking['payment_flow'] ?? 'platform_collect') === 'platform_collect';
        if ($is_prepaid && empty($booking['refund_requested'])) {
            $refund_amt = (float)$booking['total_price'];
            $now = date('Y-m-d H:i:s');
            $conn->query("
                UPDATE bookings
                SET refund_requested = 1, refund_requested_at = '$now', refund_amount = $refund_amt
                WHERE id = $id
            ");
        }
    }

    $conn->query("
        INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
        VALUES ($id, 'ADMIN', $admin_id, '$admin_name', 'BOOKING_CANCELLED', 'Admin huỷ đơn đặt phòng')
    ");
}

// Ghi nhật ký activity
$action_map = [
    'confirmed'  => 'confirm_booking',
    'cancelled'  => 'cancel_booking',
    'completed'  => 'complete_booking',
    'checked_in' => 'checkin_booking',
];
log_activity($conn, $action_map[$status] ?? 'update_booking', 'booking', $id, "Đơn #$id → $status");

// Redirect về trang chi tiết hoặc danh sách
if (isset($_GET['redirect']) && $_GET['redirect'] === 'detail') {
    header("Location: booking_detail.php?id=$id&success=$status");
} else {
    header("Location: bookings.php?success=$status");
}
exit;

