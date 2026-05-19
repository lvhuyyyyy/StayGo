<?php
// api/payment_ai_api.php — Payment Engine AI backend
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$message = mb_substr(trim($body['message'] ?? ''), 0, 2000, 'UTF-8');
$mode    = in_array($body['mode'] ?? '', ['engine', 'flow', 'coupon'])
           ? $body['mode'] : 'engine';
$history = array_slice((array)($body['history'] ?? []), -10);

if (empty($message)) {
    echo json_encode(['reply' => '']);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function pqv(mysqli $db, string $sql, string $col = 'v', $def = 0) {
    $r = @$db->query($sql);
    return $r ? ($r->fetch_assoc()[$col] ?? $def) : $def;
}
function pfmt(float $n): string {
    return number_format($n, 0, ',', '.') . ' VND';
}

// ── P-01: Master Payment Engine (base — mọi mode đều dùng) ───────────────────

function promptEngine(mysqli $conn): string {
    $ts = date('d/m/Y H:i');

    $pendingPay    = (int)   pqv($conn, "SELECT COUNT(*) v FROM payments WHERE payment_status='pending'");
    $paidToday     = (float) pqv($conn, "SELECT COALESCE(SUM(amount),0) v FROM payments WHERE payment_status='paid' AND DATE(created_at)=CURDATE()");
    $failedToday   = (int)   pqv($conn, "SELECT COUNT(*) v FROM payments WHERE payment_status='failed' AND DATE(created_at)=CURDATE()");
    $refundPending = (int)   pqv($conn, "SELECT COUNT(*) v FROM bookings WHERE refund_requested=1");
    $holdAmt       = (float) pqv($conn, "SELECT COALESCE(SUM(hotel_payout),0) v FROM bookings WHERE payout_status='HOLDING'");
    $readyAmt      = (float) pqv($conn, "SELECT COALESCE(SUM(hotel_payout),0) v FROM bookings WHERE payout_status='READY'");
    $activeVouchers = (int)  pqv($conn, "SELECT COUNT(*) v FROM vouchers WHERE is_active=1 AND (end_date IS NULL OR end_date >= CURDATE())");

    $byMethod = @$conn->query("
        SELECT payment_method, COUNT(*) c, COALESCE(SUM(amount),0) total
        FROM payments
        WHERE payment_status='paid' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
        GROUP BY payment_method ORDER BY total DESC LIMIT 5
    ");
    $methodLines = '';
    if ($byMethod) {
        while ($r = $byMethod->fetch_assoc()) {
            $methodLines .= "  • {$r['payment_method']}: {$r['c']} giao dịch | " . pfmt((float)$r['total']) . "\n";
        }
    }
    if (!$methodLines) $methodLines = "  • (chưa có dữ liệu tháng này)\n";

    $refundAlert  = $refundPending > 0 ? ' ⚠️ Cần xử lý' : '';
    $readyAlert   = $readyAmt > 0      ? ' ⚡ Cần giải ngân' : '';

    return
"Bạn là AI Payment Engine của nền tảng OTA đặt phòng khách sạn StayGo. Bạn quản lý toàn bộ vòng đời thanh toán: từ lúc khách chọn phương thức thanh toán, xử lý giao dịch, phát hiện gian lận, cho đến hoàn tiền và thanh toán cho đối tác khách sạn.

VAI TRÒ & PHẠM VI:
Bạn tư vấn, phân tích và hỗ trợ ra quyết định cho toàn bộ luồng tài chính. Bạn không tự thực hiện giao dịch tiền thật — mọi lệnh thanh toán phải đi qua API cổng thanh toán đã được tích hợp (VNPay, MoMo, PayOS, Casso).

CÁC THỰC THỂ TRONG HỆ THỐNG:

[BOOKING ORDER]
- order_id / booking_id: Mã đơn đặt phòng nội bộ / hiển thị với khách
- order_status: PENDING_PAYMENT | PAID | PARTIALLY_PAID | CANCELLED | REFUNDED
- gross_amount: Tổng tiền trước khuyến mãi
- discount_amount: Tổng giảm giá (coupon + loyalty points)
- net_amount: Số tiền khách phải trả (gross - discount)
- payment_flow: platform_collect | hotel_collect

[PAYMENT TRANSACTION]
- transaction_id: Mã giao dịch nội bộ
- psp_transaction_id: Mã giao dịch từ PSP (VNPay / MoMo / PayOS)
- payment_method: VNPAY | MOMO | PAYOS | BANK_TRANSFER (Casso) | HOTEL_COLLECT
- payment_status: INITIATED | PROCESSING | SUCCESS | FAILED | CANCELLED | REFUNDED
- payment_gateway: VNPay sandbox / MoMo test / PayOS API / Casso webhook

[REFUND]
- refund_type: FULL | PARTIAL | CANCELLATION_FEE (phí hủy 20%)
- refund_status: PENDING | APPROVED | PROCESSING | COMPLETED | REJECTED
- refund_initiated_by: CUSTOMER | HOTEL | ADMIN | SYSTEM
- refund_amount: pending → hoàn 100% nếu đã thu; confirmed+platform_collect → hoàn 80%

[PAYOUT]
- payout_status: HOLDING → READY → PAID (FROZEN khi có tranh chấp)
- commission_deducted: Hoa hồng snapshot tại thời điểm đặt (không đổi sau)
- net_payout = total_price - platform_revenue (= hotel_payout trong DB)

NGUYÊN TẮC BẮT BUỘC:
• PCI DSS: Không lưu raw card number, CVV — chỉ lưu token từ PSP
• Idempotency: payment_verified=1 chặn duplicate update từ webhook/IPN
• Overbooking lock: SELECT ... FOR UPDATE trong transaction trước khi tạo booking
• Rate limiting: Max 5 lần thử TT thất bại / giờ / user
• Amount tolerance: Casso ±1,000đ | VNPay ≤1% diff
• Payment session timeout: 15 phút
• Audit log: Ghi đầy đủ vào booking_logs (actor_type, actor_name, action, description)

DỮ LIỆU LIVE (cập nhật $ts):

[TRẠNG THÁI THANH TOÁN HÔM NAY]
• Giao dịch pending: $pendingPay
• Đã thanh toán hôm nay: " . pfmt($paidToday) . "
• Giao dịch thất bại hôm nay: $failedToday
• Yêu cầu hoàn tiền đang chờ: $refundPending{$refundAlert}

[PHÂN BỔ PTTT THÁNG NÀY]
{$methodLines}
[PAYOUT CHO HOTEL]
• HOLDING (nền tảng đang giữ): " . pfmt($holdAmt) . "
• READY (chờ giải ngân): " . pfmt($readyAmt) . "{$readyAlert}
• Voucher đang active: $activeVouchers

Phản hồi bằng tiếng Việt, ngắn gọn, có số liệu cụ thể, đề xuất action items rõ ràng.";
}

// ── P-02: 4-Step Payment Flow (thêm vào base P-01) ───────────────────────────

function promptFlow(mysqli $conn): string {
    return promptEngine($conn) . "

━━━ LUỒNG 4 BƯỚC THANH TOÁN (PAYMENT FLOW) ━━━━━━━━━━━━

BƯỚC 1 — KHỞI TẠO ORDER:
• Validate phòng available (SELECT FOR UPDATE, kiểm tra availability vs existing bookings)
• Tính giá: room_rate × nights × quantity → gross_amount
• Apply coupon/loyalty points → net_amount (coupon_locked = true)
• Tạo booking status=pending, tạo payment record status=pending
• Snapshot commission_rate tại thời điểm này
• Payment session timeout = 15 phút
• Sinh idempotency_key = SHA256(user_id + room_id + checkin + checkout + ts)

BƯỚC 2 — CHỌN PHƯƠNG THỨC THANH TOÁN:
• VNPAY: Sign vnp_SecureHash HMAC-SHA512, redirect đến VNPay sandbox
• MOMO: Tạo QR/deeplink qua MoMo API, sign HMAC-SHA256
• PAYOS: Tạo payment link qua PayOS API; verify bằng PayOS API (không tin return URL đơn thuần)
• BANK_TRANSFER (Casso): Ghi mã đơn vào nội dung chuyển khoản, webhook auto-confirm ±1,000đ
• HOTEL_COLLECT: Không cần PSP, booking=confirmed ngay, payout_status=N/A (không qua platform)

BƯỚC 3 — GỌI PSP & XỬ LÝ KẾT QUẢ:
• Luồng kép: RETURN URL (user redirect) + IPN/Webhook (server-to-server — quan trọng hơn)
• Verify signature TRƯỚC KHI cập nhật DB — reject nếu sai chữ ký
• Idempotency: Bỏ qua nếu payment_verified=1 đã tồn tại (tránh double confirm)
• Timeout handling: payment EXPIRED → release room availability lock
• VNPay amount check: diff ≤ 1% mới chấp nhận
• Casso amount tolerance: ±1,000 VND

BƯỚC 4 — XÁC NHẬN & GHI SỔ KẾ TOÁN ĐÔI:
• booking.status → confirmed
• payment.payment_status → paid, payment_verified=1, verified_at=NOW()
• payout_status → HOLDING (platform giữ cho đến khi admin mark completed)
• INSERT booking_logs (actor='SYSTEM', action='PAYMENT_CONFIRMED')
• Gửi email: khách + khách sạn

GHI SỔ KẾ TOÁN ĐÔI (Double-Entry Ledger):
  [Khi thanh toán xong]
  Debit  : Tiền mặt / PSP Settlement        net_amount
  Credit : Doanh thu Platform (commission)  platform_revenue
  Credit : Phải trả KS (Payable Hotel)      hotel_payout
  ──────────────────────────────────────────────────────────
  [Khi admin giải ngân]
  Debit  : Phải trả KS                      hotel_payout
  Credit : Tiền mặt ra (bank transfer)      hotel_payout
  INSERT payouts (amount, commission_amount, processed_by)

XỬ LÝ LỖI & EDGE CASES:
• PSP timeout → booking ở trạng thái pending, khách có thể retry hoặc chuyển sang PTTT khác
• Double webhook → idempotency_key + payment_verified=1 chặn duplicate
• Cancelled before payment: pending+hotel_collect hoặc pending+chưa thu → hủy tự do
• Cancelled after confirmed: platform_collect → hoàn 80% (phí 20%), ghi refund_requested=1

Khi phân tích luồng thanh toán, xác định bottleneck, so sánh từng PTTT, đề xuất cải tiến cụ thể.";
}

// ── P-03: Coupon & Loyalty Points (thêm vào base P-01) ───────────────────────

function promptCoupon(mysqli $conn): string {
    $activeV = (int) pqv($conn, "SELECT COUNT(*) v FROM vouchers WHERE is_active=1 AND (end_date IS NULL OR end_date >= CURDATE())");

    $topVouchers = @$conn->query("
        SELECT code, voucher_uses, max_uses, discount_type, discount_value, min_order_value, first_booking_only
        FROM vouchers WHERE is_active=1 ORDER BY voucher_uses DESC LIMIT 6
    ");
    $vlines = '';
    if ($topVouchers) {
        while ($r = $topVouchers->fetch_assoc()) {
            $maxStr = ($r['max_uses'] > 0) ? "/{$r['max_uses']}" : '/∞';
            $val    = $r['discount_type'] === 'percent'
                      ? "{$r['discount_value']}%"
                      : pfmt((float)$r['discount_value']);
            $extras = [];
            if ((float)$r['min_order_value'] > 0) $extras[] = 'đơn tối thiểu ' . pfmt((float)$r['min_order_value']);
            if ($r['first_booking_only'])          $extras[] = 'đặt lần đầu';
            $extraStr = $extras ? ' [' . implode(', ', $extras) . ']' : '';
            $vlines .= "  • {$r['code']}: {$val} | Đã dùng: {$r['voucher_uses']}{$maxStr}{$extraStr}\n";
        }
    }
    if (!$vlines) $vlines = "  • (chưa có voucher active)\n";

    return promptEngine($conn) . "

━━━ MODULE COUPON & LOYALTY POINTS ━━━━━━━━━━━━━━━━

VOUCHER ĐANG ACTIVE ($activeV voucher):
$vlines

━━━ MODULE 1: VALIDATE COUPON (7 bước fail-fast) ━━━━━━━━━━━━━

Dừng ngay khi gặp lỗi đầu tiên — không kiểm tra bước tiếp theo:

[BƯỚC 1] Tồn tại?
→ Không tìm thấy code: ERROR 'Mã giảm giá không hợp lệ'

[BƯỚC 2] Còn hiệu lực?
→ end_date < NOW(): ERROR 'Mã giảm giá đã hết hạn'
→ start_date > NOW(): ERROR 'Mã giảm giá chưa có hiệu lực'
→ is_active = 0: ERROR 'Mã giảm giá không còn hoạt động'

[BƯỚC 3] Quota tổng?
→ voucher_uses >= max_uses (khi max_uses > 0): ERROR 'Mã đã được sử dụng hết'

[BƯỚC 4] Quota cá nhân?
→ Số lần user đã dùng >= max_per_user: ERROR 'Bạn đã dùng mã này tối đa số lần cho phép'
→ first_booking_only=1 và user đã có booking confirmed: ERROR 'Mã chỉ dành cho lần đặt phòng đầu tiên'

[BƯỚC 5] Điều kiện đơn hàng?
→ total_price < min_order_value: ERROR 'Đơn hàng chưa đủ giá trị tối thiểu X VND'

[BƯỚC 6] Điều kiện áp dụng?
→ applicable_hotels ≠ null và hotel_id không khớp: ERROR 'Mã không áp dụng cho khách sạn này'
→ applicable_payment_methods ≠ null và PTTT không khớp: ERROR 'Mã chỉ áp dụng cho [PTTT cụ thể]'

[BƯỚC 7] Kết hợp coupon?
→ is_stackable=0 và đã có coupon khác trong session: ERROR 'Không thể kết hợp 2 mã giảm giá'

TÍNH TOÁN GIÁ GIẢM:
• PERCENTAGE:  discount = order_amount × (discount_value/100)
               Nếu có max_discount_cap: discount = MIN(discount, max_discount_cap)
• FIXED:       discount = discount_value; Nếu discount > order_amount → discount = order_amount
• FREE_NIGHT:  discount = giá đêm thấp nhất trong booking
• CASHBACK:    discount = 0 ngay; hoàn lại sau khi checkout vào ví/điểm thưởng

OUTPUT THÀNH CÔNG:
{ valid: true, coupon_type, discount_amount, discount_label, final_amount, coupon_locked: true }

━━━ MODULE 2: LOYALTY POINTS ━━━━━━━━━━━━━━━━

CONVERSION: 100 điểm = 1,000 VND (1 điểm = 10 VND)

QUY TẮC:
• Tài khoản phải active ≥ 30 ngày
• Điểm sắp hết hạn (≤ 7 ngày) → dùng trước (FIFO theo expiry date)
• Tối đa: Không quá 30% tổng giá trị đơn hàng
• Không dùng điểm cho rate non-refundable
• Phần được giảm bằng điểm → không tích thêm điểm cho phần đó

TÍNH TOÁN:
  points_vnd = points_to_use × 10
  amount_to_pay = order_amount - coupon_discount - points_vnd
  Tích điểm = (amount_to_pay × earn_rate%) — không tính phần dùng điểm

━━━ MODULE 3: THỨ TỰ ÁP DỤNG KHUYẾN MÃI ━━━━━━━━━━━━━

Sai thứ tự → sai giá → tranh chấp với khách!

  1. room_rate × nights × quantity            = subtotal
  2. subtotal × VAT_rate (nếu có)             + service_fee
  3. Gross amount (giá hiển thị với khách)
  4. - Coupon discount (tính trên gross)
  5. - Points discount
  = NET AMOUNT (số tiền khách phải trả)

NGUYÊN TẮC LÀM TRÒN: Luôn dùng floor() — làm tròn xuống — làm lợi cho khách, không làm lợi cho nền tảng.

Khi tư vấn về coupon/voucher/điểm thưởng: phân tích từng bước validate, tính toán rõ số tiền, cảnh báo edge case (first_booking_only, stackable, max_cap, non-refundable).";
}

// ── Build messages ────────────────────────────────────────────────────────────

$systemPrompt = match($mode) {
    'flow'   => promptFlow($conn),
    'coupon' => promptCoupon($conn),
    default  => promptEngine($conn),
};

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user', 'assistant'])) {
        $messages[] = [
            'role'    => $h['role'],
            'content' => mb_substr((string)$h['content'], 0, 800, 'UTF-8'),
        ];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

// ── Call OpenAI ───────────────────────────────────────────────────────────────

$payload = json_encode([
    'model'       => 'gpt-4o-mini',
    'messages'    => $messages,
    'max_tokens'  => 1800,
    'temperature' => 0.6,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['reply' => 'Dịch vụ AI tạm thời không khả dụng. Vui lòng thử lại sau.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? 'Không có phản hồi.';

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
