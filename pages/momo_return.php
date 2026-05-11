<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../includes/email_helper.php';

// ── Xác minh chữ ký MoMo ─────────────────────────────────────────
$params = [
    'accessKey'    => MOMO_ACCESS_KEY,
    'amount'       => $_GET['amount']       ?? '',
    'extraData'    => $_GET['extraData']    ?? '',
    'message'      => $_GET['message']      ?? '',
    'orderId'      => $_GET['orderId']      ?? '',
    'orderInfo'    => $_GET['orderInfo']    ?? '',
    'orderType'    => $_GET['orderType']    ?? '',
    'partnerCode'  => $_GET['partnerCode']  ?? '',
    'payType'      => $_GET['payType']      ?? '',
    'requestId'    => $_GET['requestId']    ?? '',
    'responseTime' => $_GET['responseTime'] ?? '',
    'resultCode'   => $_GET['resultCode']   ?? '',
    'transId'      => $_GET['transId']      ?? '',
    'signature'    => $_GET['signature']    ?? '',
];
$validSig   = momo_verify_signature($params);
$resultCode = intval($_GET['resultCode'] ?? -1);
$orderCode  = $_GET['orderId'] ?? '';

$success  = false;
$errorMsg = 'Giao dịch thất bại.';

if (!$validSig) {
    $errorMsg = 'Chữ ký không hợp lệ. Giao dịch không được xác nhận.';
} elseif ($resultCode === 0) {
    $stmt = $conn->prepare("
        UPDATE payments p
        JOIN bookings b ON p.booking_id = b.id
        SET p.payment_status = 'paid',
            b.status         = 'confirmed'
        WHERE b.order_code = ? AND p.payment_status = 'pending'
    ");
    $stmt->bind_param('s', $orderCode);
    $stmt->execute();
    $success = true;
} else {
    $momo_errors = [
        1  => 'Giao dịch thất bại. Vui lòng thử lại.',
        2  => 'Giao dịch bị từ chối. Liên hệ MoMo để được hỗ trợ.',
        3  => 'Giao dịch bị hủy.',
        10 => 'Hệ thống đang bảo trì. Vui lòng thử lại sau.',
        11 => 'Truy cập bị từ chối.',
        12 => 'Phiên thanh toán hết hạn.',
        13 => 'Xác thực OTP thất bại.',
        20 => 'Yêu cầu không hợp lệ.',
        21 => 'Số tiền không hợp lệ.',
        40 => 'Vượt hạn mức giao dịch.',
        41 => 'Vượt số lần giao dịch cho phép.',
        42 => 'Tài khoản không đủ số dư.',
        43 => 'Giao dịch đang chờ xử lý.',
    ];
    $errorMsg = $momo_errors[$resultCode]
             ?? ($_GET['message'] ?? 'Giao dịch thất bại (mã: ' . $resultCode . ').');
}

// ── Lấy thông tin booking để hiển thị ───────────────────────────
$booking = null;
if ($orderCode) {
    $s = $conn->prepare("
        SELECT b.order_code, b.check_in, b.check_out, b.total_price,
               b.full_name, b.email, h.name AS hotel_name
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
    send_payment_email($booking['email'], $booking['full_name'], [
        'order_code'     => $booking['order_code'],
        'hotel_name'     => $booking['hotel_name'],
        'checkin'        => $booking['check_in'],
        'checkout'       => $booking['check_out'],
        'payment_method' => 'Ví MoMo',
        'amount'         => $booking['total_price'],
        'full_name'      => $booking['full_name'],
    ]);
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.result-page{min-height:60vh;display:flex;align-items:center;justify-content:center;padding:40px 16px}
.result-card{background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.1);max-width:520px;width:100%;padding:44px 36px;text-align:center}
.result-icon{width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px}
.result-icon.success{background:#fff0f6}
.result-icon.fail{background:#fff5f5}
.result-title{font-size:22px;font-weight:800;margin-bottom:8px}
.result-title.success{color:#a0195c}
.result-title.fail{color:#c53030}
.result-sub{color:#718096;font-size:14px;margin-bottom:24px}
.result-details{background:#f7fafc;border-radius:12px;padding:16px 20px;text-align:left;margin-bottom:24px}
.detail-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;border-bottom:1px solid #e2e8f0}
.detail-row:last-child{border-bottom:none;font-weight:700;color:#a0195c;font-size:15px}
.detail-label{color:#718096}
.result-badge{display:inline-flex;align-items:center;gap:6px;background:#fff0f6;color:#a0195c;border:1px solid #fbb6ce;border-radius:20px;padding:6px 16px;font-size:13px;font-weight:600;margin-bottom:20px}
.result-badge.fail{background:#fff5f5;color:#c53030;border-color:#fed7d7}
.result-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn-momo{padding:11px 24px;background:linear-gradient(135deg,#a2185b,#d53f8c);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none}
.btn-secondary{padding:11px 24px;background:#edf2f7;color:#2d3748;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none}
.btn-retry{padding:11px 24px;background:linear-gradient(135deg,#e05c1a,#f97316);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none}
</style>

<div class="result-page">
    <div class="result-card">

    <?php if ($success): ?>

        <div class="result-icon success">💜</div>
        <div class="result-title success">Thanh toán MoMo thành công!</div>
        <p class="result-sub">Ví MoMo đã xác nhận giao dịch của bạn.</p>

        <div class="result-badge">
            <img src="/assets/images/momo.png" alt="MoMo" style="height:18px;border-radius:3px"> Ví MoMo
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
            <a href="/pages/my_bookings.php" class="btn-momo">Xem đơn hàng của tôi</a>
            <a href="/pages/hotels.php" class="btn-secondary">← Về trang khách sạn</a>
        </div>

    <?php else: ?>

        <div class="result-icon fail">❌</div>
        <div class="result-title fail">Thanh toán thất bại</div>
        <p class="result-sub"><?= htmlspecialchars($errorMsg) ?></p>

        <div class="result-badge fail">
            <img src="/assets/images/momo.png" alt="MoMo" style="height:18px;border-radius:3px"> MoMo
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
