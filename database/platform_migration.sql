-- PLATFORM OPERATOR MIGRATION — MySQL compatible (no IF NOT EXISTS in ALTER)
-- Dùng với mysql --force: lỗi "duplicate column" (1060) sẽ bị bỏ qua tự động

-- 1. hotels — commission_rate, partner_status, suspend_reason
ALTER TABLE `hotels` ADD COLUMN `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00 COMMENT '% hoa hồng platform thu từ hotel';
ALTER TABLE `hotels` ADD COLUMN `partner_status` enum('PENDING','ACTIVE','SUSPENDED','REJECTED') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Trạng thái đối tác';
ALTER TABLE `hotels` ADD COLUMN `suspend_reason` text DEFAULT NULL COMMENT 'Lý do suspend/reject';

-- Backfill partner_status từ is_active
UPDATE `hotels` SET `partner_status` = CASE WHEN `is_active` = 1 THEN 'ACTIVE' ELSE 'SUSPENDED' END;

-- 2. bookings — finance columns
ALTER TABLE `bookings` ADD COLUMN `commission_rate` decimal(5,2) DEFAULT NULL COMMENT '% hoa hồng tại thời điểm booking';
ALTER TABLE `bookings` ADD COLUMN `platform_revenue` decimal(12,2) DEFAULT NULL COMMENT 'Tiền platform giữ lại';
ALTER TABLE `bookings` ADD COLUMN `hotel_payout` decimal(12,2) DEFAULT NULL COMMENT 'Tiền giải ngân cho hotel';
ALTER TABLE `bookings` ADD COLUMN `payout_status` enum('HOLDING','READY','FROZEN','PAID') NOT NULL DEFAULT 'HOLDING' COMMENT 'Trạng thái giải ngân';

-- Backfill finance columns cho booking cũ
UPDATE `bookings` b
JOIN rooms r ON b.room_id = r.id
JOIN hotels h ON r.hotel_id = h.id
SET
    b.commission_rate  = h.commission_rate,
    b.platform_revenue = ROUND(b.total_price * h.commission_rate / 100, 2),
    b.hotel_payout     = ROUND(b.total_price * (100 - h.commission_rate) / 100, 2)
WHERE b.commission_rate IS NULL AND b.total_price IS NOT NULL;

-- Completed bookings → READY (trừ khi có refund)
UPDATE `bookings` SET payout_status = 'READY'
WHERE status = 'completed' AND payout_status = 'HOLDING' AND refund_requested = 0;

-- 3. Bảng booking_logs
CREATE TABLE IF NOT EXISTS `booking_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng payouts
CREATE TABLE IF NOT EXISTS `payouts` (
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `hotel_id`            int(11) NOT NULL,
    `booking_id`          int(11) NOT NULL,
    `amount`              decimal(12,2) NOT NULL,
    `commission_amount`   decimal(12,2) NOT NULL DEFAULT 0.00,
    `processed_by_admin_id` int(11) DEFAULT NULL,
    `processed_by_name`   varchar(100) DEFAULT NULL,
    `note`                text DEFAULT NULL,
    `processed_at`        timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hotel_id` (`hotel_id`),
    KEY `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. users — admin_level
ALTER TABLE `users` ADD COLUMN `admin_level` enum('SUPER','SUPPORT') DEFAULT NULL COMMENT 'Chỉ áp dụng khi role=admin';

-- 6. Bảng disputes
CREATE TABLE IF NOT EXISTS `disputes` (
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `booking_id`          int(11) NOT NULL,
    `user_id`             int(11) DEFAULT NULL,
    `guest_email`         varchar(150) DEFAULT NULL,
    `type`                enum('WRONG_ROOM','INCIDENT','REFUND_REQUEST','OTHER') NOT NULL DEFAULT 'OTHER',
    `description`         text NOT NULL,
    `status`              enum('OPEN','IN_PROGRESS','RESOLVED','REJECTED') NOT NULL DEFAULT 'OPEN',
    `admin_response`      text DEFAULT NULL,
    `resolved_by_admin_id` int(11) DEFAULT NULL,
    `created_at`          timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at`          timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_booking_id` (`booking_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng site_settings
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `key`        varchar(80) NOT NULL,
    `value`      text DEFAULT NULL,
    `label`      varchar(120) NOT NULL DEFAULT '',
    `group_name` varchar(60)  NOT NULL DEFAULT 'general',
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `site_settings` (`key`, `value`, `label`, `group_name`)
    VALUES ('maintenance_mode', '0', 'Chế độ bảo trì', 'system');
