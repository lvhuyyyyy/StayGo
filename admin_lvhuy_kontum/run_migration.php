<?php
/**
 * One-time migration runner — chạy qua browser, xóa file sau khi xong.
 * Truy cập: /admin_lvhuy_kontum/run_migration.php
 * Bảo vệ: chỉ chạy khi đúng token bí mật (không cần đăng nhập admin để
 *          tránh vòng lặp khi DB chưa sẵn sàng).
 */

define('MIGRATION_TOKEN', 'staygo_migrate_2026');

if (($_GET['token'] ?? '') !== MIGRATION_TOKEN) {
    http_response_code(403);
    exit('Forbidden — add ?token=staygo_migrate_2026 to URL');
}

// Kết nối thẳng, KHÔNG qua database.php (tránh side-effect auto-complete)
mysqli_report(MYSQLI_REPORT_OFF);
$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'tour_khach_san';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die('<pre>Connection failed: ' . $conn->connect_error . '</pre>');
}
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");

// ────────────────────────────────────────────────────────────────────────────
// Danh sách SQL cần chạy (theo thứ tự, idempotent)
// ────────────────────────────────────────────────────────────────────────────
$steps = [];

// Bước 1: Tạo 3 bảng mới
$steps['booking_logs'] = "CREATE TABLE IF NOT EXISTS `booking_logs` (
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `booking_id`  int(11) NOT NULL,
    `actor_type`  enum('GUEST','USER','HOTEL','ADMIN','SYSTEM') NOT NULL DEFAULT 'ADMIN',
    `actor_id`    int(11) DEFAULT NULL,
    `actor_name`  varchar(100) DEFAULT NULL,
    `action`      varchar(80) NOT NULL,
    `description` text DEFAULT NULL,
    `metadata`    json DEFAULT NULL,
    `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_booking_id` (`booking_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$steps['payouts'] = "CREATE TABLE IF NOT EXISTS `payouts` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `hotel_id`              int(11) NOT NULL,
    `booking_id`            int(11) NOT NULL,
    `amount`                decimal(12,2) NOT NULL,
    `commission_amount`     decimal(12,2) NOT NULL DEFAULT 0.00,
    `processed_by_admin_id` int(11) DEFAULT NULL,
    `processed_by_name`     varchar(100) DEFAULT NULL,
    `note`                  text DEFAULT NULL,
    `processed_at`          timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hotel_id` (`hotel_id`),
    KEY `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$steps['disputes'] = "CREATE TABLE IF NOT EXISTS `disputes` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `booking_id`            int(11) NOT NULL,
    `user_id`               int(11) DEFAULT NULL,
    `guest_email`           varchar(150) DEFAULT NULL,
    `type`                  enum('WRONG_ROOM','INCIDENT','REFUND_REQUEST','OTHER') NOT NULL DEFAULT 'OTHER',
    `description`           text NOT NULL,
    `status`                enum('OPEN','IN_PROGRESS','RESOLVED','REJECTED') NOT NULL DEFAULT 'OPEN',
    `admin_response`        text DEFAULT NULL,
    `resolved_by_admin_id`  int(11) DEFAULT NULL,
    `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`            timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_booking_id` (`booking_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$steps['site_settings'] = "CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `key`        varchar(80) NOT NULL,
    `value`      text DEFAULT NULL,
    `label`      varchar(120) NOT NULL DEFAULT '',
    `group_name` varchar(60) NOT NULL DEFAULT 'general',
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$steps['site_settings_seed'] = "INSERT IGNORE INTO `site_settings` (`key`, `value`, `label`, `group_name`)
    VALUES ('maintenance_mode', '0', 'Chế độ bảo trì', 'system')";

// Bước 2: hotels — platform columns
$steps['hotels_commission'] = "ALTER TABLE `hotels`
    ADD COLUMN IF NOT EXISTS `commission_rate`   decimal(5,2)  NOT NULL DEFAULT 10.00,
    ADD COLUMN IF NOT EXISTS `partner_status`    enum('PENDING','ACTIVE','SUSPENDED','REJECTED') NOT NULL DEFAULT 'ACTIVE',
    ADD COLUMN IF NOT EXISTS `suspend_reason`    text          DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `partner_email`     varchar(150)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `partner_password`  varchar(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `contact_name`      varchar(100)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `contact_phone`     varchar(20)   DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `bank_info`         varchar(255)  DEFAULT NULL";

$steps['hotels_partner_email_idx'] = "ALTER TABLE `hotels`
    ADD UNIQUE INDEX IF NOT EXISTS `idx_partner_email` (`partner_email`)";

// Bước 3: bookings — finance columns
$steps['bookings_finance'] = "ALTER TABLE `bookings`
    ADD COLUMN IF NOT EXISTS `commission_rate` decimal(5,2)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `platform_revenue` decimal(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `hotel_payout`     decimal(12,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `payout_status`    enum('HOLDING','READY','FROZEN','PAID') NOT NULL DEFAULT 'HOLDING',
    ADD COLUMN IF NOT EXISTS `payment_flow`     enum('platform_collect','hotel_collect') NOT NULL DEFAULT 'platform_collect'";

// Bước 4: payments — bank verification columns
$steps['payments_bank'] = "ALTER TABLE `payments`
    ADD COLUMN IF NOT EXISTS `bank_txn_id`      varchar(100) NULL,
    ADD COLUMN IF NOT EXISTS `verified_at`      datetime     NULL,
    ADD COLUMN IF NOT EXISTS `payment_verified` tinyint(1)   NOT NULL DEFAULT 0";

$steps['payments_uniq_bank'] = "ALTER TABLE `payments`
    ADD UNIQUE KEY IF NOT EXISTS `uniq_bank_txn` (`bank_txn_id`)";

// Bước 5: users — admin_level
$steps['users_admin_level'] = "ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `admin_level` enum('SUPER','SUPPORT') DEFAULT NULL";

// Bước 6: support_requests
$steps['support_requests_cols'] = "ALTER TABLE `support_requests`
    ADD COLUMN IF NOT EXISTS `user_id`    int          DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `email`      varchar(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `subject`    varchar(200) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `admin_note` text         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `updated_at` timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

// Bước 7: index tối ưu
$steps['idx_hotels_location'] = "CREATE INDEX IF NOT EXISTS `idx_hotels_location_id` ON `hotels` (`location_id`)";
$steps['idx_hotels_active']   = "CREATE INDEX IF NOT EXISTS `idx_hotels_is_active`   ON `hotels` (`is_active`)";
$steps['idx_hotels_rating']   = "CREATE INDEX IF NOT EXISTS `idx_hotels_rating`       ON `hotels` (`rating`)";
$steps['idx_sr_user']         = "CREATE INDEX IF NOT EXISTS `idx_sr_user_id`          ON `support_requests` (`user_id`)";
$steps['idx_sr_status']       = "CREATE INDEX IF NOT EXISTS `idx_sr_status`           ON `support_requests` (`status`)";

// Bước 8: backfill partner_status từ is_active
$steps['backfill_partner_status'] = "UPDATE `hotels` SET `partner_status` = CASE
    WHEN `is_active` = 1 THEN 'ACTIVE' ELSE 'SUSPENDED' END
WHERE `partner_status` = 'ACTIVE'";

// Bước 9: backfill payment_flow cho booking cũ
$steps['backfill_payment_flow'] = "UPDATE `bookings` SET `payment_flow` = 'hotel_collect'
    WHERE `payment_method` = 'hotel' AND `payment_flow` = 'platform_collect'";

// Bước 10: backfill finance columns
$steps['backfill_finance'] = "UPDATE `bookings` b
    JOIN rooms r ON b.room_id = r.id
    JOIN hotels h ON r.hotel_id = h.id
    SET
        b.commission_rate  = h.commission_rate,
        b.platform_revenue = ROUND(b.total_price * h.commission_rate / 100, 2),
        b.hotel_payout     = ROUND(b.total_price * (100 - h.commission_rate) / 100, 2)
    WHERE b.commission_rate IS NULL AND b.total_price IS NOT NULL";

// ────────────────────────────────────────────────────────────────────────────
// Chạy từng bước và hiển thị kết quả
// ────────────────────────────────────────────────────────────────────────────
$results = [];
foreach ($steps as $name => $sql) {
    $ok  = $conn->query($sql);
    $err = $conn->error;
    $results[$name] = [
        'ok'      => (bool)$ok,
        'error'   => $err,
        'affected' => $conn->affected_rows,
    ];
}

$conn->close();

// ────────────────────────────────────────────────────────────────────────────
// Hiển thị kết quả
// ────────────────────────────────────────────────────────────────────────────
$all_ok = array_reduce($results, fn($c, $r) => $c && $r['ok'], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Migration Result</title>
<style>
body{font-family:monospace;padding:24px;background:#0f172a;color:#e2e8f0}
h2{color:#38bdf8}
.ok{color:#4ade80}
.fail{color:#f87171}
table{border-collapse:collapse;width:100%;margin-top:16px}
th,td{border:1px solid #334155;padding:8px 12px;text-align:left}
th{background:#1e293b;color:#94a3b8}
tr.ok-row td:first-child::before{content:"✅ "}
tr.fail-row td:first-child::before{content:"❌ "}
.banner{padding:12px 20px;border-radius:8px;margin-bottom:16px;font-size:1.1rem}
.banner.success{background:#14532d;color:#4ade80}
.banner.partial{background:#7c2d12;color:#fca5a5}
</style>
</head>
<body>
<h2>StayGo — Migration Runner</h2>

<div class="banner <?= $all_ok ? 'success' : 'partial' ?>">
    <?= $all_ok
        ? '✅ Tất cả bước thành công! Có thể xóa file này ngay.'
        : '⚠️ Một số bước có lỗi (xem chi tiết). Các lỗi "Duplicate column" là bình thường — bảng đã có cột rồi.' ?>
</div>

<table>
<thead><tr><th>Bước</th><th>Kết quả</th><th>Ghi chú</th></tr></thead>
<tbody>
<?php foreach ($results as $name => $r): ?>
<tr class="<?= $r['ok'] ? 'ok-row' : 'fail-row' ?>">
    <td><?= htmlspecialchars($name) ?></td>
    <td class="<?= $r['ok'] ? 'ok' : 'fail' ?>"><?= $r['ok'] ? 'OK' : 'FAIL' ?></td>
    <td><?= $r['ok']
        ? ($r['affected'] >= 0 ? "affected: {$r['affected']}" : '')
        : htmlspecialchars($r['error']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<p style="margin-top:24px;color:#64748b">
    Sau khi xác nhận OK, xóa file này:
    <code style="color:#fbbf24">admin_lvhuy_kontum/run_migration.php</code>
</p>
</body>
</html>
