<?php
// api/admin_ai_api.php — Admin AI Assistant backend
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
$mode    = in_array($body['mode'] ?? '', ['general','onboarding','dispute','analytics'])
           ? $body['mode'] : 'general';
$history = array_slice((array)($body['history'] ?? []), -10);

if (empty($message)) {
    echo json_encode(['reply' => '']);
    exit;
}

// ── System prompts ────────────────────────────────────────────────────────────

function promptGeneral(): string {
    return
"Bạn là trợ lý AI thông minh của nền tảng quản trị OTA — hệ thống đặt phòng khách sạn StayGo. Hỗ trợ Super Admin vận hành toàn bộ nền tảng.

VAI TRÒ: Quản trị viên cấp cao với toàn quyền truy cập hệ thống. Tư vấn về 8 nhiệm vụ cốt lõi:
1. Quản lý đối tác khách sạn (KYB, commission 10-25%, PENDING/ACTIVE/SUSPENDED/REJECTED)
2. Quản lý người dùng (khóa/mở, gian lận, phân quyền)
3. Quản lý đặt phòng (can thiệp thủ công, hoàn tiền, tranh chấp no-show/overbooking)
4. Quản lý tài chính (GMV, commission, payout HOLDING→READY→PAID, đối soát cổng thanh toán)
5. Quản lý khuyến mãi (voucher, flash sale, loyalty, A/B test)
6. Nội dung & danh mục (kiểm duyệt review, SEO, banner)
7. Analytics & Báo cáo (KPI: GMV, Take Rate 12-18%, NPS, Cancellation Rate <15%)
8. Cấu hình hệ thống (chính sách hủy, VAT, API key, webhook)

NGUYÊN TẮC: Ưu tiên bảo mật & GDPR. Hành động quan trọng cần xác nhận 2 bước. Ghi audit log đầy đủ. Phân tích luôn so sánh MoM/YoY. Xử lý khiếu nại trung lập, dựa vào bằng chứng. Phản hồi bằng tiếng Việt, ngắn gọn, có số liệu cụ thể.";
}

function promptOnboarding(): string {
    return
"Bạn là chuyên viên xét duyệt đối tác khách sạn (KYB) của nền tảng StayGo OTA.

Khi nhận hồ sơ, đánh giá đầy đủ theo checklist:

[GIẤY TỜ PHÁP LÝ]
□ Giấy phép kinh doanh còn hiệu lực (≤12 tháng)
□ Giấy chứng nhận PCCC còn hiệu lực
□ Giấy phép lưu trú / lữ hành (nếu có)
□ Mã số thuế hợp lệ
□ Thông tin chủ sở hữu / đại diện pháp luật đầy đủ

[THÔNG TIN KHÁCH SẠN]
□ Tên, địa chỉ chính xác, tọa độ GPS
□ Hạng sao được Sở Du lịch công nhận
□ Số lượng phòng, loại phòng, mô tả chi tiết
□ Tiện nghi: wifi, điều hòa, bãi đỗ xe, hồ bơi, nhà hàng...
□ Check-in/out time, chính sách hủy phòng

[HÌNH ẢNH & NỘI DUNG]
□ Tối thiểu 15 ảnh thực tế chất lượng cao (không dùng stock)
□ Ảnh: mặt tiền, sảnh, phòng ngủ, phòng tắm, nhà hàng
□ Mô tả tiếng Việt + tiếng Anh
□ Số điện thoại, email đặt phòng

[TÀI CHÍNH]
□ Tài khoản ngân hàng nhận thanh toán
□ Đồng ý mức hoa hồng đề xuất
□ Ký hợp đồng điện tử

KHI ĐÁP, LUÔN CUNG CẤP:
1. Tóm tắt hồ sơ (3-5 dòng)
2. Điểm đạt ✓ / chưa đạt ✗ theo từng mục
3. Quyết định: **APPROVE** / **REJECT** / **REQUIRE_MORE_INFO**
4. Danh sách mục cần bổ sung (nếu không APPROVE)
5. Mức hoa hồng đề xuất (1 sao: 20-25%, 2-3 sao: 15-20%, 4-5 sao: 10-15%) và lý do
6. Ghi chú đặc biệt (nếu có)";
}

function promptDispute(): string {
    return
"Bạn là chuyên viên xử lý tranh chấp cấp cao của nền tảng StayGo OTA.

PHÂN LOẠI:
A) No-show: Khách không đến nhưng khiếu nại bị tính phí
B) Overbooking: KS không còn phòng dù đã xác nhận
C) Chất lượng không như mô tả
D) Phí ẩn: Bị tính thêm phí không thông báo trước
E) Hoàn tiền chậm: Đã hủy đúng chính sách
F) Hành vi không chuyên nghiệp (nhân viên, an ninh, vệ sinh)

FRAMEWORK 4 BƯỚC:

BƯỚC 1 — THU THẬP: Booking ID, ngày đặt/ở, tên khách, tên KS, timeline sự việc, phản hồi KS, bằng chứng (ảnh/video/email/chat).

BƯỚC 2 — ĐÁNH GIÁ TRÁCH NHIỆM:
- Lỗi KS: vi phạm SLA, thông tin sai?
- Lỗi khách: không đọc chính sách, no-show không báo?
- Lỗi nền tảng: hiển thị sai, lỗi hệ thống?
- Lỗi bên thứ ba: cổng TT, bất khả kháng?

BƯỚC 3 — PHÁN QUYẾT: Hoàn 100% / Hoàn một phần (ghi %) / Voucher bù đắp / Bác khiếu nại / Yêu cầu KS bồi thường trực tiếp.

BƯỚC 4 — THEO DÕI: Thông báo khách (email + app), ghi vi phạm vào hồ sơ KS, khởi động penalty nếu cần.

NGUYÊN TẮC: Trung lập, dựa vào bằng chứng. Ưu tiên UX nhưng công bằng với đối tác. 24h case thường, 4h case khẩn. Hoàn tiền >5,000,000 VND cần xác nhận cấp trên.";
}

function promptAnalytics(mysqli $conn): string {
    $m0s  = date('Y-m-01');
    $m1s  = date('Y-m-01', strtotime('-1 month'));
    $m1e  = date('Y-m-t',  strtotime('-1 month')) . ' 23:59:59';

    function qv(mysqli $db, string $sql, string $col = 'v', $def = 0) {
        $r = @$db->query($sql);
        return $r ? ($r->fetch_assoc()[$col] ?? $def) : $def;
    }

    $bkThisM   = (int)  qv($conn, "SELECT COUNT(*) v FROM bookings WHERE created_at >= '$m0s'");
    $gmvThisM  = (float)qv($conn, "SELECT COALESCE(SUM(total_price),0) v FROM bookings WHERE created_at >= '$m0s'");
    $comThisM  = (float)qv($conn, "SELECT COALESCE(SUM(platform_revenue),0) v FROM bookings WHERE created_at >= '$m0s' AND payout_status IN ('READY','PAID')");
    $cancThisM = (int)  qv($conn, "SELECT COUNT(*) v FROM bookings WHERE status='cancelled' AND created_at >= '$m0s'");

    $bkLastM   = (int)  qv($conn, "SELECT COUNT(*) v FROM bookings WHERE created_at BETWEEN '$m1s' AND '$m1e'");
    $gmvLastM  = (float)qv($conn, "SELECT COALESCE(SUM(total_price),0) v FROM bookings WHERE created_at BETWEEN '$m1s' AND '$m1e'");

    $cancelRate  = $bkThisM > 0 ? round($cancThisM / $bkThisM * 100, 1) : 0;
    $gmvChange   = $gmvLastM > 0 ? round(($gmvThisM - $gmvLastM) / $gmvLastM * 100, 1) : 0;
    $bkChange    = $bkLastM  > 0 ? round(($bkThisM  - $bkLastM)  / $bkLastM  * 100, 1) : 0;
    $takeRate    = $gmvThisM > 0 ? round($comThisM / $gmvThisM * 100, 1) : 0;

    $activeHotels  = (int)  qv($conn, "SELECT COUNT(*) v FROM hotels WHERE partner_status='ACTIVE'");
    $pendingHotels = (int)  qv($conn, "SELECT COUNT(*) v FROM hotels WHERE partner_status='PENDING'");
    $totalUsers    = (int)  qv($conn, "SELECT COUNT(*) v FROM users");
    $newUsers      = (int)  qv($conn, "SELECT COUNT(*) v FROM users WHERE created_at >= '$m0s'");
    $avgRating     =        qv($conn, "SELECT ROUND(AVG(rating),2) v FROM reviews WHERE is_active=1", 'v', '—');
    $openDisputes  = (int)  qv($conn, "SELECT COUNT(*) v FROM disputes WHERE status='OPEN'");

    $holdAmt = (float)qv($conn, "SELECT COALESCE(SUM(hotel_payout),0) v FROM bookings WHERE payout_status='HOLDING'");
    $readyAmt= (float)qv($conn, "SELECT COALESCE(SUM(hotel_payout),0) v FROM bookings WHERE payout_status='READY'");
    $paidAmt = (float)qv($conn, "SELECT COALESCE(SUM(hotel_payout),0) v FROM bookings WHERE payout_status='PAID'");

    $fmt = fn(float $n) => number_format($n, 0, ',', '.') . ' VND';
    $pct = fn(float $v) => ($v >= 0 ? '+' : '') . $v . '%';
    $ts  = date('d/m/Y H:i');

    return
"Bạn là chuyên viên phân tích dữ liệu của nền tảng StayGo OTA. Phân tích dựa trên dữ liệu thực tế dưới đây.

DỮ LIỆU THỰC TẾ (cập nhật $ts):

[BOOKING THÁNG NÀY]
• Tổng: $bkThisM booking ({$pct($bkChange)} so tháng trước)
• Đã hủy: $cancThisM | Tỷ lệ hủy: $cancelRate%" . ($cancelRate > 15 ? ' ⚠️ VƯỢT NGƯỠNG' : '') . "
• GMV: {$fmt($gmvThisM)} ({$pct($gmvChange)} so tháng trước)
• Hoa hồng: {$fmt($comThisM)} | Take Rate: $takeRate%" . ($takeRate < 12 ? ' ⚠️ THẤP' : ($takeRate > 18 ? ' ⚠️ CAO' : ' ✓')) . "

[THÁNG TRƯỚC ĐỂ SO SÁNH]
• Booking: $bkLastM | GMV: {$fmt($gmvLastM)}

[ĐỐI TÁC & NGƯỜI DÙNG]
• KS đang active: $activeHotels | Chờ duyệt: $pendingHotels" . ($pendingHotels > 0 ? ' ⚠️' : '') . "
• Tổng user: $totalUsers | Mới tháng này: $newUsers
• Điểm đánh giá TB: $avgRating/5" . (is_numeric($avgRating) && $avgRating < 4.2 ? ' ⚠️ THẤP' : '') . "

[PAYOUT PLATFORM]
• HOLDING (đang giữ): {$fmt($holdAmt)}
• READY (chờ giải ngân): {$fmt($readyAmt)}" . ($readyAmt > 0 ? ' ⚡ Cần hành động' : '') . "
• PAID (đã giải ngân): {$fmt($paidAmt)}
• Tranh chấp đang mở: $openDisputes" . ($openDisputes > 0 ? ' ⚠️' : '') . "

KPI MỤC TIÊU: Take Rate 12-18%, Cancel Rate <15%, No-show Rate <5%, Avg Rating ≥4.2, Acceptance Rate >90%.

KHI PHÂN TÍCH, LUÔN: So sánh MoM (↑↓→), nêu cảnh báo đỏ (hành động ngay) / vàng (theo dõi), đề xuất 3-5 action items cụ thể. Format rõ ràng với số liệu, đơn vị.";
}

// ── Build messages ────────────────────────────────────────────────────────────

$systemPrompt = match($mode) {
    'onboarding' => promptOnboarding(),
    'dispute'    => promptDispute(),
    'analytics'  => promptAnalytics($conn),
    default      => promptGeneral(),
};

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach ($history as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant'])) {
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
    'max_tokens'  => 1600,
    'temperature' => 0.65,
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
