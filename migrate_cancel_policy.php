<?php
// Migration runner: thêm cancel_free_days vào bảng hotels
// Chạy 1 lần rồi XÓA FILE NÀY
require_once __DIR__ . '/config/database.php';

$results = [];

$sqls = [
    "ALTER TABLE hotels ADD COLUMN IF NOT EXISTS cancel_free_days TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'So ngay truoc check_in duoc huy mien phi. 0 = khong mien phi'",
    "UPDATE hotels SET cancel_free_days = 1 WHERE cancel_free_days IS NULL",
];

foreach ($sqls as $sql) {
    $ok = $conn->query($sql);
    $results[] = ['sql' => substr($sql, 0, 80) . '...', 'ok' => $ok, 'err' => $conn->error];
}

echo '<pre style="font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0;min-height:100vh">';
echo "=== Migration: cancel_free_days ===\n\n";
foreach ($results as $r) {
    echo ($r['ok'] ? '✅' : '❌') . ' ' . $r['sql'] . "\n";
    if (!$r['ok']) echo '   Error: ' . $r['err'] . "\n";
}

$col = $conn->query("SHOW COLUMNS FROM hotels LIKE 'cancel_free_days'")->fetch_assoc();
echo "\nVerify: " . ($col
    ? '✅ cancel_free_days OK (type: ' . $col['Type'] . ', default: ' . $col['Default'] . ')'
    : '❌ Cột chưa tồn tại') . "\n";

echo "\n⚠️  Xóa file migrate_cancel_policy.php sau khi chạy xong!\n";
echo '</pre>';
