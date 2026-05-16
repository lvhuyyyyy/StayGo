<?php
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$code   = strtoupper(trim($_POST['code']   ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$email  = strtolower(trim($_POST['email']  ?? ''));
$phone  = preg_replace('/[\s\-\.]/', '', trim($_POST['phone'] ?? ''));

function jresp($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$code) jresp(false, 'Vui lòng nhập mã voucher.');

$stmt = $conn->prepare("
    SELECT * FROM vouchers
    WHERE code = ? AND is_active = 1
    AND (expires_at IS NULL OR expires_at >= CURDATE())
    AND used_count < max_uses
");
$stmt->bind_param("s", $code);
$stmt->execute();
$v = $stmt->get_result()->fetch_assoc();

if (!$v) jresp(false, 'Mã voucher không hợp lệ hoặc đã hết lượt sử dụng.');

if ($amount > 0 && $amount < $v['min_order']) {
    $min = number_format($v['min_order'], 0, ',', '.');
    jresp(false, "Đơn tối thiểu {$min}đ mới áp dụng được mã này.");
}

// ── Kiểm tra first_booking_only (chống lợi dụng) ────────────────────────────
if (!empty($v['first_booking_only'])) {
    // Phải có email hoặc phone để kiểm tra
    if ($email || $phone) {
        // 1. Validate email format
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jresp(false, '⚠️ Email không hợp lệ. Vui lòng nhập đúng email để dùng mã này.');
        }
        // 2. Validate phone format (SĐT Việt Nam 10 số: 03x 05x 07x 08x 09x)
        if ($phone && !preg_match('/^0[3-9]\d{8}$/', $phone)) {
            jresp(false, '⚠️ Số điện thoại không hợp lệ. Vui lòng nhập SĐT Việt Nam 10 số.');
        }

        $vid       = (int)$v['id'];
        $userId    = $_SESSION['user_id'] ?? null;
        $email_esc = $conn->real_escape_string($email);
        $phone_esc = $conn->real_escape_string($phone);

        // 3. Xây dựng điều kiện so sánh (email OR phone OR user_id)
        $conds = [];
        if ($email) $conds[] = "email = '$email_esc'";
        if ($phone) $conds[] = "phone = '$phone_esc'";
        if ($userId) $conds[] = "user_id = " . (int)$userId;
        $where = implode(' OR ', $conds);

        // 4. Đã dùng mã này chưa?
        $already = $conn->query(
            "SELECT id FROM voucher_uses WHERE voucher_id = $vid AND ($where) LIMIT 1"
        )->num_rows > 0;
        if ($already) {
            jresp(false, '❌ Email hoặc SĐT này đã sử dụng mã ' . $v['code'] . ' rồi. Mỗi khách chỉ được dùng 1 lần.');
        }

        // 5. Đã từng đặt phòng chưa?
        $prior = $conn->query(
            "SELECT id FROM bookings
             WHERE status IN ('pending','confirmed','completed')
             AND ($where)
             LIMIT 1"
        )->num_rows > 0;
        if ($prior) {
            jresp(false, '❌ Mã ' . $v['code'] . ' chỉ dành cho khách đặt phòng lần đầu tiên tại StayGo.');
        }
    }
    // Chưa có email/phone → cho phép hiển thị voucher nhưng sẽ kiểm tra lại khi submit
}

$discount = ($v['type'] === 'percent')
    ? round($amount * $v['value'] / 100)
    : min((float)$v['value'], $amount);

jresp(true, $v['description'] ?: 'Áp dụng thành công!', [
    'code'             => $v['code'],
    'type'             => $v['type'],
    'value'            => (float)$v['value'],
    'min_order'        => (float)$v['min_order'],
    'discount'         => $discount,
    'first_booking_only' => (int)$v['first_booking_only'],
]);
