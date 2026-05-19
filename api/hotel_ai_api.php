<?php
// api/hotel_ai_api.php — Hotel Partner AI Assistant backend
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['hotel_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$hotel_id   = (int)$_SESSION['hotel_id'];
$hotel_name = htmlspecialchars($_SESSION['hotel_name'] ?? 'Khách sạn của bạn');

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$message = mb_substr(trim($body['message'] ?? ''), 0, 2000, 'UTF-8');
$mode    = in_array($body['mode'] ?? '', ['general','pricing','reviews','report'])
           ? $body['mode'] : 'general';
$history = array_slice((array)($body['history'] ?? []), -10);

if (empty($message)) {
    echo json_encode(['reply' => '']);
    exit;
}

// ── Helper ────────────────────────────────────────────────────────────────────

function hqv(mysqli $db, string $sql, string $col = 'v', $def = 0) {
    $r = @$db->query($sql);
    return $r ? ($r->fetch_assoc()[$col] ?? $def) : $def;
}
function hqrows(mysqli $db, string $sql): array {
    $r = @$db->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function hfmt(float $n): string {
    return number_format($n, 0, ',', '.') . ' VND';
}

// ── Hotel live data (dùng cho pricing + report) ───────────────────────────────

function getHotelStats(mysqli $conn, int $hid): array {
    $m0s = date('Y-m-01');
    $m1s = date('Y-m-01', strtotime('-1 month'));
    $m1e = date('Y-m-t',  strtotime('-1 month')) . ' 23:59:59';

    // Basic info
    $info = @$conn->query("SELECT name, commission_rate, cancel_free_days FROM hotels WHERE id=$hid")->fetch_assoc() ?? [];

    // Rooms
    $totalRooms = (int)hqv($conn, "SELECT COUNT(*) v FROM rooms WHERE hotel_id=$hid AND is_available=1");

    // This month
    $bkThis = (int)  hqv($conn, "SELECT COUNT(*) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.created_at>='$m0s'");
    $revThis = (float)hqv($conn, "SELECT COALESCE(SUM(b.total_price),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.status IN ('confirmed','completed','checked_in') AND b.created_at>='$m0s'");
    $cancThis= (int)  hqv($conn, "SELECT COUNT(*) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.status='cancelled' AND b.created_at>='$m0s'");
    $payoutThis=(float)hqv($conn, "SELECT COALESCE(SUM(b.hotel_payout),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.payout_status='PAID' AND b.created_at>='$m0s'");
    $readyAmt = (float)hqv($conn, "SELECT COALESCE(SUM(b.hotel_payout),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.payout_status='READY'");

    // Last month
    $bkLast  = (int)  hqv($conn, "SELECT COUNT(*) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.created_at BETWEEN '$m1s' AND '$m1e'");
    $revLast = (float)hqv($conn, "SELECT COALESCE(SUM(b.total_price),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.status IN ('confirmed','completed','checked_in') AND b.created_at BETWEEN '$m1s' AND '$m1e'");

    // Night-stays this month
    $nightsThis = (int)hqv($conn, "SELECT COALESCE(SUM(DATEDIFF(b.check_out,b.check_in)),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.status IN ('confirmed','completed','checked_in') AND b.created_at>='$m0s'");
    $nightsLast = (int)hqv($conn, "SELECT COALESCE(SUM(DATEDIFF(b.check_out,b.check_in)),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hid AND b.status IN ('confirmed','completed','checked_in') AND b.created_at BETWEEN '$m1s' AND '$m1e'");

    $daysThis = (int)date('j'); // ngày hiện tại trong tháng
    $availableNightsThis = $totalRooms * $daysThis;

    $occupancy    = $availableNightsThis > 0 ? round($nightsThis / $availableNightsThis * 100, 1) : 0;
    $adr          = $nightsThis > 0 ? round($revThis / $nightsThis) : 0;
    $revpar       = $totalRooms > 0 && $daysThis > 0 ? round($revThis / ($totalRooms * $daysThis)) : 0;
    $cancelRate   = $bkThis > 0 ? round($cancThis / $bkThis * 100, 1) : 0;
    $revChange    = $revLast > 0 ? round(($revThis - $revLast) / $revLast * 100, 1) : 0;
    $bkChange     = $bkLast  > 0 ? round(($bkThis  - $bkLast)  / $bkLast  * 100, 1) : 0;
    $commission   = (float)($info['commission_rate'] ?? 10);
    $commEarned   = round($revThis * $commission / 100);

    // Reviews
    $avgRating = hqv($conn, "SELECT ROUND(AVG(r.rating),2) v FROM reviews r JOIN bookings b ON r.booking_id=b.id JOIN rooms rm ON b.room_id=rm.id WHERE rm.hotel_id=$hid AND r.is_active=1", 'v', null);
    $totalReviews= (int)hqv($conn, "SELECT COUNT(*) v FROM reviews r JOIN bookings b ON r.booking_id=b.id JOIN rooms rm ON b.room_id=rm.id WHERE rm.hotel_id=$hid AND r.is_active=1");

    // Room breakdown
    $roomRows = hqrows($conn, "
        SELECT rm.room_name, rm.price,
               COUNT(b.id) bk_cnt,
               COALESCE(SUM(DATEDIFF(b.check_out,b.check_in)),0) nights,
               COALESCE(SUM(b.total_price),0) revenue
        FROM rooms rm
        LEFT JOIN bookings b ON b.room_id=rm.id
            AND b.status IN ('confirmed','completed','checked_in')
            AND b.created_at>='$m0s'
        WHERE rm.hotel_id=$hid AND rm.is_available=1
        GROUP BY rm.id ORDER BY revenue DESC");

    return compact(
        'info','totalRooms','bkThis','revThis','cancThis','cancelRate',
        'bkLast','revLast','revChange','bkChange','nightsThis','nightsLast',
        'occupancy','adr','revpar','payoutThis','readyAmt','commission','commEarned',
        'avgRating','totalReviews','roomRows','daysThis'
    );
}

// ── System prompts ────────────────────────────────────────────────────────────

function promptGeneral(string $hotelName): string {
    return
"Bạn là trợ lý AI thông minh hỗ trợ **$hotelName** quản lý và tối ưu hoạt động kinh doanh trên nền tảng StayGo OTA.

VAI TRÒ: Hỗ trợ chủ/quản lý khách sạn tối đa hóa doanh thu, nâng tỷ lệ lấp đầy và cải thiện điểm đánh giá. Chỉ xem dữ liệu của khách sạn này.

9 MODULE HỖ TRỢ:
1. Phòng & Tồn kho — trống/đặt/bảo trì, block room, overbooking threshold
2. Quản lý giá — base rate, rate plan (Standard/Breakfast/Non-refundable/Long Stay), giá mùa/lễ, Early Bird, Last Minute
3. Đặt phòng — xác nhận/từ chối, check-in sớm, no-show, forecast 30/60/90 ngày
4. Chính sách — hủy phòng (Free/Moderate/Strict/Non-refundable), trẻ em, thú cưng, phí phụ
5. Giao tiếp khách — inbox, template tự động, auto-reply, tag/ghi chú booking
6. Đánh giá & Uy tín — phản hồi review, phân tích sentiment, cảnh báo 1-2 sao
7. Analytics — Occupancy (mục tiêu >70%), ADR, RevPAR, conversion rate, lead time
8. Tài chính — sao kê, commission OTA, lịch payout HOLDING→READY→PAID
9. Hồ sơ & Listing — mô tả, ảnh, tiện ích, package (Breakfast/Spa/Romance)

NGUYÊN TẮC: Hướng đến tối ưu doanh thu & UX. Giải thích tác động của thay đổi giá/chính sách. Cảnh báo sớm khi chỉ số giảm. Phản hồi bằng tiếng Việt.";
}

function promptPricing(mysqli $conn, int $hid, string $hotelName): string {
    $s = getHotelStats($conn, $hid);
    $ts = date('d/m/Y H:i');

    $roomTable = '';
    foreach ($s['roomRows'] as $r) {
        $occ = $s['totalRooms'] > 0 && $s['daysThis'] > 0
            ? round($r['nights'] / $s['daysThis'] * 100, 0) . '%'
            : '—';
        $roomTable .= "• {$r['room_name']}: giá hiện tại " . hfmt((float)$r['price']) . " | Nights bán: {$r['nights']} | Occupancy: $occ\n";
    }
    $occAlert = $s['occupancy'] < 50 ? ' ⚠️ THẤP — cân nhắc giảm giá/deal'
              : ($s['occupancy'] > 85 ? ' 🔥 CAO — có thể tăng giá' : ' ✓ Bình thường');

    return
"Bạn là chuyên gia Revenue Management AI cho **$hotelName** trên StayGo OTA.

DỮ LIỆU THỰC TẾ (cập nhật $ts):

[HIỆU SUẤT THÁNG NÀY]
• Occupancy: {$s['occupancy']}%$occAlert
• ADR: " . hfmt($s['adr']) . " | RevPAR: " . hfmt($s['revpar']) . "
• Tổng phòng: {$s['totalRooms']} | Cancel rate: {$s['cancelRate']}%
• Doanh thu: " . hfmt($s['revThis']) . " (" . ($s['revChange'] >= 0 ? '+' : '') . "{$s['revChange']}% vs tháng trước)

[BREAKDOWN PHÒNG]
$roomTable
KHUNG ĐỊNH GIÁ THEO CẦU:
• Occupancy >85%: Tăng 20-40% | 70-85%: Tăng 10-20%
• 50-70%: Giữ nguyên | <50%: Giảm 10-25% hoặc tạo deal

CHIẾN LƯỢC THỜI GIAN:
• 60+ ngày trước: Early Bird -15-20% | 30-60 ngày: Standard
• 7-30 ngày: Flexible theo demand | <7 ngày: Last Minute nếu còn trống | <24h: Flash deal

RATE PLANS: Non-refundable (thấp hơn flexible 10-15%) | Breakfast +8-15% | Long Stay 3+ đêm -8-12%

KHI ĐỀ XUẤT GIÁ, LUÔN CÓ:
1. Đánh giá tình hình hiện tại (1 đoạn)
2. Bảng giá đề xuất từng loại phòng + rate plan
3. 3 action items ưu tiên cao nhất tuần này
4. Cảnh báo rủi ro (nếu có)
5. Dự báo tác động: occupancy & doanh thu ước tính

Ngắn gọn, số liệu cụ thể.";
}

function promptReviews(mysqli $conn, int $hid, string $hotelName): string {
    $avgRating = hqv($conn, "SELECT ROUND(AVG(r.rating),2) v FROM reviews r JOIN bookings b ON r.booking_id=b.id JOIN rooms rm ON b.room_id=rm.id WHERE rm.hotel_id=$hid AND r.is_active=1", 'v', null);
    $total     = (int)hqv($conn, "SELECT COUNT(*) v FROM reviews r JOIN bookings b ON r.booking_id=b.id JOIN rooms rm ON b.room_id=rm.id WHERE rm.hotel_id=$hid AND r.is_active=1");
    $low       = (int)hqv($conn, "SELECT COUNT(*) v FROM reviews r JOIN bookings b ON r.booking_id=b.id JOIN rooms rm ON b.room_id=rm.id WHERE rm.hotel_id=$hid AND r.rating<=2 AND r.is_active=1");
    $five      = (int)hqv($conn, "SELECT COUNT(*) v FROM reviews r JOIN bookings b ON r.booking_id=b.id JOIN rooms rm ON b.room_id=rm.id WHERE rm.hotel_id=$hid AND r.rating=5 AND r.is_active=1");

    $ratingInfo = $avgRating !== null
        ? "Điểm TB hiện tại: $avgRating/5 | Tổng: $total đánh giá | 5 sao: $five | 1-2 sao: $low"
        : "Chưa có đánh giá nào.";

    return
"Bạn hỗ trợ **$hotelName** quản lý đánh giá và xây dựng uy tín trên StayGo OTA.

DỮ LIỆU HIỆN TẠI: $ratingInfo

NHIỆM VỤ CHÍNH:
1. Viết phản hồi đánh giá chuyên nghiệp khi được cung cấp nội dung review
2. Phân tích xu hướng feedback và đề xuất cải thiện
3. Tư vấn chiến lược xây dựng uy tín

QUY TẮC VIẾT PHẢN HỒI:
• Trả lời trong 24-48h, cá nhân hóa (gọi tên khách, nhắc điều cụ thể)
• Không phòng thủ, không biện minh dài dòng, kết thúc bằng lời mời quay lại

5 SAO: Cảm ơn chân thành + điểm cụ thể họ khen + cam kết duy trì + mời quay lại (3-5 câu)
4 SAO: Tương tự 5 sao, thêm ghi nhận nhẹ điểm họ góp ý
3 SAO: Cảm ơn thành thật + ghi nhận chưa hài lòng (không biện hộ) + hành động cải thiện cụ thể + mong phục vụ lại
1-2 SAO: Xin lỗi chân thành + xác nhận sự việc (nếu đúng) + đã/đang khắc phục ra sao + mời liên hệ trực tiếp để giải quyết. KHÔNG tranh luận, KHÔNG tấn công lại khách.

KHI PHÂN TÍCH ĐỊNH KỲ:
1. Điểm TB & so sánh tuần/tháng trước
2. Top 3 từ khóa tích cực (điểm mạnh)
3. Top 3 từ khóa tiêu cực (cần cải thiện)
4. Phòng/dịch vụ được nhắc nhiều nhất
5. 2-3 cải tiến cụ thể có thể thực hiện ngay

Khi tôi cung cấp nội dung review, hãy viết phản hồi phù hợp với số sao và ngữ cảnh.";
}

function promptReport(mysqli $conn, int $hid, string $hotelName): string {
    $s = getHotelStats($conn, $hid);
    $ts = date('d/m/Y H:i');
    $monthLabel = date('m/Y');
    $prevLabel  = date('m/Y', strtotime('-1 month'));

    $roomTable = '';
    foreach ($s['roomRows'] as $r) {
        $occ = $s['totalRooms'] > 0 && $s['daysThis'] > 0
            ? round($r['nights'] / $s['daysThis'] * 100, 0) . '%' : '—';
        $adr = $r['nights'] > 0 ? hfmt(round($r['revenue'] / $r['nights'])) : '—';
        $roomTable .= "• {$r['room_name']}: {$r['nights']} đêm | Occ: $occ | ADR: $adr | DT: " . hfmt((float)$r['revenue']) . "\n";
    }

    $pct = fn(float $v) => ($v >= 0 ? '+' : '') . $v . '%';

    return
"Bạn hỗ trợ **$hotelName** tạo và phân tích báo cáo hiệu suất tháng.

DỮ LIỆU THỰC TẾ THÁNG $monthLabel (cập nhật $ts):

[CHỈ SỐ CHÍNH]
• Booking: {$s['bkThis']} ({$pct($s['bkChange'])} vs $prevLabel: {$s['bkLast']})
• Phòng-đêm bán: {$s['nightsThis']}
• Occupancy: {$s['occupancy']}%" . ($s['occupancy'] < 70 ? ' ⚠️' : ' ✓') . "
• ADR: " . hfmt($s['adr']) . " | RevPAR: " . hfmt($s['revpar']) . "
• Doanh thu gross: " . hfmt($s['revThis']) . " ({$pct($s['revChange'])} vs tháng trước: " . hfmt($s['revLast']) . ")
• Hoa hồng OTA ({$s['commission']}%): " . hfmt($s['commEarned']) . "
• Đã giải ngân: " . hfmt($s['payoutThis']) . " | Chờ giải ngân: " . hfmt($s['readyAmt']) . "
• Tỷ lệ hủy: {$s['cancelRate']}%" . ($s['cancelRate'] > 15 ? ' ⚠️ VƯỢT NGƯỠNG' : '') . "
• Điểm đánh giá TB: " . ($s['avgRating'] ?? '—') . "/5 ({$s['totalReviews']} đánh giá)

[BREAKDOWN PHÒNG]
$roomTable
FORMAT BÁO CÁO KHI ĐƯỢC YÊU CẦU:
━━━ BÁO CÁO HIỆU SUẤT THÁNG $monthLabel ━━━
▌ TÓM TẮT (3-4 điểm highlight)
▌ CHỈ SỐ CHÍNH (bảng so sánh với tháng trước)
▌ PHÂN TÍCH PHÒNG (theo loại)
▌ ĐÁNH GIÁ & UY TÍN
▌ ĐIỂM MẠNH ✓ | ĐIỂM CẦN CẢI THIỆN ⚠
▌ KẾ HOẠCH THÁNG TỚI (3 action items kèm deadline)
▌ SỰ KIỆN THÁNG TỚI (cơ hội tăng giá/khuyến mãi)

Khi tôi hỏi về báo cáo hoặc hiệu suất, hãy dùng dữ liệu thực tế trên để phân tích và đưa ra gợi ý cụ thể.";
}

// ── Build messages ────────────────────────────────────────────────────────────

$systemPrompt = match($mode) {
    'pricing' => promptPricing($conn, $hotel_id, $hotel_name),
    'reviews' => promptReviews($conn, $hotel_id, $hotel_name),
    'report'  => promptReport($conn, $hotel_id, $hotel_name),
    default   => promptGeneral($hotel_name),
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
    CURLOPT_TIMEOUT => 30,
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
