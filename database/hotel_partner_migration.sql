-- =====================================================================
-- HOTEL PARTNER PORTAL MIGRATION
-- Thêm thông tin đăng nhập cho Hotel Partner
-- Chạy file này một lần trong phpMyAdmin hoặc MySQL CLI
-- =====================================================================

-- Thêm cột đăng nhập và thông tin liên hệ vào bảng hotels
ALTER TABLE `hotels`
    ADD COLUMN IF NOT EXISTS `partner_email` varchar(150) DEFAULT NULL
        COMMENT 'Email đăng nhập của Hotel Partner',
    ADD COLUMN IF NOT EXISTS `partner_password` varchar(255) DEFAULT NULL
        COMMENT 'Mật khẩu đã hash (bcrypt) của Hotel Partner',
    ADD COLUMN IF NOT EXISTS `contact_name` varchar(100) DEFAULT NULL
        COMMENT 'Tên người liên hệ / quản lý khách sạn',
    ADD COLUMN IF NOT EXISTS `contact_phone` varchar(20) DEFAULT NULL
        COMMENT 'Số điện thoại liên hệ',
    ADD COLUMN IF NOT EXISTS `bank_info` varchar(255) DEFAULT NULL
        COMMENT 'Thông tin ngân hàng để nhận giải ngân (STK - Tên NH - Tên TK)';

-- Tạo unique index cho partner_email
ALTER TABLE `hotels`
    ADD UNIQUE INDEX IF NOT EXISTS `idx_partner_email` (`partner_email`);

-- =====================================================================
-- Sau khi chạy migration, admin có thể set tài khoản hotel qua:
-- UPDATE hotels SET
--   partner_email = 'hotel@example.com',
--   partner_password = '$2y$10$...',  -- dùng password_hash() của PHP
--   contact_name = 'Nguyễn Văn A'
-- WHERE id = 1;
--
-- Hoặc dùng trang admin: /admin_lvhuy_kontum/manage_hotel.php
-- =====================================================================
