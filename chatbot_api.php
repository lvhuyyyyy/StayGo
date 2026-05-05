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
    function($t) { return $t > time() - 60; }
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
function hasKeyword($msg, array $keywords) {
    foreach ($keywords as $kw) {
        if (mb_strpos($msg, $kw) !== false) return true;
    }
    return false;
}

function findHotelInMessage($msg, $mysqli) {
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

function findLocationInMessage($msg, $mysqli) {
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

function findOrderCodeInMessage($msg) {
    if (preg_match('/ORD\d{10,}/i', $msg, $matches)) {
        return strtoupper($matches[0]);
    }
    return null;
}

function extractBudget($msg) {
    if (preg_match('/dưới\s*(\d+)\s*triệu/u', $msg, $m)) return (int)$m[1] * 1000000;
    if (preg_match('/dưới\s*(\d+)tr/u', $msg, $m))       return (int)$m[1] * 1000000;
    if (preg_match('/dưới\s*(\d+)\s*k/u', $msg, $m))     return (int)$m[1] * 1000;
    if (preg_match('/khoảng\s*(\d+)\s*triệu/u', $msg, $m)) return (int)$m[1] * 1000000 * 1.2;
    if (preg_match('/(\d+)\s*triệu/u', $msg, $m))        return (int)$m[1] * 1000000;
    return null;
}

// Quick replies theo intent
function getQuickReplies($intent, $foundHotel = null, $foundLocation = null) {
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
        'fallback'       => ["Khách sạn nổi bật", "Ưu đãi hôm nay", "Tư vấn chọn phòng", "Bài viết du lịch"],
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
if (count($_SESSION['chat_history']) > 6) {
    array_shift($_SESSION['chat_history']);
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
            echo '<a href="/pages/hotel_detail.php?id=' . $hotelId . '" style="background:#2563eb;color:white;padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block">Đặt phòng ngay</a>';
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
        $locFilter     = $foundLocation ? " AND h.location_id = " . (int)$foundLocation['id'] : "";
        $budgetSQL     = $budget ? " AND r.price <= " . (int)$budget : "";
        $roomTypeFilter = "";
        if (hasKeyword($messageLower, ['gia đình', 'family']))
            $roomTypeFilter = " AND LOWER(r.room_name) LIKE '%gia đình%'";
        elseif (hasKeyword($messageLower, ['cặp đôi', 'đôi', 'couple', 'lãng mạn', 'honeymoon']))
            $roomTypeFilter = " AND (LOWER(r.room_name) LIKE '%đôi%' OR LOWER(r.room_name) LIKE '%deluxe%')";
        elseif (hasKeyword($messageLower, ['cao cấp', 'sang trọng', 'vip', 'suite']))
            $roomTypeFilter = " AND (LOWER(r.room_name) LIKE '%cao c?p%' OR LOWER(r.room_name) LIKE '%suite%' OR LOWER(r.room_name) LIKE '%deluxe%')";

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
            $locText    = $foundLocation ? " tại {$foundLocation['name']}" : "";
            $budgetText = $budget ? " dưới " . number_format($budget) . " VNĐ" : "";
            echo "<b>Gợi ý phòng phù hợp$locText$budgetText:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                echo "<b>{$row['hotel_name']}</b> - {$row['rating']} ({$row['review_text']})<br>";
                echo "{$row['room_name']}: <b>" . number_format($row['price']) . " VNĐ/đêm</b> (còn {$row['quantity']} phòng)<br>";
                echo "?? {$row['loc']}<br>";
                echo '<a href="/pages/hotel_detail.php?id=' . $row['hotel_id'] . '" style="color:#2563eb;font-size:12px">Xem & đặt phòng</a><br><br>';
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
            "SELECT h.name, h.address, h.description, h.rating, h.review_text,
                    h.review_count, h.price, h.old_price, l.name AS location_name
            FROM hotels h LEFT JOIN locations l ON h.location_id = l.id
            WHERE h.id = $hotelId"
        );
        if ($result && $row = $result->fetch_assoc()) {
            $discount = ($row['old_price'] > $row['price'])
                ? " <s style='color:#999'>" . number_format($row['old_price']) . "</s>
                <span style='color:#e53e3e;font-size:12px'> (-" . round((1 - $row['price']/$row['old_price'])*100) . "%)</span>"
                : "";
            echo "?? <b>{$row['name']}</b><br>";
            echo "{$row['address']}" . ($row['location_name'] ? " - {$row['location_name']}" : "") . "<br>";
            echo "{$row['rating']} - {$row['review_text']} ({$row['review_count']} đánh giá)<br>";
            echo "Từ <b>" . number_format($row['price']) . " VNĐ/đêm</b>$discount<br><br>";
            if ($row['description']) echo "?? {$row['description']}<br><br>";
            echo '<a href="/pages/hotel_detail.php?id=' . $hotelId . '" style="color:#2563eb;font-weight:600">Xem chi tiết & đặt phòng</a>';
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
                "SELECT id, name, address, rating, review_text, review_count, price, old_price
                FROM hotels WHERE is_active = 1 AND location_id = $locId
                ORDER BY rating DESC LIMIT 8"
            );
            echo "<b>$locName có $total khách sạn:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                $discount = ($row['old_price'] > $row['price'])
                    ? " <small><s>" . number_format($row['old_price']) . "</s></small>" : "";
                echo "<b>{$row['name']}</b><br>";
                echo "{$row['rating']} - {$row['review_text']} ({$row['review_count']} đánh giá)<br>";
                echo "Từ <b>" . number_format($row['price']) . " VNĐ/đêm</b>$discount<br>";
                echo '<a href="/pages/hotel_detail.php?id=' . $row['id'] . '" style="color:#2563eb;font-size:12px">Xem chi tiết</a><br><br>';
            }
        } else {
            $result = $mysqli->query(
                "SELECT h.id, h.name, h.address, h.rating, h.review_text, h.price, l.name AS loc
                FROM hotels h JOIN locations l ON h.location_id = l.id
                WHERE h.is_active = 1 ORDER BY h.rating DESC LIMIT 6"
            );
            echo "<b>Khách sạn nổi bật:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                echo "<b>{$row['name']}</b> - {$row['loc']}<br>";
                echo "{$row['rating']} - {$row['review_text']}<br>";
                echo "Từ " . number_format($row['price']) . " VNĐ/đêm<br>";
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
    fallback_label:
    default:
        // GPT với lịch sử session
        $gptMessages = [
            [
                "role"    => "system",
                "content" => "Bạn là nhân viên tư vấn thân thiện của website đặt phòng StayGo.
Website phục vụ các khu vực: Kon Tum, Măng Đen, Quảng Ngãi.
Trả lời ngắn gọn, thân thiện bằng tiếng Việt.
Nếu câu hỏi ngoài phạm vi du lịch/khách sạn, lịch sự từ chối và gợi ý câu hỏi phù hợp."
            ]
        ];
        // Thêm lịch sử session vào context GPT
        foreach ($_SESSION['chat_history'] as $h) {
            $gptMessages[] = $h;
        }

        $data = [
            "model"      => "gpt-4o-mini",
            "messages"   => $gptMessages,
            "max_tokens" => 500
        ];

        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/json",
                "Authorization: Bearer $apiKey"
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);
        $response = curl_exec($ch);

        if (!$response) { echo "Xin lỗi, hệ thống đang bận. Vui lòng thử lại!"; break; }
        $result = json_decode($response, true);
        echo $result['choices'][0]['message']['content'] ?? "Xin lỗi, tôi chưa thể trả lời câu hỏi này.";
        break;
}

$botReply = ob_get_clean();

// Lưu reply vào session history
$_SESSION['chat_history'][] = ["role" => "assistant", "content" => strip_tags($botReply)];
if (count($_SESSION['chat_history']) > 6) {
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
