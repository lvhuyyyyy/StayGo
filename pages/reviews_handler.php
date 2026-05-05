<?php
// --------------------------------------------------------
//  reviews_handler.php
//  Đặt tại: pages/reviews_handler.php
//  Xử lý: submit đánh giá + xoá đánh giá (admin)
// --------------------------------------------------------

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// -- Helper response --
function json_resp($ok, $msg, $data = []) {
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

// ----------------------------------------
//  SUBMIT ĐÁNH GIÁ
// ----------------------------------------
if ($action === 'submit') {

    // Phải đăng nhập
    if (empty($_SESSION['user_id'])) {
        json_resp(false, 'Bạn cần đăng nhập để đánh giá.');
    }

    $user_id    = (int)$_SESSION['user_id'];
    $hotel_id   = (int)($_POST['hotel_id']   ?? 0);
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $rating     = (int)($_POST['rating']     ?? 0);
    $comment    = trim($_POST['comment']     ?? '');

    // Validate
    if (!$hotel_id || !$booking_id) json_resp(false, 'Dữ liệu không hợp lệ.');
    if ($rating < 1 || $rating > 5)  json_resp(false, 'Vui lòng chọn số sao (1–5).');
    if (strlen($comment) < 10)       json_resp(false, 'Nhận xét tối thiểu 10 ký tự.');

    // Kiểm tra booking hợp lệ: đúng user, đúng hotel, status = completed
    $stmt = $conn->prepare("
        SELECT b.id
        FROM   bookings b
        JOIN   rooms    r ON r.id = b.room_id
        WHERE  b.id      = ?
        AND  b.user_id = ?
        AND  r.hotel_id = ?
        AND  b.status  = 'completed'
        LIMIT 1
    ");
    $stmt->bind_param("iii", $booking_id, $user_id, $hotel_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        json_resp(false, 'Bạn chỉ có thể đánh giá khách sạn sau khi hoàn thành booking.');
    }

    // Kiểm tra đã review booking này chưa
    $stmt2 = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ?");
    $stmt2->bind_param("i", $booking_id);
    $stmt2->execute();
    if ($stmt2->get_result()->fetch_assoc()) {
        json_resp(false, 'Bạn đã đánh giá booking này rồi.');
    }

    // Insert review
    $stmt3 = $conn->prepare("
        INSERT INTO reviews (hotel_id, user_id, booking_id, rating, comment)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt3->bind_param("iiiis", $hotel_id, $user_id, $booking_id, $rating, $comment);
    if (!$stmt3->execute()) {
        json_resp(false, 'Có lỗi xảy ra, vui lòng thử lại.');
    }

    // Cập nhật rating trung bình cho hotel
    _update_hotel_rating($conn, $hotel_id);

    json_resp(true, 'Đánh giá của bạn đã được ghi nhận!');
}

// ----------------------------------------
//  XOÁ ĐÁNH GIÁ (admin)
// ----------------------------------------
if ($action === 'delete') {

    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        json_resp(false, 'Không có quyền thực hiện.');
    }

    $review_id = (int)($_POST['review_id'] ?? 0);
    if (!$review_id) json_resp(false, 'ID không hợp lệ.');

    // Lấy hotel_id trước khi xoá
    $r = $conn->query("SELECT hotel_id FROM reviews WHERE id = $review_id")->fetch_assoc();
    if (!$r) json_resp(false, 'Không tìm thấy đánh giá.');

    $conn->query("DELETE FROM reviews WHERE id = $review_id");

    // Cập nhật lại rating hotel
    _update_hotel_rating($conn, $r['hotel_id']);
    log_activity($conn, 'delete_review', 'review', $review_id, "Xóa đánh giá #$review_id");

    json_resp(true, 'Đã xoá đánh giá.');
}

// ----------------------------------------
//  Helper: cập nhật rating + review_count
// ----------------------------------------
function _update_hotel_rating($conn, $hotel_id) {
    $res = $conn->query("
        SELECT ROUND(AVG(rating),1) AS avg_r, COUNT(*) AS cnt
        FROM reviews
        WHERE hotel_id = $hotel_id AND is_active = 1
    ")->fetch_assoc();

    $avg = $res['avg_r'] ?? 0;
    $cnt = $res['cnt']   ?? 0;

    // Map rating số → text
    $text = 'Chưa có đánh giá';
    if ($avg >= 4.5)     $text = 'Trên cả tuyệt vời';
    elseif ($avg >= 4.0) $text = 'Tuyệt vời';
    elseif ($avg >= 3.5) $text = 'Rất tốt';
    elseif ($avg >= 3.0) $text = 'Tốt';
    elseif ($avg >= 2.0) $text = 'Khá';
    elseif ($avg > 0)    $text = 'Trung bình';

    $stmt = $conn->prepare("
        UPDATE hotels SET rating = ?, review_text = ?, review_count = ? WHERE id = ?
    ");
    $stmt->bind_param("dsii", $avg, $text, $cnt, $hotel_id);
    $stmt->execute();
}

json_resp(false, 'Hành động không hợp lệ.');