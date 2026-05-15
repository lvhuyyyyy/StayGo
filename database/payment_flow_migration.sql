-- =====================================================================
-- Migration: payment_flow architecture
-- Mục đích:
--   1. Tách 2 luồng thu tiền rõ ràng: platform_collect vs hotel_collect
--   2. Ghi nhận transaction ngân hàng (bank_txn_id, verified_at) từ Casso
-- Chạy một lần trong phpMyAdmin hoặc MySQL CLI
-- =====================================================================

-- 1. bookings: thêm cột payment_flow
ALTER TABLE bookings
    ADD COLUMN payment_flow ENUM('platform_collect','hotel_collect')
        NOT NULL DEFAULT 'platform_collect'
        AFTER payment_method;

-- Backfill: booking cũ có payment_method='hotel' → hotel_collect
-- Tất cả còn lại → platform_collect (đã là DEFAULT)
UPDATE bookings
SET    payment_flow = 'hotel_collect',
       commission_rate = 0
WHERE  payment_method = 'hotel';

-- 2. payments: thêm bank_txn_id và verified_at
ALTER TABLE payments
    ADD COLUMN bank_txn_id  VARCHAR(100) NULL AFTER payment_status,
    ADD COLUMN verified_at  DATETIME     NULL AFTER bank_txn_id;

-- Index để tra cứu nhanh theo transaction id
ALTER TABLE payments
    ADD INDEX idx_bank_txn_id (bank_txn_id);
