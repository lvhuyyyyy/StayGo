-- Tạo các bảng mới của nền tảng Platform
-- Hoàn toàn idempotent: chỉ dùng CREATE TABLE IF NOT EXISTS và INSERT IGNORE

-- Booking logs (timeline mỗi đơn đặt phòng)
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

-- Payouts (lịch sử giải ngân cho hotel)
CREATE TABLE IF NOT EXISTS `payouts` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `hotel_id`              int(11) NOT NULL,
    `booking_id`            int(11) NOT NULL,
    `amount`                decimal(12,2) NOT NULL COMMENT 'Số tiền giải ngân = hotel_payout',
    `commission_amount`     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Hoa hồng platform giữ lại',
    `processed_by_admin_id` int(11) DEFAULT NULL,
    `processed_by_name`     varchar(100) DEFAULT NULL,
    `note`                  text DEFAULT NULL,
    `processed_at`          timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hotel_id` (`hotel_id`),
    KEY `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Disputes (khiếu nại từ user/guest)
CREATE TABLE IF NOT EXISTS `disputes` (
    `id`                    int(11) NOT NULL AUTO_INCREMENT,
    `booking_id`            int(11) NOT NULL,
    `user_id`               int(11) DEFAULT NULL COMMENT 'NULL nếu là Guest',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site settings (cài đặt hệ thống)
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `key`        varchar(80) NOT NULL,
    `value`      text DEFAULT NULL,
    `label`      varchar(120) NOT NULL DEFAULT '',
    `group_name` varchar(60) NOT NULL DEFAULT 'general',
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `site_settings` (`key`, `value`, `label`, `group_name`) VALUES
    ('maintenance_mode', '0', 'Chế độ bảo trì', 'system');
