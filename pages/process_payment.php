<?php
require_once "../config/database.php";

// ── Lấy & validate đầu vào ────────────────────────────────────────────────────
$full_name      = trim($_POST['full_name']      ?? '');
$email          = trim($_POST['email']          ?? '');
$phone          = trim($_POST['phone']          ?? '');
$order_code     = trim($_POST['order_code']     ?? '');
$check_in       = trim($_POST['checkin']        ?? '');
$check_out      = trim($_POST['checkout']       ?? '');
$room_id        = (int)($_POST['room_id']       ?? 0);
$payment_method = trim($_POST['payment_method'] ?? '');
$note           = trim($_POST['note']           ?? '');

// Validate bắt buộc
if ($room_id <= 0)                                  die("Phòng không hợp lệ!");
if (empty($full_name) || empty($email) || empty($phone)) die("Thiếu thông tin bắt buộc!");
if (!filter_var($email, FILTER_VALIDATE_EMAIL))     die("Email không hợp lệ!");
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) die("Ngày không hợp lệ!");

$allowed_methods = ['bank', 'momo', 'vnpay', 'card', 'hotel'];
if (!in_array($payment_method, $allowed_methods, true)) die("Phương thức thanh toán không hợp lệ!");

// ── Bước 1: Lấy thông tin phòng + khách sạn ──────────────────────────────────
$stmt = $conn->prepare("
    SELECT r.price, r.room_name, r.hotel_id, r.min_stay, r.max_stay, h.name AS hotel_name
    FROM rooms r
    JOIN hotels h ON r.hotel_id = h.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$roomData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$roomData) die("Phòng không tồn tại!");

$price_per_day = $roomData['price'];
$room_name     = $roomData['room_name'];
$hotel_id      = $roomData['hotel_id'];
$hotel_name    = $roomData['hotel_name'];

// ── Bước 2: Tính số ngày ở ───────────────────────────────────────────────────
$start = strtotime($check_in);
$end   = strtotime($check_out);
$days  = ($end - $start) / (60 * 60 * 24);

if ($days <= 0) die("Ngày trả phòng phải lớn hơn ngày nhận phòng!");

$min_stay = (int)($roomData['min_stay'] ?? 1);
$max_stay = $roomData['max_stay'] ? (int)$roomData['max_stay'] : null;
if ($days < $min_stay) die("Phòng này yêu cầu lưu trú tối thiểu $min_stay đêm.");
if ($max_stay && $days > $max_stay) die("Phòng này chỉ cho đặt tối đa $max_stay đêm.");

// ── Bước 3: Tính tổng tiền ────────────────────────────────────────────────────
$total_price = $price_per_day * $days;

// ── Bước 4: INSERT bookings (prepared statement) ──────────────────────────────
$stmt2 = $conn->prepare("
    INSERT INTO bookings
        (order_code, room_id, full_name, email, phone, check_in, check_out, total_price, payment_method, note, status)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
$stmt2->bind_param("sisssssdss",
    $order_code, $room_id, $full_name, $email, $phone,
    $check_in, $check_out, $total_price, $payment_method, $note
);

if ($stmt2->execute()) {
    $booking_id = $conn->insert_id;
    $stmt2->close();

    // ── Bước 5: INSERT payments (prepared statement) ──────────────────────────
    $stmt3 = $conn->prepare("
        INSERT INTO payments
            (booking_id, hotel_id, hotel_name, room_name, payment_method, full_name, email, phone, amount, payment_status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt3->bind_param("iissssssd",
        $booking_id, $hotel_id, $hotel_name, $room_name,
        $payment_method, $full_name, $email, $phone, $total_price
    );
    $stmt3->execute();
    $stmt3->close();

    // ── Bước 6: Giảm số lượng phòng (chỉ khi còn phòng) ─────────────────────
    $stmt4 = $conn->prepare("UPDATE rooms SET quantity = quantity - 1 WHERE id = ? AND quantity > 0");
    $stmt4->bind_param("i", $room_id);
    $stmt4->execute();
    $stmt4->close();

    echo "<script>
        alert('Đặt phòng thành công! Tổng tiền: " . number_format($total_price) . " VNĐ');
        window.location='../index.php';
    </script>";

} else {
    $stmt2->close();
    echo "Lỗi: Không thể tạo đơn đặt phòng, vui lòng thử lại!";
}

$conn->close();
?>
