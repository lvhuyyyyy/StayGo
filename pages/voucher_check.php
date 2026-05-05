<?php
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$code   = strtoupper(trim($_POST['code']   ?? ''));
$amount = (float)($_POST['amount'] ?? 0);

function jresp($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
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
    jresp(false, "Đơn tối thiểu {$min}đ mới áp dụng được voucher này.");
}

$discount = ($v['type'] === 'percent')
    ? round($amount * $v['value'] / 100)
    : min((float)$v['value'], $amount);

jresp(true, $v['description'] ?: 'Áp dụng thành công!', [
    'code'      => $v['code'],
    'type'      => $v['type'],
    'value'     => (float)$v['value'],
    'min_order' => (float)$v['min_order'],
    'discount'  => $discount,
]);
