<?php
/**
 * PayOS IPN Webhook — server-to-server callback sau khi thanh toán hoàn tất.
 * PayOS Dashboard → Cài đặt → Webhook URL:
 *   https://staygo-local.up.railway.app/pages/payos_webhook.php
 *
 * Không dùng session/header redirect — chỉ trả JSON.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';

header('Content-Type: application/json');

// ── Đọc raw body ─────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = $raw ? json_decode($raw, true) : null;

if (!$body || !isset($body['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 1, 'message' => 'Invalid payload']);
    exit;
}

$data      = $body['data'];
$orderCode = (int)($data['orderCode'] ?? 0);
$amount    = (float)($data['amount']  ?? 0);
$status    = $data['code']            ?? '';   // "00" = thành công
$linkId    = $data['paymentLinkId']   ?? '';
$sigIn     = $body['signature']       ?? '';

// ── Xác minh chữ ký PayOS (HMAC SHA256 từ data fields sorted by key) ─
// PayOS SDK tính signature bao gồm TẤT CẢ fields kể cả empty string/null
ksort($data);
$signParts = [];
foreach ($data as $k => $v) {
    $signParts[] = "$k=" . ($v ?? '');
}
$expectedSig = hash_hmac('sha256', implode('&', $signParts), PAYOS_CHECKSUM_KEY);

if (!hash_equals($expectedSig, $sigIn)) {
    http_response_code(400);
    echo json_encode(['error' => 1, 'message' => 'Invalid signature']);
    exit;
}

// ── Chỉ xử lý khi code = 00 (success) ───────────────────────────────
if ($status !== '00' || !$orderCode) {
    echo json_encode(['error' => 0, 'message' => 'Ignored', 'data' => null]);
    exit;
}

// ── Lấy booking + idempotency check ─────────────────────────────────
$b_stmt = $conn->prepare("
    SELECT b.id, b.total_price, b.status, b.payment_flow,
           p.id AS payment_id, p.payment_status
    FROM bookings b
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.id = ?
    LIMIT 1
");
$b_stmt->bind_param('i', $orderCode);
$b_stmt->execute();
$booking = $b_stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['error' => 0, 'message' => 'Booking not found', 'data' => null]);
    exit;
}

// Idempotency: đã xử lý rồi → bỏ qua
if ($booking['payment_status'] === 'paid'
    || in_array($booking['status'], ['confirmed', 'checked_in', 'completed'])) {
    echo json_encode(['error' => 0, 'message' => 'Already processed', 'data' => null]);
    exit;
}

// Amount check: ±1% tolerance
$expected = (float)$booking['total_price'];
if ($amount < $expected * 0.99 || $amount > $expected * 1.01) {
    error_log("[PayOS webhook] amount mismatch: expected={$expected}, got={$amount}, booking={$orderCode}");
    echo json_encode(['error' => 0, 'message' => 'Amount mismatch logged', 'data' => null]);
    exit;
}

// ── Cập nhật DB trong transaction ────────────────────────────────────
$conn->begin_transaction();

$upd_pay = $conn->prepare("
    UPDATE payments SET payment_status='paid', payment_verified=1, verified_at=NOW()
    WHERE booking_id = ? AND payment_status = 'pending'
");
$upd_pay->bind_param('i', $orderCode);
$upd_pay->execute();

$upd_bk = $conn->prepare("
    UPDATE bookings SET status='confirmed'
    WHERE id = ? AND status = 'pending'
");
$upd_bk->bind_param('i', $orderCode);
$upd_bk->execute();

$ins_log = $conn->prepare("
    INSERT INTO booking_logs (booking_id, actor_type, actor_name, action, description)
    VALUES (?, 'SYSTEM', 'PayOS-Webhook', 'PAYMENT_CONFIRMED',
            'PayOS IPN xác nhận thanh toán thành công')
");
$ins_log->bind_param('i', $orderCode);
$ins_log->execute();

$conn->commit();

echo json_encode(['error' => 0, 'message' => 'Ok', 'data' => null]);
