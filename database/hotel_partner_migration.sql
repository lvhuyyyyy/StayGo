-- HOTEL PARTNER PORTAL MIGRATION — MySQL compatible (no IF NOT EXISTS in ALTER)
-- Dùng với mysql --force: lỗi "duplicate column" (1060) sẽ bị bỏ qua tự động

ALTER TABLE `hotels` ADD COLUMN `partner_email`    varchar(150) DEFAULT NULL COMMENT 'Email đăng nhập Hotel Partner';
ALTER TABLE `hotels` ADD COLUMN `partner_password`  varchar(255) DEFAULT NULL COMMENT 'Mật khẩu đã hash (bcrypt)';
ALTER TABLE `hotels` ADD COLUMN `contact_name`      varchar(100) DEFAULT NULL COMMENT 'Tên người liên hệ';
ALTER TABLE `hotels` ADD COLUMN `contact_phone`     varchar(20)  DEFAULT NULL COMMENT 'Số điện thoại liên hệ';
ALTER TABLE `hotels` ADD COLUMN `bank_info`         varchar(255) DEFAULT NULL COMMENT 'Thông tin ngân hàng nhận giải ngân';

-- Unique index cho partner_email (bỏ qua nếu đã tồn tại)
ALTER TABLE `hotels` ADD UNIQUE KEY `idx_partner_email` (`partner_email`);
