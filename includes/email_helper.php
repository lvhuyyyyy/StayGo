<?php
define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
define('MAIL_FROM',      'onboarding@resend.dev');
define('MAIL_FROM_NAME', 'StayGo');

function _send_via_resend(string $to_email, string $subject, string $html_body): bool {
    if (!RESEND_API_KEY) {
        error_log('StayGo email: RESEND_API_KEY not set');
        return false;
    }
    $payload = json_encode([
        'from'    => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'to'      => [$to_email],
        'subject' => $subject,
        'html'    => $html_body,
    ]);
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code < 200 || $http_code >= 300) {
        error_log('StayGo Resend error: HTTP ' . $http_code . ' — ' . $response);
        return false;
    }
    return true;
}

// ─────────────────────────────────────────────────────────────────
// 1. Gửi OTP đặt lại mật khẩu
// ─────────────────────────────────────────────────────────────────
function send_otp_email(string $to_email, string $to_name, string $otp): bool {
    return _send_via_resend(
        $to_email,
        '[StayGo] Mã xác nhận đặt lại mật khẩu',
        _otp_template($to_name, $otp)
    );
}

// ─────────────────────────────────────────────────────────────────
// 2. Gửi xác nhận đặt phòng
// ─────────────────────────────────────────────────────────────────
function send_booking_email(string $to_email, string $to_name, array $d): bool {
    return _send_via_resend(
        $to_email,
        '[StayGo] Xác nhận đặt phòng #' . $d['order_code'],
        _booking_template($d)
    );
}

// ─────────────────────────────────────────────────────────────────
// 3. Gửi thông báo thanh toán thành công (đến khách hàng)
// ─────────────────────────────────────────────────────────────────
function send_payment_email(string $to_email, string $to_name, array $d): bool {
    return _send_via_resend(
        $to_email,
        '[StayGo] Thanh toán thành công #' . $d['order_code'],
        _payment_template($d)
    );
}

// ─────────────────────────────────────────────────────────────────
// 4. Gửi thông báo đặt phòng mới đến KHÁCH SẠN (sau khi payment xác nhận)
// ─────────────────────────────────────────────────────────────────
function send_hotel_new_booking_email(string $hotel_email, array $d): bool {
    return _send_via_resend(
        $hotel_email,
        '[StayGo] Đặt phòng mới đã xác nhận #' . $d['order_code'],
        _hotel_booking_template($d)
    );
}

// ═════════════════════════════════════════════════════════════════
// HTML TEMPLATES
// ═════════════════════════════════════════════════════════════════

function _email_wrap(string $title, string $body): string {
    return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $title . '</title>
<style>
  body{margin:0;padding:0;background:#f4f6f9;font-family:"Segoe UI",Arial,sans-serif}
  .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .header{background:linear-gradient(135deg,#1e3a5f,#1e73be);padding:28px 32px;text-align:center}
  .header h1{margin:0;color:#fff;font-size:24px;font-weight:800;letter-spacing:-.3px}
  .header p{margin:6px 0 0;color:rgba(255,255,255,.75);font-size:13px}
  .content{padding:32px}
  .greeting{font-size:16px;color:#2d3748;margin-bottom:20px}
  .box{background:#f7fafc;border-radius:12px;padding:8px 24px 4px;margin:20px 0}
  .row{display:flex;justify-content:space-between;padding:12px 4px;border-bottom:1px solid #e2e8f0;font-size:14px;gap:16px}
  .row:last-child{border-bottom:none;font-weight:700;font-size:15px;color:#1e73be}
  .label{color:#718096}
  .otp-box{text-align:center;margin:28px 0}
  .otp-code{font-size:42px;font-weight:900;letter-spacing:10px;color:#1e3a5f;background:#ebf4ff;border-radius:12px;padding:16px 24px;display:inline-block}
  .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
  .badge-success{background:#f0fff4;color:#276749;border:1px solid #c6f6d5}
  .badge-pending{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
  .footer{background:#f7fafc;padding:20px 32px;text-align:center;font-size:12px;color:#a0aec0;border-top:1px solid #e2e8f0}
  .footer a{color:#1e73be;text-decoration:none}
  .btn{display:inline-block;margin-top:20px;padding:12px 28px;background:linear-gradient(135deg,#1e3a5f,#1e73be);color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none}
  .warning{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:13px;color:#92400e;margin-top:16px}
</style></head><body>
<div class="wrap">' . $body . '
<div class="footer">
  © 2026 StayGo — Nền tảng đặt phòng khách sạn<br>
  146 Nguyễn Văn Cừ, Kon Tum &nbsp;|&nbsp; <a href="mailto:lvhuy.kontum@gmail.com">lvhuy.kontum@gmail.com</a><br>
  <small>Email này được gửi tự động, vui lòng không trả lời.</small>
</div></div></body></html>';
}

function _otp_template(string $name, string $otp): string {
    $body = '
<div class="header"><h1>🔐 StayGo</h1><p>Đặt lại mật khẩu tài khoản</p></div>
<div class="content">
  <p class="greeting">Xin chào <strong>' . htmlspecialchars($name) . '</strong>,</p>
  <p style="color:#4a5568;font-size:15px">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng nhập mã OTP bên dưới:</p>
  <div class="otp-box"><div class="otp-code">' . $otp . '</div></div>
  <div class="warning">
    ⏱ Mã OTP có hiệu lực trong <strong>15 phút</strong>.<br>
    Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.
  </div>
</div>';
    return _email_wrap('Mã OTP - StayGo', $body);
}

function _booking_template(array $d): string {
    $method_labels = [
        'bank'  => 'Chuyển khoản ngân hàng',
        'momo'  => 'Ví MoMo',
        'vnpay' => 'VNPay',
        'payos' => 'PayOS',
        'hotel' => 'Thanh toán tại khách sạn',
        'card'  => 'Thẻ quốc tế',
    ];
    $method = $method_labels[$d['payment_method'] ?? ''] ?? ($d['payment_method'] ?? '');
    $nights = max(1, (int)(( strtotime($d['checkout']) - strtotime($d['checkin']) ) / 86400));
    $body = '
<div class="header"><h1>🏨 StayGo</h1><p>Xác nhận đặt phòng thành công</p></div>
<div class="content">
  <p class="greeting">Xin chào <strong>' . htmlspecialchars($d['full_name']) . '</strong>,</p>
  <p style="color:#4a5568;font-size:15px">Đặt phòng của bạn đã được ghi nhận. Dưới đây là thông tin chi tiết:</p>
  <div class="box">
    <div class="row"><span class="label">Mã đơn hàng</span><span><strong>' . htmlspecialchars($d['order_code']) . '</strong></span></div>
    <div class="row"><span class="label">Khách sạn</span><span>' . htmlspecialchars($d['hotel_name']) . '</span></div>
    <div class="row"><span class="label">Loại phòng</span><span>' . htmlspecialchars($d['room_name']) . '</span></div>
    <div class="row"><span class="label">Nhận phòng</span><span>' . date('d/m/Y', strtotime($d['checkin'])) . '</span></div>
    <div class="row"><span class="label">Trả phòng</span><span>' . date('d/m/Y', strtotime($d['checkout'])) . ' <span style="color:#a0aec0">(' . $nights . ' đêm)</span></span></div>
    <div class="row"><span class="label">Phương thức TT</span><span>' . htmlspecialchars($method) . '</span></div>
    <div class="row"><span class="label">Tổng thanh toán</span><span>' . number_format($d['total_price'], 0, ',', '.') . ' VNĐ</span></div>
  </div>
  <span class="badge badge-pending">⏳ Đang chờ xác nhận</span>
  <div class="warning">Vui lòng hoàn tất thanh toán để xác nhận đặt phòng. Nếu cần hỗ trợ, liên hệ qua email hoặc hotline 037 384 8395.</div>
</div>';
    return _email_wrap('Xác nhận đặt phòng - StayGo', $body);
}

function _payment_template(array $d): string {
    $body = '
<div class="header"><h1>✅ StayGo</h1><p>Thanh toán thành công</p></div>
<div class="content">
  <p class="greeting">Xin chào <strong>' . htmlspecialchars($d['full_name']) . '</strong>,</p>
  <p style="color:#4a5568;font-size:15px">Chúng tôi đã nhận được thanh toán của bạn. Đặt phòng đã được xác nhận!</p>
  <div class="box">
    <div class="row"><span class="label">Mã đơn hàng</span><span><strong>' . htmlspecialchars($d['order_code']) . '</strong></span></div>
    <div class="row"><span class="label">Khách sạn</span><span>' . htmlspecialchars($d['hotel_name'] ?? '') . '</span></div>
    <div class="row"><span class="label">Nhận phòng</span><span>' . date('d/m/Y', strtotime($d['checkin'])) . '</span></div>
    <div class="row"><span class="label">Trả phòng</span><span>' . date('d/m/Y', strtotime($d['checkout'])) . '</span></div>
    <div class="row"><span class="label">Phương thức</span><span>' . htmlspecialchars($d['payment_method']) . '</span></div>
    <div class="row"><span class="label">Số tiền đã thanh toán</span><span>' . number_format($d['amount'], 0, ',', '.') . ' VNĐ</span></div>
  </div>
  <span class="badge badge-success">✓ Đã xác nhận</span>
  <p style="margin-top:20px;font-size:13px;color:#718096">Vui lòng đến khách sạn vào ngày <strong>' . date('d/m/Y', strtotime($d['checkin'])) . '</strong> và xuất trình mã đơn hàng tại lễ tân.</p>
</div>';
    return _email_wrap('Thanh toán thành công - StayGo', $body);
}

function _hotel_booking_template(array $d): string {
    $nights = max(1, (int)((strtotime($d['checkout']) - strtotime($d['checkin'])) / 86400));
    $body = '
<div class="header"><h1>🏨 StayGo</h1><p>Thông báo đặt phòng mới</p></div>
<div class="content">
  <p class="greeting">Xin chào <strong>' . htmlspecialchars($d['hotel_name']) . '</strong>,</p>
  <p style="color:#4a5568;font-size:15px">Bạn có một đặt phòng mới đã được xác nhận thanh toán qua <strong>' . htmlspecialchars($d['payment_method']) . '</strong>. Vui lòng chuẩn bị đón khách.</p>
  <div class="box">
    <div class="row"><span class="label">Mã đơn hàng</span><span><strong>' . htmlspecialchars($d['order_code']) . '</strong></span></div>
    <div class="row"><span class="label">Khách hàng</span><span>' . htmlspecialchars($d['full_name']) . '</span></div>
    <div class="row"><span class="label">Email khách</span><span>' . htmlspecialchars($d['guest_email']) . '</span></div>
    <div class="row"><span class="label">Loại phòng</span><span>' . htmlspecialchars($d['room_name']) . '</span></div>
    <div class="row"><span class="label">Nhận phòng</span><span>' . date('d/m/Y', strtotime($d['checkin'])) . '</span></div>
    <div class="row"><span class="label">Trả phòng</span><span>' . date('d/m/Y', strtotime($d['checkout'])) . ' (' . $nights . ' đêm)</span></div>
    <div class="row"><span class="label">Doanh thu đơn</span><span>' . number_format($d['amount'], 0, ',', '.') . ' VNĐ</span></div>
  </div>
  <span class="badge badge-success">✓ Đã xác nhận thanh toán</span>
  <p style="margin-top:16px;font-size:13px;color:#718096">Đăng nhập vào <a href="http://tour.local/hotel/dashboard.php" style="color:#1e73be">Hotel Portal</a> để xem chi tiết và quản lý đặt phòng.</p>
</div>';
    return _email_wrap('Đặt phòng mới - StayGo Partner', $body);
}
