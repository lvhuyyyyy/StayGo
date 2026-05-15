-- Thêm cancel_free_days vào bảng hotels
-- Ý nghĩa: số ngày trước check_in được hủy miễn phí
-- 0 = không cho hủy miễn phí, 1 = hủy trước 1 ngày, 3 = trước 3 ngày, v.v.
ALTER TABLE hotels
    ADD COLUMN IF NOT EXISTS cancel_free_days TINYINT UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Số ngày trước check_in được hủy miễn phí. 0 = không miễn phí';

-- Cập nhật giá trị mặc định cho khách sạn đã có (giữ nguyên 1 ngày như logic cũ)
UPDATE hotels SET cancel_free_days = 1 WHERE cancel_free_days IS NULL;
