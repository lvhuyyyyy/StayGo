-- =====================================================================
-- PLATFORM OPERATOR MIGRATION
-- Thêm các bảng/cột cần thiết cho mô hình Platform Hotel Booking
-- Chạy file này một lần trong phpMyAdmin hoặc MySQL CLI
-- =====================================================================

-- 1. Thêm commission_rate và hotel_partner_status vào bảng hotels
ALTER TABLE `hotels`
    ADD COLUMN IF NOT EXISTS `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00
        COMMENT '% hoa hồng platform thu từ hotel',
    ADD COLUMN IF NOT EXISTS `partner_status` enum('PENDING','ACTIVE','SUSPENDED','REJECTED') NOT NULL DEFAULT 'ACTIVE'
        COMMENT 'Trạng thái đối tác: ACTIVE=đang hoạt động, PENDING=chờ duyệt, SUSPENDED=tạm đình chỉ, REJECTED=từ chối',
    ADD COLUMN IF NOT EXISTS `suspend_reason` text DEFAULT NULL
        COMMENT 'Lý do suspend/reject, ghi bởi Admin';

-- Đồng bộ: hotel đang is_active=1 → ACTIVE, is_active=0 → SUSPENDED
UPDATE `hotels` SET `partner_status` = CASE
    WHEN `is_active` = 1 THEN 'ACTIVE'
    ELSE 'SUSPENDED'
END;

-- 2. Thêm các cột finance vào bảng bookings
ALTER TABLE `bookings`
    ADD COLUMN IF NOT EXISTS `commission_rate` decimal(5,2) DEFAULT NULL
        COMMENT '% hoa hồng tại thời điểm booking được tạo',
    ADD COLUMN IF NOT EXISTS `platform_revenue` decimal(12,2) DEFAULT NULL
        COMMENT 'Số tiền platform giữ lại (= total_price * commission_rate / 100)',
    ADD COLUMN IF NOT EXISTS `hotel_payout` decimal(12,2) DEFAULT NULL
        COMMENT 'Số tiền platform sẽ giải ngân cho hotel (= total_price - platform_revenue)',
    ADD COLUMN IF NOT EXISTS `payout_status` enum('HOLDING','READY','FROZEN','PAID') NOT NULL DEFAULT 'HOLDING'
        COMMENT 'HOLDING=chưa thể giải ngân, READY=sẵn sàng giải ngân, FROZEN=đang có dispute, PAID=đã giải ngân';

-- Tính ngược platform_revenue và hotel_payout cho bookings cũ (dùng commission_rate mặc định 10%)
UPDATE `bookings` b
JOIN rooms r ON b.room_id = r.id
JOIN hotels h ON r.hotel_id = h.id
SET
    b.commission_rate  = h.commission_rate,
    b.platform_revenue = ROUND(b.total_price * h.commission_rate / 100, 2),
    b.hotel_payout     = ROUND(b.total_price * (100 - h.commission_rate) / 100, 2)
WHERE b.commission_rate IS NULL AND b.total_price IS NOT NULL;

-- Booking đã completed → READY (trừ khi có refund_requested)
UPDATE `bookings`
SET payout_status = 'READY'
WHERE status = 'completed' AND payout_status = 'HOLDING' AND refund_requested = 0;

-- 3. Tạo bảng booking_logs (Timeline Log cho mỗi booking)
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

-- 4. Tạo bảng payouts (Lịch sử giải ngân cho hotel)
CREATE TABLE IF NOT EXISTS `payouts` (
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `hotel_id`            int(11) NOT NULL,
    `booking_id`          int(11) NOT NULL,
    `amount`              decimal(12,2) NOT NULL COMMENT 'Số tiền giải ngân = hotel_payout',
    `commission_amount`   decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Hoa hồng platform giữ lại',
    `processed_by_admin_id` int(11) DEFAULT NULL,
    `processed_by_name`   varchar(100) DEFAULT NULL,
    `note`                text DEFAULT NULL,
    `processed_at`        timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hotel_id` (`hotel_id`),
    KEY `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Thêm cột admin_role vào bảng users (admin dùng users table, role='admin')
-- Thêm admin_level để phân biệt Super Admin vs Support Admin
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `admin_level` enum('SUPER','SUPPORT') DEFAULT NULL
        COMMENT 'Chỉ áp dụng khi role=admin: SUPER=toàn quyền, SUPPORT=chỉ xem+dispute';

-- 6. Tạo bảng disputes (Khiếu nại từ User/Guest)
CREATE TABLE IF NOT EXISTS `disputes` (
    `id`                  int(11) NOT NULL AUTO_INCREMENT,
    `booking_id`          int(11) NOT NULL,
    `user_id`             int(11) DEFAULT NULL COMMENT 'NULL nếu là Guest',
    `guest_email`         varchar(150) DEFAULT NULL COMMENT 'Email Guest dùng để tra cứu',
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

-- =====================================================================
-- XONG. Kiểm tra bằng các lệnh sau:
-- DESCRIBE hotels;
-- DESCRIBE bookings;
-- SHOW TABLES LIKE 'booking_logs';
-- SHOW TABLES LIKE 'payouts';
-- DESCRIBE admins;
-- =====================================================================
