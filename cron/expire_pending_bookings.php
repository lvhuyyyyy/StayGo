<?php
/**
 * Cron job: tự động huỷ (expire) các booking pending quá TTL mà không thanh toán.
 *
 * TTL mặc định:
 *   - Online (momo/vnpay/payos/card): 30 phút  — phải thanh toán ngay
 *   - Bank transfer:                  48 giờ   — cần thời gian chuyển khoản
 *   - Hotel collect:                  48 giờ   — chờ khách sạn xác nhận
 *
 * Cách chạy:
 *   CLI (XAMPP / Windows Task Scheduler / Linux crontab):
 *     php C:\xampp\htdocs\tour_khach_san_project\cron\expire_pending_bookings.php
 *     0,10,20,30,40,50 * * * * php /var/www/html/cron/expire_pending_bookings.php >> /var/log/staygo_cron.log 2>&1
 *
 *   HTTP (Railway Cron Service hoặc cron-job.org — dùng token bảo vệ):
 *     GET https://your-app.railway.app/cron/expire_pending_bookings.php?token=CRON_SECRET
 *     Header: X-Cron-Token: CRON_SECRET
 *
 *   Railway Variables cần set:
 *     CRON_SECRET = <random 32+ char string>
 *
 * Booking bị expire → status = 'cancelled', booking_log action = 'BOOKING_EXPIRED'
 * Booking có payment_status='paid' trong bảng payments → KHÔNG bị expire (đã thanh toán).
 */

define('CRON_START', microtime(true));

// ── Auth: CLI hoặc HTTP với token hợp lệ ─────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secrets.php';

if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    if (!hash_equals(CRON_SECRET, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json');
}

$is_cli = (php_sapi_name() === 'cli');

$log = function(string $msg) use ($is_cli) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($is_cli) {
        echo $line . PHP_EOL;
    } else {
        error_log($line);
    }
};

$log('=== expire_pending_bookings START ===');

// ── Lấy pending bookings quá TTL và chưa có payment paid ─────────────────────
$candidates = $conn->query("
    SELECT b.id, b.order_code, b.payment_method, b.payment_flow
    FROM bookings b
    WHERE b.status = 'pending'
      AND (
            (b.payment_method IN ('momo','vnpay','payos','card')
             AND b.created_at < NOW() - INTERVAL 30 MINUTE)
         OR
            (b.payment_method IN ('bank','hotel')
             AND b.created_at < NOW() - INTERVAL 48 HOUR)
      )
      AND NOT EXISTS (
            SELECT 1 FROM payments p
            WHERE p.booking_id = b.id AND p.payment_status = 'paid'
      )
    ORDER BY b.created_at ASC
");

if (!$candidates) {
    $log('Query error: ' . $conn->error);
    if (!$is_cli) echo json_encode(['ok' => false, 'error' => $conn->error]);
    exit(1);
}

$expired_ids  = [];
$expired_info = [];

while ($row = $candidates->fetch_assoc()) {
    $expired_ids[]  = (int)$row['id'];
    $expired_info[] = $row;
}

$count = 0;

if (!empty($expired_ids)) {
    $ids_str = implode(',', $expired_ids);

    $conn->begin_transaction();

    // Đặt status = cancelled — tất cả overbooking queries đã exclude 'cancelled'
    $conn->query("UPDATE bookings SET status = 'cancelled' WHERE id IN ($ids_str) AND status = 'pending'");
    $count = $conn->affected_rows;

    foreach ($expired_ids as $bid) {
        $conn->query("
            INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
            VALUES ($bid, 'SYSTEM', 0, 'CronJob',
                    'BOOKING_EXPIRED',
                    'Tự động huỷ: không thanh toán trong thời gian quy định')
        ");
    }

    $conn->commit();

    foreach ($expired_info as $info) {
        $log("  Expired #{$info['order_code']} ({$info['payment_method']}/{$info['payment_flow']})");
    }
}

$elapsed = round(microtime(true) - CRON_START, 3);
$log("=== DONE: {$count} booking(s) expired in {$elapsed}s ===");

if (!$is_cli) {
    echo json_encode([
        'ok'      => true,
        'expired' => $count,
        'time_s'  => $elapsed,
        'run_at'  => date('Y-m-d H:i:s'),
    ]);
}
