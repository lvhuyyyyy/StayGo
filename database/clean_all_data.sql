SET NAMES utf8mb4;
USE tour_khach_san;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE activity_log;
TRUNCATE TABLE blog_posts;
TRUNCATE TABLE booking_logs;
TRUNCATE TABLE bookings;
TRUNCATE TABLE disputes;
TRUNCATE TABLE favorites;
TRUNCATE TABLE hotel_images;
TRUNCATE TABLE hotels;
TRUNCATE TABLE payments;
TRUNCATE TABLE payouts;
TRUNCATE TABLE places;
TRUNCATE TABLE reviews;
TRUNCATE TABLE rooms;
TRUNCATE TABLE support_requests;
TRUNCATE TABLE vouchers;

-- Giữ lại locations (Đà Nẵng, Vũng Tàu, Phan Thiết) vừa cập nhật

-- Xóa users test, chỉ giữ admin (id=5) và tài khoản thật (id=9)
DELETE FROM users WHERE id NOT IN (5, 9);

SET FOREIGN_KEY_CHECKS = 1;

-- Kiểm tra kết quả
SELECT 'hotels' AS tbl, COUNT(*) AS total FROM hotels
UNION ALL SELECT 'rooms', COUNT(*) FROM rooms
UNION ALL SELECT 'bookings', COUNT(*) FROM bookings
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'locations', COUNT(*) FROM locations;
