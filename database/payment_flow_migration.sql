-- =====================================================================
-- Migration: payment_flow architecture
-- Mục đích:
--   1. Tách 2 luồng thu tiền rõ ràng: platform_collect vs hotel_collect
--   2. Ghi nhận transaction ngân hàng (bank_txn_id, verified_at) từ Casso
-- Chạy một lần trong phpMyAdmin hoặc MySQL CLI
-- =====================================================================

-- 1. bookings: thêm cột payment_flow
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS payment_flow ENUM('platform_collect','hotel_collect')
        NOT NULL DEFAULT 'platform_collect'
        AFTER payment_method;

-- Backfill: booking cũ có payment_method='hotel' → hotel_collect
-- Tất cả còn lại → platform_collect (đã là DEFAULT)
UPDATE bookings
SET    payment_flow = 'hotel_collect',
       commission_rate = 0
WHERE  payment_method = 'hotel';

-- 2. payments: thêm bank_txn_id, verified_at, payment_verified
ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS bank_txn_id       VARCHAR(100) NULL        AFTER payment_status,
    ADD COLUMN IF NOT EXISTS verified_at       DATETIME     NULL        AFTER bank_txn_id,
    ADD COLUMN IF NOT EXISTS payment_verified  TINYINT(1)   NOT NULL DEFAULT 0 AFTER verified_at;

-- UNIQUE key: đảm bảo mỗi bank transaction chỉ xử lý đúng 1 lần (exactly-once)
-- Dùng IF NOT EXISTS để migration idempotent
ALTER TABLE payments
    ADD UNIQUE KEY IF NOT EXISTS uniq_bank_txn (bank_txn_id);
