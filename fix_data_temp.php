<?php
require_once __DIR__ . '/config/database.php';
$conn->set_charset('utf8mb4');
$r = [];

// ── 0. Mở rộng cột rating tránh "Out of range" khi lưu 10.0 ─────────────────
$conn->query("ALTER TABLE hotels MODIFY COLUMN rating DECIMAL(3,1) DEFAULT 0.0");
$r[] = $conn->error ? "⚠️ ALTER rating: " . $conn->error : "✅ hotels.rating → DECIMAL(3,1)";

// ── 1. Sửa tên người dùng bị lỗi encoding ────────────────────────────────────
$users = [
    16 => 'Nguyễn Thị Mai',
    17 => 'Trần Văn Nam',
    18 => 'Phạm Thị Lan',
    19 => 'Lê Văn Đức',
    20 => 'Hoàng Thị Hương',
];
foreach ($users as $uid => $name) {
    $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $uid);
    $r[] = $stmt->execute() ? "✅ User #$uid → $name" : "❌ User #$uid: " . $conn->error;
}

// ── 2. Sửa đánh giá: rating về thang 1-5 + comment đúng tiếng Việt ──────────
$reviews = [
    27 => [4, 'Khách sạn sạch sẽ, vị trí trung tâm Kon Tum, thuận tiện đi lại. Nhân viên thân thiện và nhiệt tình.'],
    28 => [5, 'Phòng rộng rãi, view đẹp nhìn ra biển. Bữa sáng buffet đa dạng và ngon. Rất hài lòng, sẽ quay lại!'],
    29 => [5, 'Tuyệt vời! Không khí trong lành giữa rừng Mang Đen, dịch vụ chuyên nghiệp. Xứng đáng là điểm đến hàng đầu.'],
    30 => [5, 'Resort xanh mát giữa rừng thông, yên tĩnh và thư giãn. Nhân viên nhiệt tình, hỗ trợ chu đáo.'],
    31 => [5, 'Khách sạn boutique sang trọng, thiết kế tinh tế. Giá cao nhưng hoàn toàn xứng đáng với chất lượng dịch vụ.'],
    32 => [4, 'View núi tuyệt đẹp buổi sáng sương mù. Phòng ấm cúng, giường ngủ thoải mái. Ăn sáng ngon.'],
    33 => [5, 'Spa tuyệt vời, hồ bơi nước mặn rất sạch và đẹp. Gần biển Sa Huỳnh. Kỳ nghỉ thực sự thư giãn!'],
    34 => [4, 'Lần thứ 2 ở đây, vẫn rất hài lòng. Phòng tiêu chuẩn nhưng sạch và đầy đủ tiện nghi. Vị trí gần biển.'],
    35 => [4, 'Phòng sạch, điều hòa hoạt động tốt. Nhà hàng trong khách sạn có nhiều món ăn đặc sản Tây Nguyên.'],
    36 => [5, 'Casa Vanilla cực kỳ đẹp và lãng mạn. Thiết kế độc đáo, hòa quyện với thiên nhiên Mang Đen. Xuất sắc!'],
];
foreach ($reviews as $rid => [$rating, $comment]) {
    $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ?");
    $stmt->bind_param("isi", $rating, $comment, $rid);
    $r[] = $stmt->execute() ? "✅ Review #$rid → {$rating}⭐" : "❌ Review #$rid: " . $conn->error;
}

// ── 3. Cập nhật lại hotels.rating cho các khách sạn liên quan ────────────────
$hotel_ids = [17, 18, 19, 25, 26, 27, 31, 32];
foreach ($hotel_ids as $hid) {
    $res = $conn->query("
        SELECT ROUND(AVG(rating),1) AS avg_r, COUNT(*) AS cnt
        FROM reviews WHERE hotel_id = $hid AND is_active = 1
    ")->fetch_assoc();

    $avg_raw = (float)($res['avg_r'] ?? 0);
    $cnt     = (int)($res['cnt']    ?? 0);
    $avg     = round($avg_raw * 2, 1);

    $text = 'Chưa có đánh giá';
    if ($avg >= 9)     $text = 'Trên cả tuyệt vời';
    elseif ($avg >= 8) $text = 'Tuyệt vời';
    elseif ($avg >= 7) $text = 'Rất tốt';
    elseif ($avg >= 6) $text = 'Tốt';
    elseif ($avg >= 4) $text = 'Khá';
    elseif ($avg > 0)  $text = 'Bình thường';

    $stmt = $conn->prepare("UPDATE hotels SET rating = ?, review_text = ?, review_count = ? WHERE id = ?");
    $stmt->bind_param("dsii", $avg, $text, $cnt, $hid);
    $r[] = $stmt->execute()
        ? "✅ Hotel #$hid → {$avg}/10 · {$text} · {$cnt} đánh giá"
        : "❌ Hotel #$hid: " . $conn->error;
}

echo '<pre style="font-family:monospace;font-size:14px;line-height:1.8">';
foreach ($r as $line) echo $line . "\n";
echo '</pre>';

unlink(__FILE__);
echo '<p>🗑️ File đã tự xóa.</p>';
