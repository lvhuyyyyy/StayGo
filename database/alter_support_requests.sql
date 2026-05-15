-- support_requests migration — MySQL compatible (no IF NOT EXISTS in ALTER)
-- Dùng với mysql --force

-- Cập nhật status cũ
UPDATE `support_requests` SET `status` = 'pending'    WHERE `status` = 'new';
UPDATE `support_requests` SET `status` = 'processing' WHERE `status` = 'contacted';
UPDATE `support_requests` SET `status` = 'resolved'   WHERE `status` = 'done';

-- Đổi ENUM
ALTER TABLE `support_requests`
    MODIFY COLUMN `status` ENUM('pending','processing','resolved') NOT NULL DEFAULT 'pending';

-- Thêm cột (mỗi cột riêng để --force bỏ qua từng lỗi duplicate)
ALTER TABLE `support_requests` ADD COLUMN `user_id`    int          DEFAULT NULL;
ALTER TABLE `support_requests` ADD COLUMN `email`      varchar(100) DEFAULT NULL;
ALTER TABLE `support_requests` ADD COLUMN `subject`    varchar(200) DEFAULT NULL;
ALTER TABLE `support_requests` ADD COLUMN `admin_note` text         DEFAULT NULL;
ALTER TABLE `support_requests` ADD COLUMN `updated_at` timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Indexes
ALTER TABLE `support_requests` ADD KEY `idx_sr_user_id` (`user_id`);
ALTER TABLE `support_requests` ADD KEY `idx_sr_status`  (`status`);
