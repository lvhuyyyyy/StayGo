-- PAYMENT FLOW MIGRATION — MySQL compatible (no IF NOT EXISTS in ALTER)
-- Dùng với mysql --force: lỗi "duplicate column" (1060) sẽ bị bỏ qua tự động

-- 1. bookings: thêm payment_flow
ALTER TABLE `bookings` ADD COLUMN `payment_flow` enum('platform_collect','hotel_collect') NOT NULL DEFAULT 'platform_collect' AFTER `payment_method`;

-- Backfill: booking cũ có payment_method='hotel' → hotel_collect
UPDATE `bookings` SET `payment_flow` = 'hotel_collect' WHERE `payment_method` = 'hotel';

-- 2. payments: bank verification columns
ALTER TABLE `payments` ADD COLUMN `bank_txn_id`      varchar(100) NULL        AFTER `payment_status`;
ALTER TABLE `payments` ADD COLUMN `verified_at`       datetime     NULL        AFTER `bank_txn_id`;
ALTER TABLE `payments` ADD COLUMN `payment_verified`  tinyint(1)   NOT NULL DEFAULT 0 AFTER `verified_at`;

-- Unique key bank_txn_id (exactly-once processing)
ALTER TABLE `payments` ADD UNIQUE KEY `uniq_bank_txn` (`bank_txn_id`);
