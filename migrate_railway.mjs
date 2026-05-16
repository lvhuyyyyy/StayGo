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

await conn.end();
console.log('✅ Migration Railway hoàn tất!');
