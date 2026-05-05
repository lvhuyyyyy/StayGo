<?php
require_once __DIR__ . '/config/database.php';

$email    = 'admin@gmail.com';
$new_pass = password_hash('Admin@123', PASSWORD_BCRYPT);

$stmt = $conn->prepare("UPDATE users SET password=?, login_attempts=0, locked_until=NULL WHERE email=? AND role='admin'");
$stmt->bind_param("ss", $new_pass, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "✅ Reset xong: admin@gmail.com → Admin@123, lockout đã xóa.";
} else {
    $chk = $conn->query("SELECT id, role FROM users WHERE email='$email'")->fetch_assoc();
    echo $chk ? "⚠️ User tồn tại nhưng không update. Role: {$chk['role']}" : "❌ Không tìm thấy admin@gmail.com.";
}

unlink(__FILE__);
echo "<br>🗑️ File đã tự xóa.";
