-- Tối ưu hiệu suất truy vấn bảng hotels
-- Idempotent: dùng CREATE INDEX IF NOT EXISTS (MySQL 8.0+)

CREATE INDEX IF NOT EXISTS `idx_hotels_location_id` ON `hotels` (`location_id`);
CREATE INDEX IF NOT EXISTS `idx_hotels_is_active`   ON `hotels` (`is_active`);
CREATE INDEX IF NOT EXISTS `idx_hotels_rating`       ON `hotels` (`rating`);
