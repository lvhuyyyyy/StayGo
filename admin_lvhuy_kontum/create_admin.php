<?php
require_once __DIR__ . '/../config/database.php';

/*
tài khoản mặc định admin
Email: admin@gmail.com
Password: admin123
*/

$email     = "admin@gmail.com";
$password  = "admin123";
$full_name = "Administrator";
$role      = "admin";

/* Kiểm tra admin đã tồn tại chưa */
$sql  = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {

    echo "⚠️ Admin đã tồn tại rồi.";

} else {

    /* Hash password */
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    /* Tạo admin */
    $insert = "INSERT INTO users (full_name, email, password, role)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($insert);
    $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);

    if ($stmt->execute()) {
        echo "✅ Tạo admin thành công!<br>";
        echo "Email: admin@gmail.com<br>";
        echo "Password: admin123";
    } else {
        echo "❌ Lỗi tạo admin.";
    }
}
?>