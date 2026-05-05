<?php
session_start();

// Chỉ xóa session liên quan đến admin
// (giữ nguyên nếu user thường đang login song song ở tab khác)
unset($_SESSION['admin_id']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_name']);

// Hủy toàn bộ session
session_destroy();

// Về trang login admin
header("Location: /auth/login_admin.php?logout=1");
exit;
?>