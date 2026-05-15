-- Tối ưu hiệu suất truy vấn bảng hotels — MySQL compatible
-- Dùng với mysql --force: lỗi "Duplicate key name" (1061) sẽ bị bỏ qua

ALTER TABLE `hotels` ADD KEY `idx_hotels_location_id` (`location_id`);
ALTER TABLE `hotels` ADD KEY `idx_hotels_is_active`   (`is_active`);
ALTER TABLE `hotels` ADD KEY `idx_hotels_rating`       (`rating`);
