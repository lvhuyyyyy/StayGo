<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../includes/email_helper.php';

$booking_id = (int)($_GET['orderCode'] ?? 0);
$status     = $_GET['status']         ?? '';
$payment_id = $_GET['id']             ?? '';
$cancelled  = ($_GET['cancel']        ?? '') === 'true';

$success  = false;
$errorMsg = 'Giao dịch thất bại.';

if ($cancelled || $status === 'CANCELLED') {
    $errorMsg = 'Bạn đã hủy giao dịch PayOS.';
} elseif ($status === 'PAID' && $booking_id && $payment_id) {
    // Xác minh giao dịch qua PayOS API (tránh giả mạo return URL)
    $ch = curl_init('https://api-merchant.payos.vn/v2/payment-requests/' . rawurlencode($payment_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => [
            'x-client-id: ' . PAYOS_CLIENT_ID,
            'x-api-key: '   . PAYOS_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $vres = curl_exec($ch);
    curl_close($ch);

    $vdata    = $vres ? json_decode($vres, true) : null;
    $verified = ($vdata
              && ($vdata['code'] ?? '') === '00'
              && ($vdata['data']['status'] ?? '') === 'PAID'
              && (int)($vdata['data']['orderCode'] ?? 0) === $booking_id);

    if ($verified) {
        // Cập nhật payments + bookings
        $stmt = $conn->prepare("
            UPDATE payments p
            JOIN bookings b ON p.booking_id = b.id
            SET p.payment_status   = 'paid',
                p.payment_verified = 1,
                p.verified_at      = NOW(),
                b.status           = 'confirmed'
            WHERE b.id = ? AND p.payment_status = 'pending'
        ");
        if (!$stmt) {
            $stmt = $conn->prepare("
                UPDATE payments p
                JOIN bookings b ON p.booking_id = b.id
                SET p.payment_status = 'paid',
                    b.status         = 'confirmed'
                WHERE b.id = ? AND p.payment_status = 'pending'
            ");
        }
        $stmt->bind_param('i', $booking_id);
        $stmt->execute();

        // Ghi log
        $log_stmt = $conn->prepare("
            INSERT INTO booking_logs (booking_id, actor_type, actor_name, action, description)
            VALUES (?, 'SYSTEM', 'PayOS', 'PAYMENT_CONFIRMED',
                    CONCAT('Thanh toán PayOS thành công. Booking ID: ', ?))
        ");
        if ($log_stmt) {
            $log_stmt->bind_param('ii', $booking_id, $booking_id);
            $log_stmt->execute();
        }

        $success = true;
    } else {
        $errorMsg = 'Không thể xác minh giao dịch với PayOS. Vui lòng liên hệ hỗ trợ.';
    }
} elseif ($booking_id) {
    $errorMsg = 'Giao dịch chưa được hoàn tất (trạng thái: ' . htmlspecialchars($status) . ').';
}

// Lấy thông tin booking để hiển thị và gửi email
$booking = null;
if ($booking_id) {
    $s = $conn->prepare("
        SELECT b.id, b.order_code, b.check_in, b.check_out, b.total_price,
               b.full_name, b.email, r.room_name,
               h.name AS hotel_name, h.partner_email AS hotel_email
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN hotels h ON r.hotel_id = h.id
        WHERE b.id = ?
        LIMIT 1
    ");
    if (!$s) {
        $s = $conn->prepare("
            SELECT b.id, b.order_code, b.check_in, b.check_out, b.total_price,
                   b.full_name, b.email, r.room_name,
                   h.name AS hotel_name, NULL AS hotel_email
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN hotels h ON r.hotel_id = h.id
            WHERE b.id = ?
            LIMIT 1
        ");
    }
    $s->bind_param('i', $booking_id);
    $s->execute();
    $s_result = $s->get_result();
    $booking  = $s_result ? $s_result->fetch_assoc() : null;
}

if ($success && $booking) {
    send_payment_email($booking['email'], $booking['full_name'], [
        'order_code'     => $booking['order_code'],
        'hotel_name'     => $booking['hotel_name'],
        'checkin'        => $booking['check_in'],
        'checkout'       => $booking['check_out'],
        'payment_method' => 'PayOS',
        'amount'         => $booking['total_price'],
        'full_name'      => $booking['full_name'],
    ]);
    if (!empty($booking['hotel_email'])) {
        send_hotel_new_booking_email($booking['hotel_email'], [
            'order_code'     => $booking['order_code'],
            'hotel_name'     => $booking['hotel_name'],
            'full_name'      => $booking['full_name'],
            'guest_email'    => $booking['email'],
            'room_name'      => $booking['room_name'],
            'checkin'        => $booking['check_in'],
            'checkout'       => $booking['check_out'],
            'payment_method' => 'PayOS',
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
.result-icon.success{background:#e6f0ff}
.result-icon.fail{background:#fff5f5}
.result-title{font-size:22px;font-weight:800;margin-bottom:8px}
.result-title.success{color:#0055bb}
.result-title.fail{color:#c53030}
.result-sub{color:#718096;font-size:14px;margin-bottom:24px}
.result-details{background:#f7fafc;border-radius:12px;padding:16px 20px;text-align:left;margin-bottom:24px}
.detail-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;border-bottom:1px solid #e2e8f0}
.detail-row:last-child{border-bottom:none;font-weight:700;color:#0055bb;font-size:15px}
.detail-label{color:#718096}
.result-badge{display:inline-flex;align-items:center;gap:6px;background:#e6f0ff;color:#0055bb;border:1px solid #b3d0ff;border-radius:20px;padding:6px 16px;font-size:13px;font-weight:600;margin-bottom:20px}
.result-badge.fail{background:#fff5f5;color:#c53030;border-color:#fed7d7}
.result-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn-payos{padding:11px 24px;background:linear-gradient(135deg,#0055bb,#0088ff);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none}
.btn-secondary{padding:11px 24px;background:#edf2f7;color:#2d3748;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none}
.btn-retry{padding:11px 24px;background:linear-gradient(135deg,#e05c1a,#f97316);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none}
</style>

<div class="result-page">
    <div class="result-card">

    <?php if ($success): ?>

        <div class="result-icon success">💙</div>
        <div class="result-title success">Thanh toán PayOS thành công!</div>
        <p class="result-sub">Giao dịch đã được xác nhận qua cổng PayOS.</p>

        <div class="result-badge">
            <svg width="16" height="16" viewBox="0 0 46 46" fill="none"><rect width="46" height="46" rx="10" fill="#0066CC"/><text x="23" y="30" text-anchor="middle" font-family="Arial" font-size="12" font-weight="bold" fill="white">P</text></svg>
            PayOS
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
            <a href="/pages/my_bookings.php" class="btn-payos">Xem đơn hàng của tôi</a>
            <a href="/pages/hotels.php" class="btn-secondary">← Về trang khách sạn</a>
        </div>

    <?php else: ?>

        <div class="result-icon fail">❌</div>
        <div class="result-title fail">Thanh toán thất bại</div>
        <p class="result-sub"><?= htmlspecialchars($errorMsg) ?></p>

        <div class="result-badge fail">PayOS</div>

        <p style="font-size:13px;color:#718096;margin-bottom:24px">
            Đơn đặt phòng
            <?php if ($booking): ?>
                <strong><?= htmlspecialchars($booking['order_code']) ?></strong>
            <?php endif; ?>
            vẫn được lưu với trạng thái <em>chờ thanh toán</em>.
            Bạn có thể thử lại hoặc chọn phương thức khác.
        </p>

        <div class="result-actions">
            <a href="javascript:history.back()" class="btn-retry">Thử lại</a>
            <a href="/pages/hotels.php" class="btn-secondary">← Về trang khách sạn</a>
        </div>

    <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
