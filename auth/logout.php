<?php
session_start();

// FIX: Chỉ xóa session user, giữ nguyên session admin
unset($_SESSION['user_id']);
unset($_SESSION['role']);
unset($_SESSION['full_name']);
unset($_SESSION['email']);
// Xóa thêm các session liên quan user nếu có
unset($_SESSION['redirect_after_login']);
unset($_SESSION['user_cache']);

// KHÔNG dùng session_destroy() hoặc $_SESSION = array()
// vì sẽ xóa cả session admin đang đăng nhập

// Chuyển về trang chủ
header("Location: /tour_khach_san_project/index.php");
exit();
?>