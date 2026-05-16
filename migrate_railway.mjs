import mysql from 'mysql2/promise';

const conn = await mysql.createConnection({
  host: 'trolley.proxy.rlwy.net', port: 38045,
  user: 'root', password: 'FAfebXOgloLEEFJhUDTlrFgdzwNPyLhR',
  database: 'railway', ssl: { rejectUnauthorized: false }
});

// #1: min_stay, max_stay cho rooms
const [cols] = await conn.query("SHOW COLUMNS FROM rooms LIKE 'min_stay'");
if (cols.length === 0) {
  await conn.query("ALTER TABLE rooms ADD COLUMN min_stay INT NOT NULL DEFAULT 1 AFTER quantity");
  await conn.query("ALTER TABLE rooms ADD COLUMN max_stay INT NULL DEFAULT NULL AFTER min_stay");
  console.log('✅ rooms: added min_stay, max_stay');
} else {
  console.log('ℹ️  rooms: min_stay already exists');
}

// #2: hotel_id + type cho support_requests
const [cols2] = await conn.query("SHOW COLUMNS FROM support_requests LIKE 'hotel_id'");
if (cols2.length === 0) {
  await conn.query("ALTER TABLE support_requests ADD COLUMN hotel_id INT NULL DEFAULT NULL AFTER user_id");
  await conn.query("ALTER TABLE support_requests ADD COLUMN type ENUM('user','hotel') NOT NULL DEFAULT 'user' AFTER hotel_id");
  console.log('✅ support_requests: added hotel_id, type');
} else {
  console.log('ℹ️  support_requests: hotel_id already exists');
}

// #3: payment_flow cho bookings
const [cols3] = await conn.query("SHOW COLUMNS FROM bookings LIKE 'payment_flow'");
if (cols3.length === 0) {
  await conn.query("ALTER TABLE bookings ADD COLUMN payment_flow ENUM('platform_collect','hotel_collect') NOT NULL DEFAULT 'platform_collect' AFTER payment_method");
  await conn.query("UPDATE bookings SET payment_flow = 'hotel_collect' WHERE payment_method = 'hotel'");
  console.log('✅ bookings: added payment_flow');
} else {
  console.log('ℹ️  bookings: payment_flow already exists');
}

// #4: commission_rate cho bookings (snapshot tại thời điểm booking)
const [cols4] = await conn.query("SHOW COLUMNS FROM bookings LIKE 'commission_rate'");
if (cols4.length === 0) {
  await conn.query("ALTER TABLE bookings ADD COLUMN commission_rate DECIMAL(5,2) DEFAULT NULL AFTER payment_flow");
  console.log('✅ bookings: added commission_rate');
} else {
  console.log('ℹ️  bookings: commission_rate already exists');
}

// #5: commission_rate cho hotels (nếu chưa có)
const [cols5] = await conn.query("SHOW COLUMNS FROM hotels LIKE 'commission_rate'");
if (cols5.length === 0) {
  await conn.query("ALTER TABLE hotels ADD COLUMN commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00 AFTER partner_status");
  console.log('✅ hotels: added commission_rate');
} else {
  console.log('ℹ️  hotels: commission_rate already exists');
}

// #6: cancel_free_days cho hotels (nếu chưa có)
const [cols6] = await conn.query("SHOW COLUMNS FROM hotels LIKE 'cancel_free_days'");
if (cols6.length === 0) {
  await conn.query("ALTER TABLE hotels ADD COLUMN cancel_free_days INT NOT NULL DEFAULT 1 AFTER commission_rate");
  console.log('✅ hotels: added cancel_free_days');
} else {
  console.log('ℹ️  hotels: cancel_free_days already exists');
}

// #7: refund_requested_at, refund_amount, refund_requested cho bookings
const [cols7] = await conn.query("SHOW COLUMNS FROM bookings LIKE 'refund_requested'");
if (cols7.length === 0) {
  await conn.query("ALTER TABLE bookings ADD COLUMN refund_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
  await conn.query("ALTER TABLE bookings ADD COLUMN refund_requested_at DATETIME NULL DEFAULT NULL AFTER refund_requested");
  await conn.query("ALTER TABLE bookings ADD COLUMN refund_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER refund_requested_at");
  console.log('✅ bookings: added refund_requested, refund_requested_at, refund_amount');
} else {
  console.log('ℹ️  bookings: refund_requested already exists');
}

// #8: first_booking_only cho vouchers (chỉ tài khoản mới / đặt lần đầu)
const [cols8] = await conn.query("SHOW COLUMNS FROM vouchers LIKE 'first_booking_only'");
if (cols8.length === 0) {
  await conn.query("ALTER TABLE vouchers ADD COLUMN first_booking_only TINYINT(1) NOT NULL DEFAULT 0 AFTER description");
  console.log('✅ vouchers: added first_booking_only');
} else {
  console.log('ℹ️  vouchers: first_booking_only already exists');
}
// Đánh dấu SUMMER2026 là voucher chỉ dành cho lần đầu
await conn.query("UPDATE vouchers SET first_booking_only = 1 WHERE code = 'SUMMER2026'");
console.log('✅ SUMMER2026 marked as first_booking_only');

// #9: voucher_uses — theo dõi ai đã dùng voucher để chặn lợi dụng
const [vutables] = await conn.query("SHOW TABLES LIKE 'voucher_uses'");
if (vutables.length === 0) {
  await conn.query(`CREATE TABLE voucher_uses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    user_id INT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    phone VARCHAR(20)  NOT NULL DEFAULT '',
    booking_id INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vu_email  (voucher_id, email(30)),
    INDEX idx_vu_phone  (voucher_id, phone),
    INDEX idx_vu_uid    (voucher_id, user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`);
  console.log('✅ Created voucher_uses table');
} else {
  console.log('ℹ️  voucher_uses: already exists');
}

await conn.end();
console.log('✅ Migration Railway hoàn tất!');
