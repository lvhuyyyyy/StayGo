<?php
require_once __DIR__ . '/config/database.php';

// Sửa đường dẫn ảnh blog bị thừa /tour_khach_san_project/
$conn->query("UPDATE blog_posts SET thumb = REPLACE(thumb, '/tour_khach_san_project/', '/') WHERE thumb LIKE '/tour_khach_san_project/%'");
$fixed = $conn->affected_rows;
echo "✅ Đã sửa $fixed bài viết.<br>";

// Hiển thị kết quả
$rows = $conn->query("SELECT id, title, thumb FROM blog_posts")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $r) {
    echo "#{$r['id']}: " . htmlspecialchars($r['thumb']) . "<br>";
}

unlink(__FILE__);
echo "<br>🗑️ File đã tự xóa.";
