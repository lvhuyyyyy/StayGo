-- Tối ưu hiệu suất truy vấn bảng hotels
-- Chạy 1 lần trong phpMyAdmin hoặc MySQL CLI

ALTER TABLE `hotels` ADD INDEX `idx_hotels_location_id` (`location_id`);
ALTER TABLE `hotels` ADD INDEX `idx_hotels_is_active`   (`is_active`);
ALTER TABLE `hotels` ADD INDEX `idx_hotels_rating`      (`rating`);
