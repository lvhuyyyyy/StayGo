<?php
/**
 * Seed hotel partner accounts on Railway DB.
 * Sets partner_email + hashed partner_password for all hotels.
 * Delete this file after running.
 */
require_once __DIR__ . '/../config/database.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'staygo_setup_2026') {
    http_response_code(403); echo 'Forbidden'; exit;
}

$password = 'Hotel@2025';
$hashed   = password_hash($password, PASSWORD_BCRYPT);

// Lấy danh sách hotels hiện có trên DB này
$hotels = $conn->query("SELECT id, name FROM hotels WHERE is_active = 1 ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if (empty($hotels)) {
    echo '<pre>Không có khách sạn nào trong database!</pre>'; exit;
}

echo '<pre>';
echo "Tổng số khách sạn: " . count($hotels) . "\n";
echo "Mật khẩu sẽ đặt: $password\n\n";

$updated = 0;
foreach ($hotels as $h) {
    // Tạo email từ tên khách sạn: chữ thường, bỏ dấu, bỏ khoảng trắng
    $slug = strtolower($h['name']);
    // Bỏ dấu tiếng Việt
    $map = ['à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ắ'=>'a','ặ'=>'a','ằ'=>'a','ẵ'=>'a','ẳ'=>'a','â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y','đ'=>'d'];
    $slug = strtr($slug, $map);
    // Bỏ ký tự đặc biệt và khoảng trắng
    $slug = preg_replace('/[^a-z0-9]/', '', $slug);
    $slug = substr($slug, 0, 20); // Giới hạn độ dài

    $email = $slug . '@staygo.vn';

    // Chỉ update nếu chưa có partner_email
    $stmt = $conn->prepare("UPDATE hotels SET partner_email = ?, partner_password = ?, partner_status = 'ACTIVE' WHERE id = ? AND (partner_email IS NULL OR partner_email = '')");
    $stmt->bind_param("ssi", $email, $hashed, $h['id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "✓ ID {$h['id']}: {$h['name']} → $email\n";
        $updated++;
    } else {
        // Đã có email rồi, chỉ update password
        $stmt2 = $conn->prepare("UPDATE hotels SET partner_password = ?, partner_status = 'ACTIVE' WHERE id = ?");
        $stmt2->bind_param("si", $hashed, $h['id']);
        $stmt2->execute();
        // Lấy email hiện tại để hiển thị
        $cur = $conn->query("SELECT partner_email FROM hotels WHERE id = {$h['id']}")->fetch_assoc();
        echo "~ ID {$h['id']}: {$h['name']} → {$cur['partner_email']} (giữ email, reset pw)\n";
        $updated++;
    }
}

echo "\n✅ Hoàn tất: $updated khách sạn\n\n";

// Hiển thị kết quả
$rows = $conn->query("SELECT id, name, partner_email, partner_status, CHAR_LENGTH(partner_password) AS pw_len FROM hotels WHERE is_active = 1 ORDER BY id");
echo str_pad('ID', 5) . str_pad('Name', 35) . str_pad('Email', 30) . str_pad('Status', 10) . "PW\n";
echo str_repeat('-', 85) . "\n";
while ($r = $rows->fetch_assoc()) {
    echo str_pad($r['id'], 5)
       . str_pad(mb_substr($r['name'], 0, 33), 35)
       . str_pad($r['partner_email'] ?? '—', 30)
       . str_pad($r['partner_status'] ?? '—', 10)
       . ($r['pw_len'] ?? 0) . "\n";
}

echo "\nPassword mặc định: $password\n";
echo '</pre>';
