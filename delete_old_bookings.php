<?php
// Xóa 2 booking test cũ: BK2026002, BK2026008
// Chạy 1 lần rồi XÓA FILE NÀY
require_once __DIR__ . '/config/database.php';

$targets = ['BK2026002', 'BK2026008'];
$results = [];

foreach ($targets as $code) {
    $esc = $conn->real_escape_string($code);
    $row = $conn->query("SELECT id FROM bookings WHERE order_code = '$esc'")->fetch_assoc();
    if (!$row) {
        $results[] = "❌ $code — không tìm thấy";
        continue;
    }
    $id = (int)$row['id'];

    // Xóa theo thứ tự: logs → payments → booking
    $conn->query("DELETE FROM booking_logs WHERE booking_id = $id");
    $conn->query("DELETE FROM payments WHERE booking_id = $id");
    $conn->query("DELETE FROM bookings WHERE id = $id");

    $results[] = $conn->affected_rows >= 0
        ? "✅ $code (id=$id) — đã xóa"
        : "❌ $code — lỗi: " . $conn->error;
}

echo '<pre style="font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0;min-height:100vh">';
echo "=== Xóa booking cũ ===\n\n";
foreach ($results as $r) echo $r . "\n";
echo "\n⚠️  Xóa file delete_old_bookings.php sau khi chạy xong!\n";
echo '</pre>';
