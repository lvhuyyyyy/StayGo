-- ================================================================
-- Restore rooms.quantity về tổng số phòng thực tế
-- Chạy 1 lần sau khi deploy date-range availability logic
-- Lý do: quantity đã bị trừ sai (không theo date) từ các booking cũ
-- ================================================================

UPDATE rooms r
SET r.quantity = r.quantity + (
    SELECT COUNT(*) FROM bookings b
    WHERE b.room_id = r.id
    AND b.status NOT IN ('cancelled')
);

-- Verify kết quả
SELECT id, room_name, quantity FROM rooms ORDER BY hotel_id, price;
