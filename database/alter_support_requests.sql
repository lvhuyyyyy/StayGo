-- Cập nhật bảng support_requests — idempotent (IF NOT EXISTS / IF EXISTS)

-- Cập nhật giá trị status cũ (chạy an toàn nếu không có dữ liệu cũ)
UPDATE `support_requests` SET `status` = 'pending'    WHERE `status` = 'new';
UPDATE `support_requests` SET `status` = 'processing' WHERE `status` = 'contacted';
UPDATE `support_requests` SET `status` = 'resolved'   WHERE `status` = 'done';

-- Đổi ENUM (chạy lại cũng an toàn)
ALTER TABLE `support_requests`
    MODIFY COLUMN `status` ENUM('pending','processing','resolved') NOT NULL DEFAULT 'pending';

-- Thêm cột (IF NOT EXISTS — MySQL 8.0.3+)
ALTER TABLE `support_requests`
    ADD COLUMN IF NOT EXISTS `user_id`    INT          DEFAULT NULL   AFTER `id`,
    ADD COLUMN IF NOT EXISTS `email`      VARCHAR(100) DEFAULT NULL   AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `subject`    VARCHAR(200) DEFAULT NULL   AFTER `email`,
    ADD COLUMN IF NOT EXISTS `admin_note` TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Thêm index (IF NOT EXISTS)
CREATE INDEX IF NOT EXISTS `idx_sr_user_id` ON `support_requests` (`user_id`);
CREATE INDEX IF NOT EXISTS `idx_sr_status`  ON `support_requests` (`status`);
