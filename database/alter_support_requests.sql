-- Chạy toàn bộ trong phpMyAdmin > tab SQL

-- Bước 1: Cập nhật dữ liệu cũ sang giá trị status mới (nếu có dữ liệu)
UPDATE `support_requests` SET `status` = 'pending'    WHERE `status` = 'new';
UPDATE `support_requests` SET `status` = 'processing' WHERE `status` = 'contacted';
UPDATE `support_requests` SET `status` = 'resolved'   WHERE `status` = 'done';

-- Bước 2: Đổi ENUM của cột status
ALTER TABLE `support_requests`
    MODIFY COLUMN `status` ENUM('pending','processing','resolved') NOT NULL DEFAULT 'pending';

-- Bước 3: Thêm các cột còn thiếu
ALTER TABLE `support_requests`
    ADD COLUMN `user_id`    INT          DEFAULT NULL   AFTER `id`,
    ADD COLUMN `email`      VARCHAR(100) DEFAULT NULL   AFTER `phone`,
    ADD COLUMN `subject`    VARCHAR(200) DEFAULT NULL   AFTER `email`,
    ADD COLUMN `admin_note` TEXT         DEFAULT NULL,
    ADD COLUMN `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Bước 4: Thêm index
ALTER TABLE `support_requests`
    ADD INDEX `idx_sr_user_id` (`user_id`),
    ADD INDEX `idx_sr_status`  (`status`);
