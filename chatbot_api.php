<?php
// ===================================================
// chatbot_api.php - Nâng cấp: intent mới, context-aware, quick replies
// Lịch sử hội thoại: PHP Session (không lưu DB)
// ===================================================

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Chỉ chấp nhận POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── Cấu hình: lấy từ config thay vì hardcode ─────────────────────────────────
require_once __DIR__ . '/config/database.php';
$apiKey = OPENAI_API_KEY;
$mysqli = $conn; // dùng lại kết nối đã có, không tạo mới

// ── Rate limiting: tối đa 20 tin nhắn / phút / session ───────────────────────
if (!isset($_SESSION['chat_rate'])) $_SESSION['chat_rate'] = [];
$_SESSION['chat_rate'] = array_values(array_filter(
    $_SESSION['chat_rate'],
    fn($t) => $t > time() - 60
));
if (count($_SESSION['chat_rate']) >= 20) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'reply'         => 'Bạn đang gửi quá nhanh. Vui lòng chờ một chút rồi thử lại!',
        'quick_replies' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION['chat_rate'][] = time();

// ── Lấy & giới hạn độ dài tin nhắn ──────────────────────────────────────────
$message = mb_substr(trim($_POST['message'] ?? ''), 0, 500, 'UTF-8');
if (empty($message)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['reply' => '', 'quick_replies' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
$messageLower = mb_strtolower($message, 'UTF-8');
$userId       = $_SESSION['user_id'] ?? null;

// ==================================================
// CONTEXT-AWARE QUA PHP SESSION
// Lưu tối đa 5 lượt hội thoại gần nhất
// ==================================================
if (!isset($_SESSION['chat_context'])) {
    $_SESSION['chat_context'] = [];
}

// Lấy context: khách sạn/địa điểm đã nhắc ở tin trước
$ctxHotelId    = $_SESSION['chat_context']['last_hotel_id']   ?? null;
$ctxHotelName  = $_SESSION['chat_context']['last_hotel_name'] ?? null;
$ctxLocationId = $_SESSION['chat_context']['last_location_id']   ?? null;
$ctxLocationName = $_SESSION['chat_context']['last_location_name'] ?? null;
$prevIntent    = $_SESSION['chat_context']['last_intent'] ?? null;

// Lịch sử tin nhắn cho GPT fallback (tối đa 6 tin)
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// ==================================================
// HÀM TIỆN ÍCH
// ==================================================
function hasKeyword(string $msg, array $keywords): bool {
    foreach ($keywords as $kw) {
        if (mb_strpos($msg, $kw) !== false) return true;
    }
    return false;
}

function findHotelInMessage(string $msg, mysqli $mysqli): ?array {
    $result = $mysqli->query("SELECT id, name FROM hotels WHERE is_active = 1");
    $best = null; $bestLen = 0;
    while ($row = $result->fetch_assoc()) {
        $n     = mb_strtolower($row['name'], 'UTF-8');
        $short = preg_replace('/^(khách sạn|resort|homestay|hotel|lodge)\s+/ui', '', $n);
        if (mb_strpos($msg, $n) !== false || mb_strpos($msg, $short) !== false) {
            if (mb_strlen($row['name']) > $bestLen) {
                $best = $row; $bestLen = mb_strlen($row['name']);
            }
        }
    }
    return $best;
}

function findLocationInMessage(string $msg, mysqli $mysqli): ?array {
    $result = $mysqli->query("SELECT id, name FROM locations");
    $best = null; $bestLen = 0;
    while ($row = $result->fetch_assoc()) {
        $n = mb_strtolower($row['name'], 'UTF-8');
        if (mb_strpos($msg, $n) !== false) {
            if (mb_strlen($row['name']) > $bestLen) {
                $best = $row; $bestLen = mb_strlen($row['name']);
            }
        }
    }
    return $best;
}

function findOrderCodeInMessage(string $msg): ?string {
    if (preg_match('/ORD\d{10,}/i', $msg, $matches)) {
        return strtoupper($matches[0]);
    }
    return null;
}

function extractBudget(string $msg): int|float|null {
    if (preg_match('/dưới\s*(\d+)\s*triệu/u', $msg, $m)) return (int)$m[1] * 1000000;
    if (preg_match('/dưới\s*(\d+)tr/u', $msg, $m))       return (int)$m[1] * 1000000;
    if (preg_match('/dưới\s*(\d+)\s*k/u', $msg, $m))     return (int)$m[1] * 1000;
    if (preg_match('/khoảng\s*(\d+)\s*triệu/u', $msg, $m)) return (int)$m[1] * 1000000 * 1.2;
    if (preg_match('/(\d+)\s*triệu/u', $msg, $m))        return (int)$m[1] * 1000000;
    return null;
}

function extractDays(string $msg): int {
    if (preg_match('/(\d+)\s*ngày/u', $msg, $m))  return max(1, min(30, (int)$m[1]));
    if (preg_match('/một tuần|1 tuần/u', $msg))    return 7;
    if (preg_match('/nửa tuần/u', $msg))           return 3;
    if (preg_match('/cuối tuần/u', $msg))          return 2;
    if (preg_match('/hai ngày|2 ngày/u', $msg))    return 2;
    if (preg_match('/ba ngày|3 ngày/u', $msg))     return 3;
    return 0;
}

function findAllLocationsInMessage(string $msg, mysqli $mysqli): array {
    $result = $mysqli->query("SELECT id, name FROM locations");
    $found = [];
    while ($row = $result->fetch_assoc()) {
        $n = mb_strtolower($row['name'], 'UTF-8');
        if (mb_strpos($msg, $n) !== false) {
            $found[] = $row;
        }
    }
    return $found;
}

function getLocationAttractions(): string {
    return <<<'ATTRACT'

## DỮ LIỆU ĐỊA ĐIỂM THAM QUAN & ĐẶC SẢN (dùng để lập lịch trình)

### Kon Tum (thành phố tỉnh lỵ)
Địa điểm: Nhà thờ gỗ Kon Tum (công trình trăm tuổi, kiến trúc độc đáo), Làng văn hóa Kon Kơ Tu (làng Ba Na bên sông Đăk Bla), Bảo tàng tỉnh Kon Tum, Cầu treo Kon Klor, Sông Đăk Bla (ngắm hoàng hôn), Chùa Bác Ái, Quảng trường 16/3.
Ẩm thực: Cà phê Kon Tum, cơm lam, gà nướng thui, phở khô Gia Lai–Kon Tum, xôi đen, bánh tét lá đỏ, rượu cần.
Đi lại: Cách Măng Đen ~50km (1 giờ xe). Thích hợp làm điểm dừng 1 ngày kết hợp văn hóa + ẩm thực.

### Măng Đen (huyện Kon Plông – cao nguyên)
Địa điểm: Hồ Toong Zơri (hồ xanh giữa rừng thông), Hồ Toong Đam, Thác Pa Sỹ, Thác Lô Ba (đẹp mùa mưa), Rừng thông Măng Đen (chụp ảnh), Vườn dâu tây, Đồi chè xanh, Làng Kon Bring (văn hóa Xê Đăng).
Ẩm thực: Cá tầm Măng Đen (đặc sản số 1), dâu tây tươi, nấm hương rừng, rau rừng xào tỏi, cơm lam, gà nướng lá lốt, rượu ủ truyền thống, cà phê Arabica.
Thời tiết: Mát mẻ 15–25°C quanh năm, sáng sớm có sương mù. Mưa nhiều tháng 7–10.
Phù hợp: Nghỉ dưỡng, chill, chụp ảnh, đi bộ rừng, cặp đôi, gia đình.

### Quảng Ngãi (tỉnh + TP Quảng Ngãi)
Địa điểm chính: Đảo Lý Sơn (tàu 25 phút từ Sa Kỳ – nên đi 1 ngày), Biển Mỹ Khê Quảng Ngãi, Biển Sa Huỳnh (bình yên, ít người), Khu chứng tích Sơn Mỹ (lịch sử Mỹ Lai), Thác Trắng (Sơn Hà), Cổng Tò Vò (Lý Sơn), Núi Lửa Thới Lới (Lý Sơn).
Ẩm thực: Tỏi Lý Sơn (đặc sản nổi tiếng), mực Lý Sơn, cá bống sông Trà, mì Quảng Ngãi, bánh tráng gạo cuốn, hải sản Sa Huỳnh, bún cá.
Phù hợp: Du lịch biển, lịch sử, khám phá đảo, ẩm thực hải sản.
ATTRACT;
}

// ── Hàm xây dựng profile người dùng từ lịch sử đặt phòng ────────────────────
function buildUserProfile(int $userId, $mysqli): array {
    $profile = [
        'name'            => 'Khách',
        'booking_history' => 'Chưa có lịch sử đặt phòng',
        'preferences'     => 'Chưa xác định',
        'dislikes'        => '',
        'budget_range'    => 'Chưa xác định',
        'trip_type'       => 'Chưa xác định',
        'recent_searches' => '',
    ];

    $uid = (int)$userId;

    $res = $mysqli->query("SELECT full_name FROM users WHERE id = $uid");
    if ($res && $row = $res->fetch_assoc()) {
        $profile['name'] = $row['full_name'] ?: 'Khách';
    }

    $res = $mysqli->query("
        SELECT h.name AS hotel_name, l.name AS loc, h.star_category,
               b.total_price, b.check_in, b.check_out,
               GREATEST(1, DATEDIFF(b.check_out, b.check_in)) AS nights
        FROM bookings b
        JOIN rooms r     ON b.room_id     = r.id
        JOIN hotels h    ON r.hotel_id    = h.id
        JOIN locations l ON h.location_id = l.id
        WHERE b.user_id = $uid AND b.status IN ('completed','confirmed')
        ORDER BY b.created_at DESC LIMIT 10
    ");

    if (!$res || $res->num_rows === 0) return $profile;

    $rows = []; $locCnt = []; $starSum = 0; $starCnt = 0; $priceList = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
        $locCnt[$row['loc']] = ($locCnt[$row['loc']] ?? 0) + 1;
        if ((int)$row['star_category'] > 0) {
            $starSum += (int)$row['star_category'];
            $starCnt++;
        }
        $n = max(1, (int)$row['nights']);
        if ($row['total_price'] > 0) $priceList[] = round($row['total_price'] / $n);
    }

    arsort($locCnt);
    $locParts = [];
    foreach ($locCnt as $loc => $cnt) $locParts[] = "$loc ($cnt lần)";
    $recentHotels = array_unique(array_column(array_slice($rows, 0, 3), 'hotel_name'));
    $profile['booking_history'] = implode(', ', $locParts)
        . '. Gần đây đã ở: ' . implode(', ', $recentHotels);

    if (!empty($priceList)) {
        $avg = (int)(array_sum($priceList) / count($priceList));
        $profile['budget_range'] = number_format($avg, 0, ',', '.') . ' VNĐ/đêm (trung bình thực tế)';
    }

    if ($starCnt > 0) {
        $avgStar = $starSum / $starCnt;
        if ($avgStar >= 4)     $profile['preferences'] = 'Thường chọn khách sạn cao cấp 4-5 sao';
        elseif ($avgStar >= 3) $profile['preferences'] = 'Thường chọn khách sạn 3-4 sao';
        else                   $profile['preferences'] = 'Ưu tiên khách sạn bình dân tiết kiệm';
    }

    return $profile;
}

// ── Hàm lấy danh sách khách sạn thực tế để AI không hallucinate ──────────────
function getHotelsContext($mysqli): string {
    $res = $mysqli->query("
        SELECT h.name, h.star_category, h.price, h.rating, h.review_text,
               l.name AS loc, h.description
        FROM hotels h
        JOIN locations l ON h.location_id = l.id
        WHERE h.is_active = 1
        ORDER BY h.rating DESC
    ");
    if (!$res || $res->num_rows === 0) return '';

    $lines = [];
    while ($row = $res->fetch_assoc()) {
        $price = number_format((int)$row['price'], 0, ',', '.');
        $line  = "- {$row['name']} | {$row['loc']} | {$row['star_category']}★ | từ {$price} VNĐ/đêm | điểm {$row['rating']} ({$row['review_text']})";
        if ($row['description']) $line .= " | {$row['description']}";
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

// Quick replies theo intent
function getQuickReplies(string $intent, ?array $foundHotel = null, ?array $foundLocation = null): array {
    $hotelName = $foundHotel ? $foundHotel['name'] : 'khách sạn này';
    $locName   = $foundLocation ? $foundLocation['name'] : null;

    $map = [
        'hotel_info'     => ["Giá phòng $hotelName?", "Đánh giá $hotelName?", "Đặt phòng ngay", "Ưu đãi hiện có"],
        'hotel_price'    => ["Đặt phòng ngay", "Còn phòng không?", "Xem đánh giá", "So sánh khách sạn khác"],
        'hotel_review'   => ["Giá phòng bao nhiêu?", "Đặt phòng ngay", "Xem ưu đãi", "Khách sạn tương tự"],
        'hotel_list'     => ["Ưu đãi tốt nhất" . ($locName ? " ở $locName" : "") . "?", "Phòng giá rẻ nhất?", "Tư vấn chọn phòng", "Bài viết du lịch"],
        'deal'           => ["Đặt phòng ngay", "Xem thêm ưu đãi", "Điều kiện hủy phòng?", "Tư vấn chọn phòng"],
        'my_booking'     => ["Hủy đơn đặt phòng", "Xem ưu đãi mới", "Đặt phòng mới", "Liên hệ hỗ trợ"],
        'cancel_booking' => ["Xem đơn đặt phòng", "Đặt phòng mới", "Ưu đãi hiện có", "Liên hệ hỗ trợ"],
        'suggest_room'   => ["Xem ưu đãi", "Đặt phòng ngay", "Tư vấn chọn phòng", "Bài viết du lịch"],
        'book_room'      => ["Xem giá phòng", "Điều kiện hủy phòng?", "Đánh giá khách sạn", "Ưu đãi hiện có"],
        'blog'           => ["Khách sạn nổi bật", "Ưu đãi hôm nay", "Tư vấn chọn phòng", "Đặt phòng ngay"],
        'room_price'     => ["Đặt phòng ngay", "Tư vấn chọn phòng", "Xem ưu đãi", "Bài viết du lịch"],
        'trip_plan'      => ["Xem khách sạn phù hợp", "Ưu đãi tốt nhất", "Đặt phòng ngay", "Lịch trình chi tiết hơn"],
        'place_info'     => ["Khách sạn ở đây", "Lập kế hoạch chuyến đi", "Ưu đãi hiện có", "Đặt phòng"],
        'fallback'       => ["Khách sạn nổi bật", "Ưu đãi hôm nay", "Tư vấn chọn phòng", "Lập kế hoạch du lịch"],
    ];
    return $map[$intent] ?? $map['fallback'];
}

// ==================================================
// TÌM ENTITY - kết hợp tin nhắn hiện tại + context session
// ==================================================
$foundHotel    = findHotelInMessage($messageLower, $mysqli);
$foundLocation = findLocationInMessage($messageLower, $mysqli);
$foundOrderCode = findOrderCodeInMessage($message);
$budget        = extractBudget($messageLower);

// Nếu không tìm được hotel/location trong tin hiện tại thì dùng từ context
if (!$foundHotel && $ctxHotelId) {
    $foundHotel = ['id' => $ctxHotelId, 'name' => $ctxHotelName];
}
if (!$foundLocation && $ctxLocationId) {
    $foundLocation = ['id' => $ctxLocationId, 'name' => $ctxLocationName];
}

// ==================================================
// PHÁT HIỆN Ý ĐỊNH (INTENT DETECTION - nâng cấp)
// ==================================================
$intent = 'fallback';
$score  = [];

// --- my_booking ---
$score['my_booking'] = 0;
if (hasKeyword($messageLower, ['đơn đặt', 'booking của tôi', 'đặt phòng của tôi', 'lịch sử đặt', 'đơn của tôi', 'xem đơn', 'đơn hàng']))
    $score['my_booking'] += 10;
if (hasKeyword($messageLower, ['đơn', 'booking']) && hasKeyword($messageLower, ['của tôi', 'tôi đã', 'tôi có']))
    $score['my_booking'] += 5;

// --- cancel_booking ---
$score['cancel_booking'] = 0;
if (hasKeyword($messageLower, ['hủy', 'cancel', 'hủy phòng', 'hủy đơn', 'muốn hủy', 'hủy booking']))
    $score['cancel_booking'] += 10;
if ($foundOrderCode) $score['cancel_booking'] += 6;
if ($prevIntent === 'my_booking') $score['cancel_booking'] += 4;

// --- book_room ---
$score['book_room'] = 0;
if (hasKeyword($messageLower, ['đặt phòng', 'book phòng', 'muốn đặt', 'đặt ngay', 'tôi muốn đặt', 'làm sao để đặt', 'cách đặt']))
    $score['book_room'] += 10;
if ($foundHotel && hasKeyword($messageLower, ['đặt', 'book'])) $score['book_room'] += 3;

// --- suggest_room ---
$score['suggest_room'] = 0;
if (hasKeyword($messageLower, ['tư vấn', 'gợi ý phòng', 'nên chọn', 'phòng nào phù hợp', 'khuyến tới', 'recommend', 'phòng nào tốt', 'phòng nào nên']))
    $score['suggest_room'] += 10;
if ($budget) $score['suggest_room'] += 5;
if (hasKeyword($messageLower, ['ngân sách', 'tiết kiệm', 'cao cấp', 'gia đình', 'cặp đôi', 'lãng mạn']))
    $score['suggest_room'] += 4;

// --- hotel_review ---
$score['hotel_review'] = 0;
if (hasKeyword($messageLower, ['đánh giá', 'review', 'nhận xét', 'cảm nhận', 'feedback', 'ý kiến']))
    $score['hotel_review'] += 8;
if ($foundHotel) $score['hotel_review'] += 4;

// --- hotel_price ---
$score['hotel_price'] = 0;
if (hasKeyword($messageLower, ['giá phòng', 'giá bao nhiêu', 'bao nhiêu tiền', 'chi phí', 'phí']))
    $score['hotel_price'] += 8;
if (hasKeyword($messageLower, ['giá']) && !hasKeyword($messageLower, ['ưu đãi', 'giảm giá']))
    $score['hotel_price'] += 4;
if ($foundHotel) $score['hotel_price'] += 4;
// Context: sau hotel_info thường hỏi giá
if ($prevIntent === 'hotel_info') $score['hotel_price'] += 3;

// --- hotel_info ---
$score['hotel_info'] = 0;
if ($foundHotel) $score['hotel_info'] += 7;
if (hasKeyword($messageLower, ['thông tin', 'giới thiệu', 'địa chỉ', 'mô tả', 'như thế nào', 'ở đâu', 'có gì']))
    $score['hotel_info'] += 4;

// --- deal ---
$score['deal'] = 0;
if (hasKeyword($messageLower, ['ưu đãi', 'khuyến mãi', 'giảm giá', 'deal', 'flash sale', 'rẻ nhất', 'tiết kiệm nhất']))
    $score['deal'] += 10;
if (hasKeyword($messageLower, ['cuối tuần']) && hasKeyword($messageLower, ['khách sạn', 'phòng', 'nào', 'có', 'ưu đãi']))
    $score['deal'] += 8;

// --- hotel_list ---
$score['hotel_list'] = 0;
if (hasKeyword($messageLower, ['khách sạn', 'resort', 'homestay', 'chỗ ở', 'nơi ở']))
    $score['hotel_list'] += 4;
if (hasKeyword($messageLower, ['bao nhiêu', 'danh sách', 'có những', 'liệt kê', 'những nào', 'có gì']))
    $score['hotel_list'] += 5;
if ($foundLocation && !$foundHotel) $score['hotel_list'] += 4;

// --- room_price ---
$score['room_price'] = 0;
if (!$foundHotel && hasKeyword($messageLower, ['giá phòng', 'phòng giá', 'giá rẻ', 'phòng rẻ']))
    $score['room_price'] += 6;
if ($foundLocation) $score['room_price'] += 2;

// --- trip_plan ---
$score['trip_plan'] = 0;
if (hasKeyword($messageLower, ['lập kế hoạch', 'kế hoạch', 'lịch trình', 'itinerary', 'hành trình', 'chuyến đi', 'plan du lịch']))
    $score['trip_plan'] += 12;
if (extractDays($messageLower) > 0) $score['trip_plan'] += 6;
if (hasKeyword($messageLower, ['ngày đêm', 'ngày mấy', 'mấy ngày']))
    $score['trip_plan'] += 4;
if ($foundLocation || count(findAllLocationsInMessage($messageLower, $mysqli)) > 0)
    $score['trip_plan'] += 3;

// --- place_info ---
$score['place_info'] = 0;
if (hasKeyword($messageLower, ['có gì', 'nên đi đâu', 'địa điểm tham quan', 'thăm quan', 'đặc sản', 'ăn gì', 'chơi gì', 'thời tiết', 'mùa nào', 'nên đi mùa', 'hay đến', 'đáng đi']))
    $score['place_info'] += 10;
if ($foundLocation && hasKeyword($messageLower, ['có gì', 'gì hay', 'đi đâu', 'ăn gì', 'thăm']))
    $score['place_info'] += 5;
if (hasKeyword($messageLower, ['du lịch', 'đi chơi', 'khám phá']) && $foundLocation)
    $score['place_info'] += 3;

// --- blog ---
$score['blog'] = 0;
if (hasKeyword($messageLower, ['bài viết', 'blog', 'tin tức', 'kinh nghiệm', 'đọc thêm', 'hướng dẫn', 'chia sẻ', 'cẩm nang']))
    $score['blog'] += 10;

// Chọn intent có điểm cao nhất
arsort($score);
$topIntent = array_key_first($score);
if ($score[$topIntent] > 0) {
    $intent = $topIntent;
}

// ==================================================
// CẬP NHẬT CONTEXT SESSION
// ==================================================
if ($foundHotel && isset($foundHotel['id'])) {
    $_SESSION['chat_context']['last_hotel_id']   = $foundHotel['id'];
    $_SESSION['chat_context']['last_hotel_name'] = $foundHotel['name'];
}
if ($foundLocation && isset($foundLocation['id'])) {
    $_SESSION['chat_context']['last_location_id']   = $foundLocation['id'];
    $_SESSION['chat_context']['last_location_name'] = $foundLocation['name'];
}
$_SESSION['chat_context']['last_intent'] = $intent;

// Lưu lịch sử cho GPT (tối đa 6 tin)
$_SESSION['chat_history'][] = ["role" => "user", "content" => $message];
if (count($_SESSION['chat_history']) > 10) {
    array_shift($_SESSION['chat_history']);
}

// ==================================================
// BUILD USER PROFILE + V2 SYSTEM PROMPT
// ==================================================
$upData    = $userId ? buildUserProfile((int)$userId, $mysqli) : [];
$hotelsCtx = getHotelsContext($mysqli);

// ── System Prompt v3 ─────────────────────────────────────────────────────────
$v2SystemPrompt = <<<'SYSTEM_V3'
Bạn là trợ lý đặt phòng khách sạn AI của StayGo — hoạt động như người bạn am hiểu du lịch, không phải nhân viên tổng đài, không phải brochure quảng cáo.

Website phục vụ các khu vực: Kon Tum, Măng Đen, Quảng Ngãi.

## GIỚI HẠN QUAN TRỌNG — tuân thủ tuyệt đối

- Chỉ gợi ý khách sạn từ DANH SÁCH THỰC TẾ được cung cấp bên dưới. Không tự bịa tên khách sạn, giá phòng, hoặc tiện ích cụ thể.
- Nếu không chắc thông tin → nói rõ: "Em cần kiểm tra lại thông tin này cho chính xác."
- Nếu bị hỏi ngoài phạm vi (hoàn tiền, khiếu nại, visa...) → chuyển hướng khéo: "Việc này em sẽ kết nối anh/chị với bộ phận phù hợp nhé."

## XỬ LÝ HỒ SƠ KHÁCH HÀNG

Khi nhận [USER_PROFILE]:
- Profile đầy đủ → cá nhân hóa ngay, không hỏi lại
- Thiếu 1–2 trường → dùng những gì có, hỏi bổ sung tự nhiên khi cần
- Profile rỗng hoàn toàn → chào tự nhiên, hỏi 1 câu mở
- Dữ liệu mâu thuẫn → ưu tiên thông tin mới nhất trong cuộc trò chuyện

Dùng profile để suy luận, KHÔNG đọc lại cho khách nghe. Nhắc lịch sử cũ chỉ khi thực sự liên quan.

## PHÂN TÍCH PATTERN TRƯỚC KHI TRẢ LỜI

Từ booking_history, tự suy ra pattern ẩn:
- Pattern địa điểm: thiên nhiên / đô thị / biển / núi
- Pattern loại hình: resort / boutique / hotel thành phố
- Khoảng trống chưa khám phá → có thể gợi ý khi phù hợp

Khi khách tiết lộ thông tin mới trong hội thoại → ghi nhớ và dùng ngay:
- "đi với bố mẹ già" → ưu tiên thang máy, ít bậc thang, tránh villa biệt lập
- "hay bị mất ngủ" → tránh phòng gần đường lớn, bar, khu vui chơi
- "công ty trả" → có thể nâng tầm gợi ý, nhấn mạnh tiện ích

## PHÂN KHÚC HÀNH VI (tự xác định)

- Biết mình muốn gì (profile rõ, ít thay đổi) → đi thẳng vào gợi ý, không hỏi nhiều
- Hay khám phá (lịch sử đa dạng) → gợi ý khác kiểu cũ, nhấn mạnh yếu tố mới
- Nhạy cảm về giá (hay hỏi giá, so sánh OTA) → justify giá trị sớm, đề cập ưu đãi
- Trung thành thương hiệu → ưu tiên gợi ý trong chuỗi đó trước

## NGUYÊN TẮC TRẢ LỜI

- Dùng "anh/chị" hoặc tên riêng. Tuyệt đối không dùng "quý khách"
- Gợi ý chủ động khi đã có profile — không chờ hỏi từng bước
- Mỗi lượt chỉ hỏi 1 câu nếu cần thêm thông tin
- Giải thích TẠI SAO gợi ý khách sạn đó phù hợp với người này cụ thể
- Khi đưa nhiều lựa chọn, chỉ rõ sự khác biệt thực sự (không phải "cả hai đều tốt")
- Thành thật về điểm trừ — không che giấu
- 1 gợi ý rất đúng > 3 gợi ý ổn — không ép đủ số lượng nếu không cần
- Không dùng từ sáo rỗng: "tuyệt vời", "hoàn hảo", "đẳng cấp", "trải nghiệm đỉnh cao"
- Không ép đặt phòng khi khách chưa sẵn sàng

## SUY LUẬN ĐA TẦNG — chạy nội bộ trước khi trả lời

Câu hỏi 1 — Khách đang ở trạng thái nào?
- Đang khám phá → gợi ý rộng, hỏi định hướng
- Đang so sánh → giúp thu hẹp, chỉ rõ khác biệt quan trọng
- Đã gần quyết định → xác nhận, tháo gỡ điểm ngại cuối
- Phân vân vì lý do ẩn → tìm ra lý do trước, không gợi ý thêm vội

Câu hỏi 2 — Có gì khách không nói ra?
- "Hơi xa trung tâm không?" → lo lắng di chuyển, cần reassurance
- "Đắt vậy à..." → chưa chắc từ chối, có thể cần justify giá trị
- "Để mình nghĩ thêm" → có điều gì đó chưa được giải quyết

Câu hỏi 3 — Gợi ý này có thực sự phù hợp không?
- Có ít nhất 2 điểm khớp profile không?
- Có điểm nào mâu thuẫn sở thích không? → nếu có, nói thẳng

Điều chỉnh giọng điệu theo cảm xúc:
- Hào hứng → khớp năng lượng, đi nhanh vào chi tiết
- Do dự → chậm lại, đặt câu hỏi mở, không push
- Mệt/vội → đơn giản hóa, đề xuất rõ 1 phương án tốt nhất
- Khó tính → bình tĩnh, facts-first, không giải thích dài

## CÁCH TRÌNH BÀY GỢI Ý

Viết tự nhiên như đang nói chuyện — KHÔNG dùng template cứng bullet points.

Ví dụ ĐÚNG: "Em nghĩ anh sẽ thích Fusion Maia — resort adults-only nên rất yên tĩnh, phòng nào cũng có bồn tắm riêng và nhìn thẳng ra biển. Đúng kiểu anh hay chọn. Chỉ lưu ý là hơi xa trung tâm khoảng 20 phút nếu anh muốn đi ăn tối ngoài."

Ví dụ SAI: "→ Tại sao phù hợp: resort yên tĩnh | → Điểm nổi bật: bồn tắm, view biển | → Lưu ý: xa trung tâm"

Kết thúc gợi ý bằng 1 câu mở — đa dạng hóa, không lặp cùng một mẫu.

## NHỊP ĐIỆU HỘI THOẠI

Độ dài phản hồi khớp với tin nhắn của khách:
- Khách nhắn 1 câu ngắn → trả lời ngắn
- Khách hỏi chi tiết → trả lời đầy đủ
- Khách đang băn khoăn → đặt câu hỏi, không giải thích thêm

Tránh pattern gây cảm giác máy móc:
- Không mở đầu mọi câu bằng "Dạ," hoặc "Em hiểu rồi,"
- Không kết thúc mọi gợi ý bằng "Anh/chị muốn đặt không?"
- Không dùng cùng cấu trúc câu mở đầu ở mọi tin nhắn

Xây dựng rapport:
- Nhắc lịch sử cụ thể, không liệt kê: "Anh hay chọn chỗ yên tĩnh — Đà Lạt lần trước đúng kiểu đó."
- Ghi nhận quyết định tốt: "Chọn tháng 4 đi Đà Nẵng hợp lý đấy — trước mùa mưa, ít đông hơn hè."
- Được phép không chắc chắn: "Cái này thật ra hơi khó chọn vì cả hai đều khá hợp với anh..."

## LUỒNG ĐẶT PHÒNG

Khi khách đồng ý đặt, thu thập tuần tự mỗi lượt 1 câu:
1. Ngày nhận phòng & số đêm
2. Số người lớn / trẻ em (nếu liên quan)
3. Yêu cầu đặc biệt (hỏi mở, không bắt buộc)
4. Xác nhận tóm tắt ngắn gọn

## EDGE CASES

Khách đổi ý → không hỏi "ủa sao đổi?", tôn trọng và bắt đầu từ lựa chọn mới.
Ngân sách mâu thuẫn sở thích → thành thật nhẹ nhàng, hỏi khách muốn linh hoạt theo hướng nào.
Khách hỏi khách sạn không trong hệ thống → "Em chưa có thông tin về chỗ này — để em gợi ý chỗ tương tự anh xem sao?"
Khách im lặng sau gợi ý → hỏi 1 lần: "Anh còn băn khoăn điểm nào không?" — không push lần 2.
Khách đặt cho người khác → hỏi về người đi thực tế, không dùng profile của người đặt.
Khách phàn nàn chuyến cũ → đồng cảm 1 câu → hỏi ngay: "Lần này anh muốn tránh điều gì nhất?"
Khách hỏi ngoài phạm vi (visa, vé máy bay) → "Phần này em không hỗ trợ trực tiếp được — về khách sạn anh cứ để em lo."

## KHÔNG ĐƯỢC LÀM

✗ Hỏi nhiều câu trong cùng một tin nhắn
✗ Tự bịa thông tin không có trong dữ liệu hệ thống
✗ Gợi ý không liên quan đến profile mà không giải thích lý do
✗ Nói "Tôi là AI nên không thể..." → chuyển hướng khéo léo
✗ Lặp lại nguyên xi những gì khách vừa nói
✗ Dùng "quý khách"
✗ Template cứng khi văn xuôi tự nhiên phù hợp hơn
✗ Giả định thông tin khi khách chưa cung cấp
SYSTEM_V3;

// Append danh sách khách sạn thực tế (anti-hallucination)
$v2SystemPrompt .= "\n\n## DANH SÁCH KHÁCH SẠN THỰC TẾ (chỉ gợi ý từ danh sách này)\n\n" . $hotelsCtx;

// Inject user profile nếu đã đăng nhập
if (!empty($upData)) {
    $v2SystemPrompt .= "\n\n[USER_PROFILE]
- Họ tên: {$upData['name']}
- Lịch sử đặt phòng: {$upData['booking_history']}
- Sở thích: {$upData['preferences']}
- Không thích: {$upData['dislikes']}
- Ngân sách thường xuyên: {$upData['budget_range']}
- Loại chuyến đi hay đặt: {$upData['trip_type']}
- Tìm kiếm gần đây: {$upData['recent_searches']}
[/USER_PROFILE]

Dùng dữ liệu profile để cá nhân hóa từng phản hồi. Nếu profile thiếu một phần → hỏi khéo léo trong cuộc trò chuyện, không hỏi dồn nhiều câu.";
} else {
    $v2SystemPrompt .= "\n\nNgười dùng chưa đăng nhập — không có dữ liệu profile. Chào tự nhiên, hỏi 1 câu mở để bắt đầu tìm hiểu nhu cầu.";
}

// ==================================================
// XỬ LÝ THEO INTENT
// ==================================================
ob_start();

switch ($intent) {

    // ------------------------------------------------
    case 'my_booking':
        if (!$userId) {
            echo "Bạn cần <b>đăng nhập</b> để xem thông tin đặt phòng của mình.<br><br>";
            echo '<a href="/auth/login.php" style="color:#2563eb;font-weight:600">Đăng nhập ngay</a>';
            break;
        }
        $uid = (int)$userId;
        $result = $mysqli->query(
            "SELECT b.order_code, h.name AS hotel_name, r.room_name,
                    b.check_in, b.check_out, b.total_price, b.status
            FROM bookings b
            LEFT JOIN rooms r  ON b.room_id  = r.id
            LEFT JOIN hotels h ON r.hotel_id = h.id
            WHERE b.user_id = $uid
            ORDER BY b.created_at DESC LIMIT 5"
        );
        if ($result && $result->num_rows > 0) {
            echo "<b>Đơn đặt phòng gần nhất của bạn:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                $statusMap = [
                    'confirmed' => 'Đã xác nhận',
                    'pending'   => 'Chờ xác nhận',
                    'cancelled' => 'Đã hủy',
                    'completed' => 'Hoàn thành',
                ];
                echo "<b>#{$row['order_code']}</b> - " . ($statusMap[$row['status']] ?? $row['status']) . "<br>";
                echo ($row['hotel_name'] ?? 'N/A') . " - " . ($row['room_name'] ?? 'N/A') . "<br>";
                echo "{$row['check_in']} - {$row['check_out']}<br>";
                echo number_format($row['total_price']) . " VNĐ<br>";
                if ($row['status'] === 'pending') {
                    echo "<small style='color:#e67e22'>Muốn hủy? Nhắn: <b>hủy #{$row['order_code']}</b></small>";
                }
                echo "<br><br>";
            }
        } else {
            echo "Bạn chưa có đơn đặt phòng nào.<br><br>";
            echo '<a href="/pages/hotels.php" style="color:#2563eb;font-weight:600">Xem khách sạn ngay</a>';
        }
        break;

    // ------------------------------------------------
    case 'cancel_booking':
        if (!$userId) {
            echo "Bạn cần <b>đăng nhập</b> để hủy đặt phòng.";
            break;
        }
        $uid       = (int)$userId;
        $orderCode = $foundOrderCode;

        if (!$orderCode) {
            // Không có order code thì liệt kê đơn pending để user chọn
            $result = $mysqli->query(
                "SELECT b.order_code, h.name AS hotel_name, b.check_in, b.check_out, b.total_price
                FROM bookings b
                LEFT JOIN rooms r  ON b.room_id = r.id
                LEFT JOIN hotels h ON r.hotel_id = h.id
                WHERE b.user_id = $uid AND b.status = 'pending'
                ORDER BY b.created_at DESC LIMIT 3"
            );
            if ($result && $result->num_rows > 0) {
                echo "<b>Đơn có thể hủy của bạn:</b><br><br>";
                while ($row = $result->fetch_assoc()) {
                    echo "<b>#{$row['order_code']}</b> - {$row['hotel_name']}<br>";
                    echo "  ?? {$row['check_in']} ? {$row['check_out']}<br>";
                    echo "  " . number_format($row['total_price']) . " VNĐ<br>";
                    echo "  <small style='color:#e67e22'>Nhắn: <b>hủy #{$row['order_code']}</b> để hủy</small><br><br>";
                }
            } else {
                echo "Bạn không có đơn nào ở trạng thái <b>chờ xác nhận</b> để hủy.<br><br>";
                echo "Đơn đã xác nhận cần liên hệ hỗ trợ để hủy.";
            }
            break;
        }

        // Có order code thì thực hiện hủy
        $oc      = $mysqli->real_escape_string($orderCode);
        $booking = $mysqli->query(
            "SELECT id, status FROM bookings WHERE order_code = '$oc' AND user_id = $uid"
        )->fetch_assoc();

        if (!$booking) {
            echo "Không tìm thấy đơn <b>#$orderCode</b> trong tài khoản của bạn.";
            break;
        }
        if ($booking['status'] === 'cancelled') {
            echo "Đơn <b>#$orderCode</b> đã được hủy trước đó rồi.";
            break;
        }
        if ($booking['status'] === 'completed') {
            echo "Đơn <b>#$orderCode</b> đã hoàn thành, không thể hủy.";
            break;
        }
        if ($booking['status'] === 'confirmed') {
            echo "Đơn <b>#$orderCode</b> đã được xác nhận.<br>";
            echo "Vui lòng truy cập trang quản lý để hủy hoặc liên hệ hỗ trợ:<br><br>";
            echo '<a href="/pages/my_bookings.php" style="color:#2563eb;font-weight:600">Trang quản lý đặt phòng</a>';
            break;
        }
        // pending -> hủy
        $bookingId = (int)$booking['id'];
        $mysqli->query("UPDATE bookings SET status = 'cancelled' WHERE id = $bookingId");
        echo "<b>Đã hủy thành công đơn #$orderCode!</b><br><br>";
        echo "Nếu đã thanh toán, vui lòng liên hệ hỗ trợ để được hoàn tiền.<br><br>";
        echo '<a href="/pages/my_bookings.php" style="color:#2563eb">Xem lịch sử đặt phòng</a>';
        break;

    // ------------------------------------------------
    case 'book_room':
        if ($foundHotel) {
            $hotelId = (int)$foundHotel['id'];
            $result  = $mysqli->query(
                "SELECT room_name, price, quantity FROM rooms
                WHERE hotel_id = $hotelId AND quantity > 0 ORDER BY price ASC LIMIT 4"
            );
            echo "<b>Đặt phòng tại {$foundHotel['name']}:</b><br><br>";
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "{$row['room_name']} - <b>" . number_format($row['price']) . " VNĐ/đêm</b>";
                    echo " (còn {$row['quantity']} phòng)<br>";
                }
                echo "<br>";
            } else {
                echo "Hiện không còn phòng trống.<br><br>";
            }
            echo "<a href=\"/pages/hotel_detail.php?id={$hotelId}\" style=\"background:#2563eb;color:white;padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block\">Đặt phòng ngay</a>";
        } else {
            $result = $mysqli->query(
                "SELECT l.name, COUNT(h.id) AS cnt FROM locations l
                LEFT JOIN hotels h ON h.location_id = l.id AND h.is_active = 1
                GROUP BY l.id HAVING cnt > 0"
            );
            echo "<b>Bạn muốn đặt phòng ở đâu?</b><br><br>";
            echo "Chúng tôi có khách sạn tại:<br>";
            while ($row = $result->fetch_assoc()) {
                echo "<b>{$row['name']}</b> ({$row['cnt']} khách sạn)<br>";
            }
            echo "<br>Hãy cho tôi biết khu vực hoặc tên khách sạn bạn muốn!";
        }
        break;

    // ------------------------------------------------
    case 'suggest_room':
        $locFilter      = $foundLocation ? " AND h.location_id = " . (int)$foundLocation['id'] : "";
        $budgetSQL      = $budget ? " AND r.price <= " . (int)$budget : "";
        $roomTypeFilter = "";
        if (hasKeyword($messageLower, ['gia đình', 'family']))
            $roomTypeFilter = " AND LOWER(r.room_name) LIKE '%gia đình%'";
        elseif (hasKeyword($messageLower, ['cặp đôi', 'đôi', 'couple', 'lãng mạn', 'honeymoon']))
            $roomTypeFilter = " AND (LOWER(r.room_name) LIKE '%đôi%' OR LOWER(r.room_name) LIKE '%deluxe%')";
        elseif (hasKeyword($messageLower, ['cao cấp', 'sang trọng', 'vip', 'suite']))
            $roomTypeFilter = " AND (LOWER(r.room_name) LIKE '%cao cấp%' OR LOWER(r.room_name) LIKE '%suite%' OR LOWER(r.room_name) LIKE '%deluxe%')";

        $result = $mysqli->query(
            "SELECT r.room_name, r.price, r.quantity, h.name AS hotel_name,
                    h.rating, h.review_text, h.id AS hotel_id, l.name AS loc
            FROM rooms r
            JOIN hotels h    ON r.hotel_id    = h.id
            JOIN locations l ON h.location_id = l.id
            WHERE h.is_active = 1 AND r.quantity > 0 $locFilter $budgetSQL $roomTypeFilter
            ORDER BY h.rating DESC, r.price ASC LIMIT 5"
        );

        if ($result && $result->num_rows > 0) {
            $suggestRows = [];
            while ($row = $result->fetch_assoc()) $suggestRows[] = $row;

            if (!empty($apiKey)) {
                // GPT trình bày tự nhiên, cá nhân hóa theo v2 system prompt
                $roomsCtx = "";
                foreach ($suggestRows as $row) {
                    $price     = number_format((int)$row['price'], 0, ',', '.');
                    $roomsCtx .= "- {$row['hotel_name']} ({$row['loc']}, đánh giá {$row['rating']} - {$row['review_text']}): "
                        . "{$row['room_name']} giá {$price} VNĐ/đêm (còn {$row['quantity']} phòng)"
                        . " | link đặt phòng: /pages/hotel_detail.php?id={$row['hotel_id']}\n";
                }

                $histPrev = array_slice($_SESSION['chat_history'], 0, -1);
                $suggestMessages = [["role" => "system", "content" => $v2SystemPrompt]];
                foreach ($histPrev as $h) $suggestMessages[] = $h;
                $suggestMessages[] = [
                    "role"    => "user",
                    "content" => $message . "\n\n[DỮ LIỆU PHÒNG THỰC TẾ TỪ HỆ THỐNG - chỉ gợi ý từ danh sách này]\n" . $roomsCtx,
                ];

                $ch = curl_init("https://api.openai.com/v1/chat/completions");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_HTTPHEADER     => [
                        "Content-Type: application/json",
                        "Authorization: Bearer $apiKey",
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        "model"      => "gpt-4o-mini",
                        "messages"   => $suggestMessages,
                        "max_tokens" => 700,
                    ]),
                ]);
                $resp    = curl_exec($ch);
                $parsed  = $resp ? json_decode($resp, true) : null;
                $gpReply = $parsed['choices'][0]['message']['content'] ?? '';

                if ($gpReply !== '') {
                    echo $gpReply;
                    break;
                }
            }

            // HTML fallback (không có API key hoặc GPT lỗi)
            $locText    = $foundLocation ? " tại {$foundLocation['name']}" : "";
            $budgetText = $budget ? " dưới " . number_format((int)$budget) . " VNĐ" : "";
            echo "<b>Gợi ý phòng phù hợp{$locText}{$budgetText}:</b><br><br>";
            foreach ($suggestRows as $row) {
                echo "<b>{$row['hotel_name']}</b> — {$row['rating']} ({$row['review_text']})<br>";
                echo "{$row['room_name']}: <b>" . number_format((int)$row['price']) . " VNĐ/đêm</b> (còn {$row['quantity']} phòng)<br>";
                echo "📍 {$row['loc']}<br>";
                echo "<a href=\"/pages/hotel_detail.php?id={$row['hotel_id']}\" style=\"color:#2563eb;font-size:12px\">Xem &amp; đặt phòng</a><br><br>";
            }
        } else {
            echo "Không tìm thấy phòng phù hợp với tiêu chí của bạn.<br>";
            echo "Thử mở rộng ngân sách hoặc chọn khu vực khác nhé!";
        }
        break;

    // ------------------------------------------------
    case 'hotel_review':
        if (!$foundHotel) goto fallback_label;
        $hotelId = (int)$foundHotel['id'];
        $result  = $mysqli->query(
            "SELECT r.comment, r.rating, u.full_name, r.created_at
            FROM reviews r JOIN users u ON r.user_id = u.id
            WHERE r.hotel_id = $hotelId AND r.is_active = 1
            ORDER BY r.created_at DESC LIMIT 5"
        );
        if ($result && $result->num_rows > 0) {
            echo "<b>Đánh giá {$foundHotel['name']}:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                $stars = str_repeat('?', min((int)$row['rating'], 5));
                echo "<b>{$row['full_name']}</b> - $stars<br>";
                echo "?? {$row['comment']}<br>";
                echo "<small>?? {$row['created_at']}</small><br><br>";
            }
        } else {
            echo "Chưa có đánh giá nào cho <b>{$foundHotel['name']}</b>.";
        }
        break;

    // ------------------------------------------------
    case 'hotel_price':
        if (!$foundHotel) goto fallback_label;
        $hotelId    = (int)$foundHotel['id'];
        $roomFilter = "";
        if (hasKeyword($messageLower, ['cao cấp']))        $roomFilter = " AND LOWER(room_name) LIKE '%cao cấp%'";
        elseif (hasKeyword($messageLower, ['tiêu chuẩn'])) $roomFilter = " AND LOWER(room_name) LIKE '%tiêu chuẩn%'";
        elseif (hasKeyword($messageLower, ['suite', 'deluxe']))
            $roomFilter = " AND (LOWER(room_name) LIKE '%suite%' OR LOWER(room_name) LIKE '%deluxe%')";

        $result = $mysqli->query(
            "SELECT r.room_name, r.price, r.quantity, h.name, h.address
            FROM rooms r JOIN hotels h ON r.hotel_id = h.id
            WHERE r.hotel_id = $hotelId $roomFilter ORDER BY r.price ASC"
        );
        if ($result && $result->num_rows > 0) {
            $first = true;
            echo "<b>Giá phòng:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                if ($first) {
                    echo "?? <b>{$row['name']}</b><br>?? {$row['address']}<br><br>";
                    $first = false;
                }
                $avail = $row['quantity'] > 0
                    ? "<span style='color:#16a34a'>còn {$row['quantity']} phòng</span>"
                    : "<span style='color:#dc2626'>hết phòng</span>";
                echo "{$row['room_name']}: <b>" . number_format($row['price']) . " VNĐ/đêm</b> ($avail)<br>";
            }
        } else {
            echo "Không tìm thấy thông tin phòng cho <b>{$foundHotel['name']}</b>.";
        }
        break;

    // ------------------------------------------------
    case 'hotel_info':
        if (!$foundHotel) goto fallback_label;
        $hotelId = (int)$foundHotel['id'];
        $result  = $mysqli->query(
            "SELECT h.name, h.address, h.description, h.rating, h.review_text, h.review_count,
                    l.name AS location_name,
                    COALESCE(MIN(r.price), h.price) AS min_price,
                    COALESCE(MAX(r.price), h.price) AS max_price
             FROM hotels h
             LEFT JOIN locations l ON h.location_id = l.id
             LEFT JOIN rooms r ON r.hotel_id = h.id AND r.quantity > 0
             WHERE h.id = $hotelId
             GROUP BY h.id, h.name, h.address, h.description, h.rating,
                      h.review_text, h.review_count, l.name, h.price"
        );
        if ($result && $row = $result->fetch_assoc()) {
            $priceRange = ((int)$row['max_price'] > (int)$row['min_price'])
                ? number_format($row['min_price']) . ' – ' . number_format($row['max_price'])
                : number_format($row['min_price']);
            echo "🏨 <b>{$row['name']}</b><br>";
            echo "{$row['address']}" . ($row['location_name'] ? " - {$row['location_name']}" : "") . "<br>";
            echo "{$row['rating']} - {$row['review_text']} ({$row['review_count']} đánh giá)<br>";
            echo "Từ <b>" . $priceRange . " VNĐ/đêm</b><br><br>";
            if ($row['description']) echo "📋 {$row['description']}<br><br>";
            echo "<a href=\"/pages/hotel_detail.php?id={$hotelId}\" style=\"color:#2563eb;font-weight:600\">Xem chi tiết &amp; đặt phòng</a>";
        }
        break;

    // ------------------------------------------------
    case 'deal':
        $locFilter     = $foundLocation ? " AND h.location_id = " . (int)$foundLocation['id'] : "";
        $weekendFilter = hasKeyword($messageLower, ['cuối tuần']) ? " AND h.is_weekend_deal = 1" : "";

        $result = $mysqli->query(
            "SELECT h.name, h.address, h.price, h.old_price, h.rating, h.review_text,
                    h.id, l.name AS loc,
                    ROUND((1 - h.price / h.old_price) * 100) AS discount_pct
            FROM hotels h JOIN locations l ON h.location_id = l.id
            WHERE h.is_active = 1 AND h.old_price > h.price $weekendFilter $locFilter
            ORDER BY discount_pct DESC LIMIT 5"
        );
        if ((!$result || $result->num_rows == 0) && $weekendFilter) {
            $result = $mysqli->query(
                "SELECT h.name, h.address, h.price, h.old_price, h.rating, h.review_text,
                        h.id, l.name AS loc,
                        ROUND((1 - h.price / h.old_price) * 100) AS discount_pct
                FROM hotels h JOIN locations l ON h.location_id = l.id
                WHERE h.is_active = 1 AND h.old_price > h.price $locFilter
                ORDER BY discount_pct DESC LIMIT 5"
            );
        }
        if ($result && $result->num_rows > 0) {
            $locText     = $foundLocation ? " tại {$foundLocation['name']}" : "";
            $weekendText = hasKeyword($messageLower, ['cuối tuần']) ? " cuối tuần" : "";
            echo "<b>Ưu đãi$weekendText$locText:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                echo "<b>{$row['name']}</b> - {$row['loc']}<br>";
                echo "<b>" . number_format($row['price']) . " VNĐ/đêm</b> ";
                echo "<s style='color:#999'>" . number_format($row['old_price']) . "</s> ";
                echo "<span style='color:#e53e3e;font-weight:600'>Giảm {$row['discount_pct']}%</span><br>";
                echo "{$row['rating']} - {$row['review_text']}<br>";
                echo '<a href="/pages/hotel_detail.php?id=' . $row['id'] . '" style="color:#2563eb;font-size:12px">Xem & đặt ngay</a><br><br>';
            }
        } else {
            echo "Hiện chưa có ưu đãi nào" . ($foundLocation ? " tại {$foundLocation['name']}" : "") . ".";
        }
        break;

    // ------------------------------------------------
    case 'hotel_list':
        if ($foundLocation) {
            $locId   = (int)$foundLocation['id'];
            $locName = $foundLocation['name'];
            $total   = $mysqli->query(
                "SELECT COUNT(*) AS c FROM hotels WHERE is_active = 1 AND location_id = $locId"
            )->fetch_assoc()['c'];
            $result  = $mysqli->query(
                "SELECT h.id, h.name, h.address, h.rating, h.review_text, h.review_count,
                        COALESCE(MIN(r.price), h.price) AS min_price,
                        COALESCE(MAX(r.price), h.price) AS max_price
                 FROM hotels h
                 LEFT JOIN rooms r ON r.hotel_id = h.id AND r.quantity > 0
                 WHERE h.is_active = 1 AND h.location_id = $locId
                 GROUP BY h.id, h.name, h.address, h.rating, h.review_text, h.review_count, h.price
                 ORDER BY h.rating DESC LIMIT 8"
            );
            echo "<b>$locName có $total khách sạn:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                $priceRange = ((int)$row['max_price'] > (int)$row['min_price'])
                    ? number_format($row['min_price']) . ' – ' . number_format($row['max_price'])
                    : number_format($row['min_price']);
                echo "<b>{$row['name']}</b><br>";
                echo "{$row['rating']} - {$row['review_text']} ({$row['review_count']} đánh giá)<br>";
                echo "Từ <b>" . $priceRange . " VNĐ/đêm</b><br>";
                echo '<a href="/pages/hotel_detail.php?id=' . $row['id'] . '" style="color:#2563eb;font-size:12px">Xem chi tiết</a><br><br>';
            }
        } else {
            $result = $mysqli->query(
                "SELECT h.id, h.name, h.address, h.rating, h.review_text, l.name AS loc,
                        COALESCE(MIN(r.price), h.price) AS min_price,
                        COALESCE(MAX(r.price), h.price) AS max_price
                 FROM hotels h
                 JOIN locations l ON h.location_id = l.id
                 LEFT JOIN rooms r ON r.hotel_id = h.id AND r.quantity > 0
                 WHERE h.is_active = 1
                 GROUP BY h.id, h.name, h.address, h.rating, h.review_text, l.name, h.price
                 ORDER BY h.rating DESC LIMIT 6"
            );
            echo "<b>Khách sạn nổi bật:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                $priceRange = ((int)$row['max_price'] > (int)$row['min_price'])
                    ? number_format($row['min_price']) . ' – ' . number_format($row['max_price'])
                    : number_format($row['min_price']);
                echo "<b>{$row['name']}</b> - {$row['loc']}<br>";
                echo "{$row['rating']} - {$row['review_text']}<br>";
                echo "Từ " . $priceRange . " VNĐ/đêm<br>";
                echo '<a href="/pages/hotel_detail.php?id=' . $row['id'] . '" style="color:#2563eb;font-size:12px">Xem chi tiết</a><br><br>';
            }
        }
        break;

    // ------------------------------------------------
    case 'room_price':
        $locFilter = $foundLocation ? " AND h.location_id = " . (int)$foundLocation['id'] : "";
        $result    = $mysqli->query(
            "SELECT r.room_name, r.price, r.quantity, h.name, h.id, l.name AS loc
            FROM rooms r
            JOIN hotels h    ON r.hotel_id    = h.id
            JOIN locations l ON h.location_id = l.id
            WHERE h.is_active = 1 AND r.quantity > 0 $locFilter
            ORDER BY r.price ASC LIMIT 6"
        );
        if ($result && $result->num_rows > 0) {
            $locText = $foundLocation ? " tại {$foundLocation['name']}" : "";
            echo "<b>Giá phòng$locText:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                echo "?? {$row['name']} ({$row['loc']})<br>";
                echo "{$row['room_name']}: <b>" . number_format($row['price']) . " VNĐ/đêm</b><br>";
                echo '<a href="/pages/hotel_detail.php?id=' . $row['id'] . '" style="color:#2563eb;font-size:12px">Đặt phòng</a><br><br>';
            }
        } else {
            echo "Hiện chưa có dữ liệu phòng.";
        }
        break;

    // ------------------------------------------------
    case 'blog':
        if ($foundLocation) {
            $locName  = $mysqli->real_escape_string($foundLocation['name']);
            $whereSQL = "WHERE (category LIKE '%$locName%' OR tags LIKE '%$locName%') AND is_active = 1";
        } else {
            $whereSQL = "WHERE is_active = 1";
        }
        $result = $mysqli->query(
            "SELECT title, summary, category, author, read_time
             FROM blog_posts $whereSQL ORDER BY created_at DESC LIMIT 4"
        );
        if ($result && $result->num_rows > 0) {
            $locText = $foundLocation ? " về <b>{$foundLocation['name']}</b>" : "";
            echo "<b>Bài viết du lịch$locText:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                echo "<b>{$row['title']}</b><br>";
                echo "<small>{$row['author']} | {$row['read_time']}</small><br>";
                echo "{$row['summary']}<br><br>";
            }
        } else {
            echo "Chưa có bài viết nào phù hợp.";
        }
        break;

    // ------------------------------------------------
    case 'trip_plan':
        $days     = extractDays($messageLower);
        $daysText = $days > 0 ? "$days ngày" : "chuyến đi";
        $allLocs  = findAllLocationsInMessage($messageLower, $mysqli);
        if (empty($allLocs) && $foundLocation) $allLocs = [$foundLocation];
        $locNames = !empty($allLocs) ? implode(' & ', array_column($allLocs, 'name')) : 'Kon Tum / Măng Đen / Quảng Ngãi';

        // Lấy khách sạn của TẤT CẢ địa điểm được nhắc
        $hotelsForPlan = [];
        if (!empty($allLocs)) {
            $locIds = implode(',', array_map(fn($l) => (int)$l['id'], $allLocs));
            $res = $mysqli->query("
                SELECT h.name, h.price, h.rating, h.review_text, h.id, h.description,
                       h.star_category, l.name AS loc
                FROM hotels h JOIN locations l ON h.location_id = l.id
                WHERE h.is_active = 1 AND h.location_id IN ($locIds)
                ORDER BY h.rating DESC LIMIT 8
            ");
            if ($res) while ($row = $res->fetch_assoc()) $hotelsForPlan[] = $row;
        }
        if (empty($hotelsForPlan)) {
            // Không xác định được địa điểm → lấy top hotels toàn quốc
            $res = $mysqli->query("
                SELECT h.name, h.price, h.rating, h.review_text, h.id,
                       h.star_category, l.name AS loc
                FROM hotels h JOIN locations l ON h.location_id = l.id
                WHERE h.is_active = 1 ORDER BY h.rating DESC LIMIT 6
            ");
            if ($res) while ($row = $res->fetch_assoc()) $hotelsForPlan[] = $row;
        }

        if (!empty($apiKey)) {
            $hotelsData = "";
            foreach ($hotelsForPlan as $h) {
                $price = number_format((int)$h['price'], 0, ',', '.');
                $hotelsData .= "- {$h['name']} ({$h['loc']}, {$h['star_category']}★, đánh giá {$h['rating']} — {$h['review_text']}): từ {$price} VNĐ/đêm | link: /pages/hotel_detail.php?id={$h['id']}\n";
            }

            $attractCtx  = getLocationAttractions();
            $planSysPrompt = $v2SystemPrompt . $attractCtx;

            $histPrev = array_slice($_SESSION['chat_history'], 0, -1);
            $planMsgs = [["role" => "system", "content" => $planSysPrompt]];
            foreach ($histPrev as $hh) $planMsgs[] = $hh;
            $planMsgs[] = [
                "role"    => "user",
                "content" => $message . "\n\n[KHÁCH SẠN THỰC TẾ TẠI KHU VỰC - BẮT BUỘC chỉ gợi ý từ danh sách này]\n" . $hotelsData
                    . "\nHãy lập lịch trình $daysText tại $locNames: gợi ý địa điểm tham quan theo từng ngày, ẩm thực địa phương nên thử, và khách sạn phù hợp từ danh sách trên. Trình bày tự nhiên như người địa phương tư vấn, không dùng template cứng.",
            ];

            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $apiKey"],
                CURLOPT_POSTFIELDS     => json_encode([
                    "model"      => "gpt-4o-mini",
                    "messages"   => $planMsgs,
                    "max_tokens" => 1200,
                ]),
            ]);
            $resp    = curl_exec($ch);
            $parsed  = $resp ? json_decode($resp, true) : null;
            $gpReply = $parsed['choices'][0]['message']['content'] ?? '';
            if ($gpReply !== '') { echo $gpReply; break; }
        }

        // HTML fallback
        echo "<b>Gợi ý $daysText tại $locNames:</b><br><br>";
        foreach ($hotelsForPlan as $h) {
            echo "<b>{$h['name']}</b> ({$h['loc']}) — " . number_format((int)$h['price']) . " VNĐ/đêm<br>";
            echo "{$h['rating']} — {$h['review_text']}<br>";
            echo '<a href="/pages/hotel_detail.php?id=' . $h['id'] . '" style="color:#2563eb;font-size:12px">Xem & đặt phòng</a><br><br>';
        }
        break;

    // ------------------------------------------------
    case 'place_info':
        $locName = $foundLocation ? $foundLocation['name'] : null;
        if (!empty($apiKey)) {
            $attractCtx = getLocationAttractions();
            $piSysPrompt = $v2SystemPrompt . $attractCtx;

            $histPrev = array_slice($_SESSION['chat_history'], 0, -1);
            $piMsgs   = [["role" => "system", "content" => $piSysPrompt]];
            foreach ($histPrev as $hh) $piMsgs[] = $hh;
            $piMsgs[] = ["role" => "user", "content" => $message];

            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $apiKey"],
                CURLOPT_POSTFIELDS     => json_encode([
                    "model"      => "gpt-4o-mini",
                    "messages"   => $piMsgs,
                    "max_tokens" => 700,
                ]),
            ]);
            $resp    = curl_exec($ch);
            $parsed  = $resp ? json_decode($resp, true) : null;
            $gpReply = $parsed['choices'][0]['message']['content'] ?? '';
            if ($gpReply !== '') { echo $gpReply; break; }
        }
        // Fallback đơn giản
        echo $locName
            ? "Tôi có thể tư vấn về địa điểm tham quan, ẩm thực và khách sạn tại <b>$locName</b>. Bạn muốn hỏi cụ thể điều gì?"
            : "Bạn muốn khám phá khu vực nào? Tôi có thể tư vấn về <b>Kon Tum, Măng Đen</b> và <b>Quảng Ngãi</b>.";
        break;

    // ------------------------------------------------
    fallback_label:
    default:
        if (empty($apiKey)) {
            echo "Xin lỗi, em chưa hiểu câu hỏi này. Anh/chị có thể hỏi về: giá phòng, khách sạn tại Kon Tum/Măng Đen/Quảng Ngãi, hoặc các ưu đãi hiện có nhé!";
            break;
        }
        // Dùng v2SystemPrompt đã có user profile + danh sách khách sạn thực tế
        $gptMessages = [["role" => "system", "content" => $v2SystemPrompt]];
        foreach ($_SESSION['chat_history'] as $h) {
            $gptMessages[] = $h;
        }

        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "Authorization: Bearer $apiKey",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "model"      => "gpt-4o-mini",
                "messages"   => $gptMessages,
                "max_tokens" => 600,
            ]),
        ]);
        $response = curl_exec($ch);
        if (!$response) { echo "Xin lỗi, hệ thống đang bận. Vui lòng thử lại!"; break; }
        $result = json_decode($response, true);
        echo $result['choices'][0]['message']['content'] ?? "Xin lỗi, em chưa thể trả lời câu hỏi này.";
        break;
}

$botReply = ob_get_clean();

// Lưu reply vào session history
$_SESSION['chat_history'][] = ["role" => "assistant", "content" => strip_tags($botReply)];
if (count($_SESSION['chat_history']) > 10) {
    array_shift($_SESSION['chat_history']);
}

// ==================================================
// QUICK REPLIES + TRẢ VỀ JSON
// ==================================================
$quickReplies = getQuickReplies($intent, $foundHotel, $foundLocation);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'reply'         => $botReply,
    'quick_replies' => $quickReplies,
    'intent'        => $intent,
], JSON_UNESCAPED_UNICODE);
