<?php
// Migration: thêm cancel_free_days vào bảng hotels
// Chạy 1 lần trên localhost VÀ Railway, rồi xóa file này
require_once __DIR__ . '/../config/database.php';

$results = [];

$sqls = [
    "ALTER TABLE hotels ADD COLUMN IF NOT EXISTS cancel_free_days TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'So ngay truoc check_in duoc huy mien phi. 0 = khong mien phi'",
    "UPDATE hotels SET cancel_free_days = 1 WHERE cancel_free_days = 0 OR cancel_free_days IS NULL",
];

foreach ($sqls as $sql) {
    $ok = $conn->query($sql);
    $results[] = ['sql' => substr($sql, 0, 80) . '...', 'ok' => $ok, 'err' => $conn->error];
}

echo '<pre style="font-family:monospace;padding:20px">';
echo "Migration: cancel_free_days\n";
echo str_repeat('=', 60) . "\n";
foreach ($results as $r) {
    echo ($r['ok'] ? '✅' : '❌') . ' ' . $r['sql'] . "\n";
    if (!$r['ok']) echo '   Error: ' . $r['err'] . "\n";
}
// Verify
$col = $conn->query("SHOW COLUMNS FROM hotels LIKE 'cancel_free_days'")->fetch_assoc();
echo "\nVerify: " . ($col ? '✅ cancel_free_days tồn tại (type: ' . $col['Type'] . ', default: ' . $col['Default'] . ')' : '❌ Cột chưa có') . "\n";
echo '</pre>';
