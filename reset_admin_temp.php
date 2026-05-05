<?php
// FILE TẠM - XÓA NGAY SAU KHI DÙNG
require_once __DIR__ . '/config/database.php';

$newPass = password_hash('Admin@123', PASSWORD_DEFAULT);
$email = 'admin@gmail.com';

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $newPass, $email);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo "✅ Đã reset mật khẩu admin@gmail.com thành Admin@123";
} else {
    echo "❌ Lỗi hoặc không tìm thấy tài khoản";
}
$stmt->close();

// Tự xóa file sau khi chạy
unlink(__FILE__);
echo "<br>🗑️ File đã tự xóa.";
