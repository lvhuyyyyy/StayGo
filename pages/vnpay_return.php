<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../includes/email_helper.php';

// ── Xác minh chữ ký ──────────────────────────────────────────────
$validSig     = vnpay_verify_signature($_GET);
$responseCode = $_GET['vnp_ResponseCode'] ?? '';
$orderCode    = $_GET['vnp_TxnRef']       ?? '';
$vnpAmount    = intval($_GET['vnp_Amount'] ?? 0) / 100;

$success  = false;
$errorMsg = 'Giao dịch không thành công.';

if (!$validSig) {
    $errorMsg = 'Chữ ký không hợp lệ. Giao dịch không được xác nhận.';
} elseif ($responseCode === '00') {
    // Thanh toán thành công — cập nhật DB
    $stmt = $conn->prepare("
        UPDATE payments p
        JOIN bookings b ON p.booking_id = b.id
        SET p.payment_status    = 'paid',
            p.payment_verified  = 1,
            p.verified_at       = NOW(),
            b.status            = 'confirmed'
        WHERE b.order_code = ? AND p.payment_status = 'pending'
    ");
    $stmt->bind_param('s', $orderCode);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Ghi booking_log
        $log_stmt = $conn->prepare("
            INSERT INTO booking_logs (booking_id, actor_type, actor_name, action, description)
            SELECT b.id, 'SYSTEM', 'VNPay', 'PAYMENT_CONFIRMED',
                   CONCAT('Thanh toán VNPay thành công. Số tiền: ', b.total_price, ' VNĐ')
            FROM bookings b WHERE b.order_code = ? LIMIT 1
        ");
        $log_stmt->bind_param('s', $orderCode);
        $log_stmt->execute();
    }

    $success = true;
} else {
    $vnpay_errors = [
        '07' => 'Giao dịch bị nghi ngờ gian lận.',
        '09' => 'Thẻ/tài khoản chưa đăng ký Internet Banking.',
        '10' => 'Xác thực thông tin thẻ quá 3 lần.',
        '11' => 'Phiên thanh toán hết hạn. Vui lòng thực hiện lại.',
        '12' => 'Thẻ/tài khoản bị khóa.',
        '13' => 'Sai mật khẩu OTP.',
        '24' => 'Bạn đã hủy giao dịch.',
        '51' => 'Tài khoản không đủ số dư.',
        '65' => 'Vượt hạn mức giao dịch trong ngày.',
        '75' => 'Ngân hàng đang bảo trì. Vui lòng thử lại sau.',
        '79' => 'Nhập sai mật khẩu thanh toán quá số lần quy định.',
    ];
    $errorMsg = $vnpay_errors[$responseCode]
             ?? 'Giao dịch thất bại (mã lỗi: ' . htmlspecialchars($responseCode) . ').';
}

// ── Lấy thông tin booking để hiển thị và gửi email ──────────────
$booking = null;
if ($orderCode) {
    $s = $conn->prepare("
        SELECT b.order_code, b.check_in, b.check_out, b.total_price,
               b.full_name, b.email, r.room_name,
               h.name AS hotel_name, h.partner_email AS hotel_email
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN hotels h ON r.hotel_id = h.id
        WHERE b.order_code = ?
        LIMIT 1
    ");
    $s->bind_param('s', $orderCode);
    $s->execute();
    $booking = $s->get_result()->fetch_assoc();
}

if ($success && $booking) {
    // Email xác nhận đến khách hàng
    send_payment_email($booking['email'], $booking['full_name'], [
        'order_code'     => $booking['order_code'],
        'hotel_name'     => $booking['hotel_name'],
        'checkin'        => $booking['check_in'],
        'checkout'       => $booking['check_out'],
        'payment_method' => 'VNPay',
        'amount'         => $booking['total_price'],
        'full_name'      => $booking['full_name'],
    ]);
    // Email thông báo đến khách sạn
    if (!empty($booking['hotel_email'])) {
        send_hotel_new_booking_email($booking['hotel_email'], [
            'order_code'     => $booking['order_code'],
            'hotel_name'     => $booking['hotel_name'],
            'full_name'      => $booking['full_name'],
            'guest_email'    => $booking['email'],
            'room_name'      => $booking['room_name'],
            'checkin'        => $booking['check_in'],
            'checkout'       => $booking['check_out'],
            'payment_method' => 'VNPay',
            'amount'         => $booking['total_price'],
        ]);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.result-page{min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 16px}
.result-card{background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.1);max-width:520px;width:100%;padding:44px 36px;text-align:center}
.result-icon{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px}
.result-icon.success{background:#f0fff4}
.result-icon.fail{background:#fff5f5}
.result-title{font-size:22px;font-weight:800;margin-bottom:8px}
.result-title.success{color:#276749}
.result-title.fail{color:#c53030}
.result-sub{color:#718096;font-size:14px;margin-bottom:24px}
.result-details{background:#f7fafc;border-radius:12px;padding:16px 20px;text-align:left;margin-bottom:24px}
.detail-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;border-bottom:1px solid #e2e8f0}
.detail-row:last-child{border-bottom:none;font-weight:700;color:#276749;font-size:15px}
.detail-label{color:#718096}
.result-badge{display:inline-flex;align-items:center;gap:6px;background:#f0fff4;color:#276749;border:1px solid #c6f6d5;border-radius:20px;padding:6px 16px;font-size:13px;font-weight:600;margin-bottom:20px}
.result-badge.fail{background:#fff5f5;color:#c53030;border-color:#fed7d7}
.result-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn-primary{padding:11px 24px;background:linear-gradient(135deg,#276749,#38a169);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none;border:none;cursor:pointer}
.btn-secondary{padding:11px 24px;background:#edf2f7;color:#2d3748;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none}
.btn-retry{padding:11px 24px;background:linear-gradient(135deg,#e05c1a,#f97316);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none}
.method-tag{display:inline-block;background:#ebf4ff;color:#1a5fa8;border-radius:6px;padding:2px 10px;font-size:12px;font-weight:600;margin-bottom:4px}
</style>

<div class="result-page">
    <div class="result-card">

    <?php if ($success): ?>

        <div class="result-icon success">✅</div>
        <div class="result-title success">Thanh toán thành công!</div>
        <p class="result-sub">VNPay đã xác nhận giao dịch của bạn.</p>

        <div class="result-badge">
            <img src="/assets/images/vnpay.png" alt="VNPay" style="height:18px;border-radius:3px"> VNPay
        </div>

        <?php if ($booking): ?>
        <div class="result-details">
            <div class="detail-row">
                <span class="detail-label">Mã đơn hàng</span>
                <span><?= htmlspecialchars($booking['order_code']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Khách sạn</span>
                <span><?= htmlspecialchars($booking['hotel_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-in</span>
                <span><?= date('d/m/Y', strtotime($booking['check_in'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-out</span>
                <span><?= date('d/m/Y', strtotime($booking['check_out'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tổng thanh toán</span>
                <span><?= number_format($booking['total_price'], 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="result-actions">
            <a href="/pages/my_bookings.php" class="btn-primary">Xem đơn hàng của tôi</a>
            <a href="/pages/hotels.php" class="btn-secondary">← Về trang khách sạn</a>
        </div>

    <?php else: ?>

        <div class="result-icon fail">❌</div>
        <div class="result-title fail">Thanh toán thất bại</div>
        <p class="result-sub"><?= htmlspecialchars($errorMsg) ?></p>

        <div class="result-badge fail">
            <img src="/assets/images/vnpay.png" alt="VNPay" style="height:18px;border-radius:3px"> VNPay
        </div>

        <p style="font-size:13px;color:#718096;margin-bottom:24px">
            Đơn đặt phòng <strong><?= htmlspecialchars($orderCode) ?></strong> vẫn được lưu với trạng thái
            <em>chờ thanh toán</em>. Bạn có thể thử lại hoặc chọn phương thức khác.
        </p>

        <div class="result-actions">
            <a href="javascript:history.back()" class="btn-retry">Thử lại</a>
            <a href="/pages/hotels.php" class="btn-secondary">← Về trang khách sạn</a>
        </div>

    <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
