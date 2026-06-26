<?php
/**
 * One-time migration runner — compatible với MySQL chuẩn (không dùng IF NOT EXISTS trong ALTER).
 * Truy cập: /admin/run_migration.php?token=staygo_migrate_2026
 */

define('MIGRATION_TOKEN', 'staygo_migrate_2026');

if (($_GET['token'] ?? '') !== MIGRATION_TOKEN) {
    http_response_code(403);
    exit('Forbidden — add ?token=staygo_migrate_2026 to URL');
}

mysqli_report(MYSQLI_REPORT_OFF);
$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'tour_khach_san';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    die('<pre>Connection failed: ' . $conn->connect_error . '</pre>');
}
$conn->set_charset('utf8mb4');

// ─── helpers ────────────────────────────────────────────────────────────────

function colExists(mysqli $c, string $db, string $tbl, string $col): bool {
    $db  = $c->real_escape_string($db);
    $tbl = $c->real_escape_string($tbl);
    $col = $c->real_escape_string($col);
    $r = $c->query("SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$tbl' AND COLUMN_NAME='$col' LIMIT 1");
    return $r && $r->num_rows > 0;
}

function idxExists(mysqli $c, string $db, string $tbl, string $idx): bool {
    $db  = $c->real_escape_string($db);
    $tbl = $c->real_escape_string($tbl);
    $idx = $c->real_escape_string($idx);
    $r = $c->query("SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$tbl' AND INDEX_NAME='$idx' LIMIT 1");
    return $r && $r->num_rows > 0;
}

function run(mysqli $c, bool $needed, string $sql, string $skipNote = 'already exists — skipped'): array {
    if (!$needed) return ['ok' => true, 'note' => $skipNote, 'affected' => -1];
    $ok = $c->query($sql);
    return [
        'ok'       => (bool)$ok,
        'error'    => $c->error ?: '',
        'affected' => $c->affected_rows,
        'note'     => '',
    ];
}

function runAlways(mysqli $c, string $sql): array {
    $ok = $c->query($sql);
    return [
        'ok'       => (bool)$ok,
        'error'    => $c->error ?: '',
        'affected' => $c->affected_rows,
        'note'     => '',
    ];
}

// ─── steps ──────────────────────────────────────────────────────────────────
$steps = [];

// ── 1. Tạo bảng mới (CREATE TABLE IF NOT EXISTS OK trên MySQL) ──────────────

$steps['CREATE booking_logs'] = runAlways($conn,
    "CREATE TABLE IF NOT EXISTS `booking_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$steps['CREATE payouts'] = runAlways($conn,
    "CREATE TABLE IF NOT EXISTS `payouts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$steps['CREATE disputes'] = runAlways($conn,
    "CREATE TABLE IF NOT EXISTS `disputes` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$steps['CREATE site_settings'] = runAlways($conn,
    "CREATE TABLE IF NOT EXISTS `site_settings` (
        `id`         int(11) NOT NULL AUTO_INCREMENT,
        `key`        varchar(80) NOT NULL,
        `value`      text DEFAULT NULL,
        `label`      varchar(120) NOT NULL DEFAULT '',
        `group_name` varchar(60)  NOT NULL DEFAULT 'general',
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `key` (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$steps['SEED site_settings'] = runAlways($conn,
    "INSERT IGNORE INTO `site_settings` (`key`,`value`,`label`,`group_name`)
     VALUES ('maintenance_mode','0','Chế độ bảo trì','system')"
);

// ── 2. hotels — platform columns (mỗi cột 1 ALTER riêng) ───────────────────

$steps['hotels.commission_rate'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'commission_rate'),
    "ALTER TABLE `hotels` ADD COLUMN `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00"
);
$steps['hotels.partner_status'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'partner_status'),
    "ALTER TABLE `hotels` ADD COLUMN `partner_status` enum('PENDING','ACTIVE','SUSPENDED','REJECTED') NOT NULL DEFAULT 'ACTIVE'"
);
$steps['hotels.suspend_reason'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'suspend_reason'),
    "ALTER TABLE `hotels` ADD COLUMN `suspend_reason` text DEFAULT NULL"
);
$steps['hotels.partner_email'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'partner_email'),
    "ALTER TABLE `hotels` ADD COLUMN `partner_email` varchar(150) DEFAULT NULL"
);
$steps['hotels.partner_password'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'partner_password'),
    "ALTER TABLE `hotels` ADD COLUMN `partner_password` varchar(255) DEFAULT NULL"
);
$steps['hotels.contact_name'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'contact_name'),
    "ALTER TABLE `hotels` ADD COLUMN `contact_name` varchar(100) DEFAULT NULL"
);
$steps['hotels.contact_phone'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'contact_phone'),
    "ALTER TABLE `hotels` ADD COLUMN `contact_phone` varchar(20) DEFAULT NULL"
);
$steps['hotels.bank_info'] = run($conn,
    !colExists($conn, $dbname, 'hotels', 'bank_info'),
    "ALTER TABLE `hotels` ADD COLUMN `bank_info` varchar(255) DEFAULT NULL"
);

// ── 3. bookings — finance columns ──────────────────────────────────────────

$steps['bookings.commission_rate'] = run($conn,
    !colExists($conn, $dbname, 'bookings', 'commission_rate'),
    "ALTER TABLE `bookings` ADD COLUMN `commission_rate` decimal(5,2) DEFAULT NULL"
);
$steps['bookings.platform_revenue'] = run($conn,
    !colExists($conn, $dbname, 'bookings', 'platform_revenue'),
    "ALTER TABLE `bookings` ADD COLUMN `platform_revenue` decimal(12,2) DEFAULT NULL"
);
$steps['bookings.hotel_payout'] = run($conn,
    !colExists($conn, $dbname, 'bookings', 'hotel_payout'),
    "ALTER TABLE `bookings` ADD COLUMN `hotel_payout` decimal(12,2) DEFAULT NULL"
);
$steps['bookings.payout_status'] = run($conn,
    !colExists($conn, $dbname, 'bookings', 'payout_status'),
    "ALTER TABLE `bookings` ADD COLUMN `payout_status` enum('HOLDING','READY','FROZEN','PAID') NOT NULL DEFAULT 'HOLDING'"
);
$steps['bookings.payment_flow'] = run($conn,
    !colExists($conn, $dbname, 'bookings', 'payment_flow'),
    "ALTER TABLE `bookings` ADD COLUMN `payment_flow` enum('platform_collect','hotel_collect') NOT NULL DEFAULT 'platform_collect'"
);

// ── 4. payments — bank verification ────────────────────────────────────────

$steps['payments.bank_txn_id'] = run($conn,
    !colExists($conn, $dbname, 'payments', 'bank_txn_id'),
    "ALTER TABLE `payments` ADD COLUMN `bank_txn_id` varchar(100) NULL"
);
$steps['payments.verified_at'] = run($conn,
    !colExists($conn, $dbname, 'payments', 'verified_at'),
    "ALTER TABLE `payments` ADD COLUMN `verified_at` datetime NULL"
);
$steps['payments.payment_verified'] = run($conn,
    !colExists($conn, $dbname, 'payments', 'payment_verified'),
    "ALTER TABLE `payments` ADD COLUMN `payment_verified` tinyint(1) NOT NULL DEFAULT 0"
);

// ── 5. users — admin_level ──────────────────────────────────────────────────

$steps['users.admin_level'] = run($conn,
    !colExists($conn, $dbname, 'users', 'admin_level'),
    "ALTER TABLE `users` ADD COLUMN `admin_level` enum('SUPER','SUPPORT') DEFAULT NULL"
);

// ── 6. support_requests ─────────────────────────────────────────────────────

$steps['support_requests.user_id'] = run($conn,
    !colExists($conn, $dbname, 'support_requests', 'user_id'),
    "ALTER TABLE `support_requests` ADD COLUMN `user_id` int DEFAULT NULL"
);
$steps['support_requests.email'] = run($conn,
    !colExists($conn, $dbname, 'support_requests', 'email'),
    "ALTER TABLE `support_requests` ADD COLUMN `email` varchar(100) DEFAULT NULL"
);
$steps['support_requests.subject'] = run($conn,
    !colExists($conn, $dbname, 'support_requests', 'subject'),
    "ALTER TABLE `support_requests` ADD COLUMN `subject` varchar(200) DEFAULT NULL"
);
$steps['support_requests.admin_note'] = run($conn,
    !colExists($conn, $dbname, 'support_requests', 'admin_note'),
    "ALTER TABLE `support_requests` ADD COLUMN `admin_note` text DEFAULT NULL"
);
$steps['support_requests.updated_at'] = run($conn,
    !colExists($conn, $dbname, 'support_requests', 'updated_at'),
    "ALTER TABLE `support_requests` ADD COLUMN `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

// ── 7. Indexes (mỗi index riêng) ────────────────────────────────────────────

$steps['idx hotels.location_id'] = run($conn,
    !idxExists($conn, $dbname, 'hotels', 'idx_hotels_location_id'),
    "CREATE INDEX `idx_hotels_location_id` ON `hotels` (`location_id`)"
);
$steps['idx hotels.is_active'] = run($conn,
    !idxExists($conn, $dbname, 'hotels', 'idx_hotels_is_active'),
    "CREATE INDEX `idx_hotels_is_active` ON `hotels` (`is_active`)"
);
$steps['idx hotels.rating'] = run($conn,
    !idxExists($conn, $dbname, 'hotels', 'idx_hotels_rating'),
    "CREATE INDEX `idx_hotels_rating` ON `hotels` (`rating`)"
);
$steps['idx hotels.partner_email (unique)'] = run($conn,
    !idxExists($conn, $dbname, 'hotels', 'idx_partner_email'),
    "CREATE UNIQUE INDEX `idx_partner_email` ON `hotels` (`partner_email`)"
);
$steps['idx payments.uniq_bank_txn'] = run($conn,
    !idxExists($conn, $dbname, 'payments', 'uniq_bank_txn'),
    "CREATE UNIQUE INDEX `uniq_bank_txn` ON `payments` (`bank_txn_id`)"
);
$steps['idx support_requests.user_id'] = run($conn,
    !idxExists($conn, $dbname, 'support_requests', 'idx_sr_user_id'),
    "CREATE INDEX `idx_sr_user_id` ON `support_requests` (`user_id`)"
);
$steps['idx support_requests.status'] = run($conn,
    !idxExists($conn, $dbname, 'support_requests', 'idx_sr_status'),
    "CREATE INDEX `idx_sr_status` ON `support_requests` (`status`)"
);

// ── 8. Backfill (chỉ chạy nếu cột vừa được thêm) ───────────────────────────

$steps['backfill hotels.partner_status'] = runAlways($conn,
    "UPDATE `hotels` SET `partner_status` = CASE
        WHEN `is_active` = 1 THEN 'ACTIVE' ELSE 'SUSPENDED' END
     WHERE `partner_status` = 'ACTIVE'"
);
$steps['backfill bookings.payment_flow'] = runAlways($conn,
    "UPDATE `bookings` SET `payment_flow` = 'hotel_collect'
     WHERE `payment_method` = 'hotel' AND `payment_flow` = 'platform_collect'"
);
$steps['backfill bookings finance'] = runAlways($conn,
    "UPDATE `bookings` b
     JOIN rooms r  ON b.room_id   = r.id
     JOIN hotels h ON r.hotel_id  = h.id
     SET
         b.commission_rate  = h.commission_rate,
         b.platform_revenue = ROUND(b.total_price * h.commission_rate / 100, 2),
         b.hotel_payout     = ROUND(b.total_price * (100 - h.commission_rate) / 100, 2)
     WHERE b.commission_rate IS NULL AND b.total_price IS NOT NULL"
);

$conn->close();

// ─── render ─────────────────────────────────────────────────────────────────
$real_fails = array_filter($steps, fn($r) => !$r['ok']);
$skipped    = array_filter($steps, fn($r) => $r['ok'] && ($r['affected'] ?? 0) === -1);
$all_ok     = count($real_fails) === 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Migration Result</title>
<style>
body{font-family:monospace;padding:24px;background:#0f172a;color:#e2e8f0}
h2{color:#38bdf8}
table{border-collapse:collapse;width:100%;margin-top:16px}
th,td{border:1px solid #334155;padding:8px 12px;text-align:left;font-size:.85rem}
th{background:#1e293b;color:#94a3b8}
.ok{color:#4ade80}.fail{color:#f87171}.skip{color:#64748b}
.banner{padding:12px 20px;border-radius:8px;margin-bottom:16px;font-size:1rem}
.banner.success{background:#14532d;color:#4ade80}
.banner.partial{background:#7c2d12;color:#fca5a5}
</style>
</head>
<body>
<h2>StayGo — Migration Runner</h2>

<div class="banner <?= $all_ok ? 'success' : 'partial' ?>">
    <?= $all_ok
        ? '✅ Tất cả thành công! Xóa file này ngay sau khi xác nhận dashboard/payment hoạt động.'
        : '❌ Có ' . count($real_fails) . ' bước thất bại — xem chi tiết bên dưới.' ?>
</div>

<table>
<thead><tr><th>Bước</th><th>Kết quả</th><th>Ghi chú</th></tr></thead>
<tbody>
<?php foreach ($steps as $name => $r):
    if (!$r['ok']) {
        $cls = 'fail'; $icon = '❌'; $note = htmlspecialchars($r['error']);
    } elseif (($r['affected'] ?? 0) === -1) {
        $cls = 'skip'; $icon = '⏭'; $note = $r['note'];
    } else {
        $cls = 'ok';   $icon = '✅'; $note = $r['affected'] >= 0 ? "affected: {$r['affected']}" : 'OK';
    }
?>
<tr>
    <td><?= $icon ?> <?= htmlspecialchars($name) ?></td>
    <td class="<?= $cls ?>"><?= $r['ok'] ? ($r['affected'] === -1 ? 'SKIP' : 'OK') : 'FAIL' ?></td>
    <td><?= $note ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<p style="margin-top:20px;color:#64748b;font-size:.8rem">
    Sau khi OK, xóa: <code style="color:#fbbf24">admin/run_migration.php</code>
</p>
</body>
</html>
