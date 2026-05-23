<?php
/**
 * Casso Webhook Handler
 * ──────────────────────────────────────────────────────────────────
 * Casso gọi endpoint này mỗi khi có giao dịch mới vào tài khoản ngân hàng.
 * Hệ thống tự động đối chiếu mã đơn hàng trong nội dung CK → confirm booking.
 *
 * Cấu hình trong Casso Dashboard:
 *   Webhook URL : https://yourdomain.com/pages/casso_webhook.php
 *   Secure Token: (tạo bất kỳ chuỗi ngẫu nhiên, điền vào CASSO_API_TOKEN trong payment_config.php)
 *
 * Tài liệu Casso: https://docs.casso.vn/
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../includes/email_helper.php';

header('Content-Type: application/json');

// ── 1. Xác minh Secure Token ─────────────────────────────────────
$received_token = $_SERVER['HTTP_SECURE_TOKEN']
               ?? $_SERVER['HTTP_X_API_KEY']
               ?? getallheaders()['secure-token']
               ?? getallheaders()['Secure-Token']
               ?? '';

if (CASSO_API_TOKEN && !hash_equals(CASSO_API_TOKEN, $received_token)) {
    http_response_code(401);
    echo json_encode(['error' => 401, 'message' => 'Invalid token']);
    exit;
}

// ── 2. Parse JSON body ───────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || ($body['error'] ?? -1) !== 0 || empty($body['data'])) {
    echo json_encode(['error' => 0, 'message' => 'No data']);
    exit;
}

// ── 3. Xử lý từng giao dịch ─────────────────────────────────────
foreach ((array)$body['data'] as $txn) {

    // Chỉ xử lý tiền VÀO (IN)
    if (($txn['type'] ?? '') !== 'IN') continue;

    $amount      = (float)($txn['amount']      ?? 0);
    $description = (string)($txn['description'] ?? '');
    $tid         = (string)($txn['tid']         ?? $txn['id'] ?? '');
    $when        = (string)($txn['when']        ?? date('Y-m-d H:i:s'));

    if ($amount <= 0) continue;

    // ── 4. Tìm mã đơn hàng trong nội dung chuyển khoản ──────────
    // Khớp pattern: ORD + chữ/số/dấu chấm, hoặc BK + số
    // VD: "ORD17780758021950", "BK2026001", "ORD177.123"
    $order_code = null;
    if (preg_match('/\b(ORD[\w.]+|BK\d+)\b/i', $description, $m)) {
        $order_code = strtoupper(trim($m[1]));
    }

    if (!$order_code) continue; // Không tìm thấy mã đơn → bỏ qua

    // ── 5. Tìm booking khớp mã đơn + số tiền ────────────────────
    $stmt = $conn->prepare("
        SELECT b.id, b.order_code, b.total_price, b.status,
               b.full_name, b.email, b.check_in, b.check_out,
               r.room_name, h.name AS hotel_name, h.partner_email AS hotel_email,
               p.id AS payment_id, p.payment_status, p.payment_verified
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN hotels h ON r.hotel_id = h.id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE b.order_code = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $order_code);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) continue; // Không tìm thấy booking

    // Fix #4a: Idempotency check đầy đủ — skip mọi trạng thái đã xử lý
    if ($booking['payment_verified']
        || $booking['payment_status'] === 'paid'
        || in_array($booking['status'], ['confirmed', 'checked_in', 'completed'])) {
        continue;
    }

    // Fix #4b: Tolerance = max(1.000đ, 1% tổng giá) — tránh lỏng với booking nhỏ
    $max_diff = max(1000, (float)$booking['total_price'] * 0.01);
    $diff = abs($amount - (float)$booking['total_price']);
    if ($diff > $max_diff) {
        // Ghi log nhưng không confirm (có thể khách CK sai số tiền)
        error_log("[Casso] Order $order_code: amount mismatch — expected {$booking['total_price']}, got $amount (diff=$diff, max_allowed=$max_diff)");
        continue;
    }

    // ── 6. Auto-confirm ──────────────────────────────────────────
    $booking_id = (int)$booking['id'];
    $tid_esc    = $conn->real_escape_string($tid);
    $desc_esc   = $conn->real_escape_string(mb_substr($description, 0, 200));
    $when_esc   = $conn->real_escape_string($when);

    // Cập nhật booking
    $upd_bk = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
    $upd_bk->bind_param('i', $booking_id);
    $upd_bk->execute();
    $upd_bk->close();

    // Cập nhật payment: ghi rõ bank_txn_id + verified_at
    if ($booking['payment_id']) {
        $upd = $conn->prepare("
            UPDATE payments
            SET payment_status   = 'paid',
                payment_verified = 1,
                bank_txn_id      = ?,
                verified_at      = NOW()
            WHERE id = ? AND payment_verified = 0
        ");
        $upd->bind_param('si', $tid, $booking['payment_id']);
        $upd->execute();
        // affected_rows = 0 nghĩa là request đồng thời đã xử lý trước — dừng luôn
        if ($upd->affected_rows === 0) { $upd->close(); continue; }
        $upd->close();
    }

    // Ghi booking_log (prepared statement để tránh injection từ description/tid)
    $log_desc = 'Chuyển khoản tự động xác nhận qua Casso. Nội dung CK: ' . mb_substr($description, 0, 200);
    $log_meta = json_encode(['tid' => $tid, 'amount' => $amount, 'when' => $when]);
    $ins_log  = $conn->prepare("
        INSERT INTO booking_logs (booking_id, actor_type, actor_name, action, description, metadata)
        VALUES (?, 'SYSTEM', 'Casso', 'PAYMENT_CONFIRMED', ?, ?)
    ");
    $ins_log->bind_param('iss', $booking_id, $log_desc, $log_meta);
    $ins_log->execute();
    $ins_log->close();

    // ── 7. Gửi email ─────────────────────────────────────────────
    $email_data = [
        'order_code'     => $booking['order_code'],
        'hotel_name'     => $booking['hotel_name'],
        'checkin'        => $booking['check_in'],
        'checkout'       => $booking['check_out'],
        'payment_method' => 'Chuyển khoản ngân hàng',
        'amount'         => $booking['total_price'],
        'full_name'      => $booking['full_name'],
    ];

    // Email đến khách hàng
    send_payment_email($booking['email'], $booking['full_name'], $email_data);

    // Email đến khách sạn
    if (!empty($booking['hotel_email'])) {
        send_hotel_new_booking_email($booking['hotel_email'], [
            'order_code'     => $booking['order_code'],
            'hotel_name'     => $booking['hotel_name'],
            'full_name'      => $booking['full_name'],
            'guest_email'    => $booking['email'],
            'room_name'      => $booking['room_name'],
            'checkin'        => $booking['check_in'],
            'checkout'       => $booking['check_out'],
            'payment_method' => 'Chuyển khoản ngân hàng',
            'amount'         => $booking['total_price'],
        ]);
    }

    error_log("[Casso] Auto-confirmed booking $order_code (tid=$tid, amount=$amount)");
}

echo json_encode(['error' => 0, 'message' => 'OK']);
