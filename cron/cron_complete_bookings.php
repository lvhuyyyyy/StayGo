<?php
/**
 * Cron job: tự động hoàn thành booking khi check_out đã qua.
 *
 * Chạy mỗi đêm lúc 00:05 (sau nửa đêm để bắt đúng ngày):
 *   XAMPP (Windows Task Scheduler):
 *     php C:\xampp\htdocs\tour_khach_san_project\cron\cron_complete_bookings.php
 *
 *   Linux/Railway (crontab -e):
 *     5 0 * * * php /var/www/html/cron/cron_complete_bookings.php >> /var/log/staygo_cron.log 2>&1
 *
 * Tại sao tách khỏi database.php?
 *   - HTTP request ≠ background business processing
 *   - Page load không nên mutate DB với side-effect ẩn
 *   - Dễ audit, dễ debug, dễ disable khi cần
 */

// Chỉ cho phép chạy từ CLI (không cho gọi qua browser)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

define('CRON_START', microtime(true));

require_once __DIR__ . '/../config/database.php';

$log = function(string $msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

$log('=== cron_complete_bookings START ===');

// ── 1. Lấy danh sách booking cần auto-complete ─────────────────────────
$candidates = $conn->query("
    SELECT b.id, b.order_code, b.room_id, b.total_price,
           b.payment_flow, b.commission_rate,
           h.commission_rate AS h_commission_rate
    FROM bookings b
    JOIN rooms r   ON b.room_id   = r.id
    JOIN hotels h  ON r.hotel_id  = h.id
    WHERE b.status     = 'confirmed'
      AND b.check_out  IS NOT NULL
      AND b.check_in   IS NOT NULL
      AND b.check_out  > b.check_in
      AND b.check_out  < CURDATE()
");

if (!$candidates) {
    $log('Query error: ' . $conn->error);
    exit(1);
}

$count = 0;

while ($b = $candidates->fetch_assoc()) {
    $id           = (int)$b['id'];
    $room_id      = (int)$b['room_id'];
    $payment_flow = $b['payment_flow'] ?? 'platform_collect';
    $total        = (float)$b['total_price'];

    if ($payment_flow === 'hotel_collect') {
        // Hotel thu tiền trực tiếp — không giải ngân qua platform
        $conn->query("
            UPDATE bookings
            SET status           = 'completed',
                commission_rate  = 0,
                platform_revenue = 0,
                hotel_payout     = 0
            WHERE id = $id
        ");
        $log("  [hotel_collect] Completed booking #{$b['order_code']} (commission=0, no payout)");
    } else {
        // platform_collect — tính commission, mở khoá giải ngân
        $rate         = (float)($b['commission_rate'] ?: $b['h_commission_rate'] ?? 10.0);
        $platform_rev = round($total * $rate / 100, 2);
        $hotel_payout = round($total - $platform_rev, 2);

        $conn->query("
            UPDATE bookings
            SET status           = 'completed',
                payout_status    = 'READY',
                commission_rate  = $rate,
                platform_revenue = $platform_rev,
                hotel_payout     = $hotel_payout
            WHERE id = $id
        ");
        $log("  [platform_collect] Completed booking #{$b['order_code']} | payout={$hotel_payout}đ | commission={$platform_rev}đ");
    }

    // Ghi booking_log
    $meta = $conn->real_escape_string(json_encode([
        'payment_flow'    => $payment_flow,
        'auto_completed'  => true,
        'cron_run_at'     => date('Y-m-d H:i:s'),
    ]));
    $conn->query("
        INSERT INTO booking_logs (booking_id, actor_type, actor_name, action, description, metadata)
        VALUES ($id, 'SYSTEM', 'Cron', 'BOOKING_COMPLETED',
                'Auto-hoàn thành bởi cron job (check_out đã qua)', '$meta')
    ");

    $count++;
}

$elapsed = round(microtime(true) - CRON_START, 3);
$log("=== DONE: {$count} booking(s) completed in {$elapsed}s ===");
