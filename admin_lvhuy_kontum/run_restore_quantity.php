<?php
require_once __DIR__ . '/../config/database.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'staygo_migrate_2026') {
    http_response_code(403); echo 'Forbidden'; exit;
}

echo '<pre>';

// Trước khi restore: hiển thị trạng thái hiện tại
$before = $conn->query("SELECT id, room_name, quantity FROM rooms ORDER BY hotel_id, price");
echo "=== TRƯỚC KHI RESTORE ===\n";
echo str_pad('ID', 5) . str_pad('Room', 30) . "Quantity\n";
echo str_repeat('-', 45) . "\n";
while ($r = $before->fetch_assoc()) {
    echo str_pad($r['id'], 5) . str_pad($r['room_name'], 30) . $r['quantity'] . "\n";
}

// Chạy restore
$result = $conn->query("
    UPDATE rooms r
    SET r.quantity = r.quantity + (
        SELECT COUNT(*) FROM bookings b
        WHERE b.room_id = r.id
        AND b.status NOT IN ('cancelled', 'completed')
    )
");

if ($result) {
    echo "\n✅ Restore thành công! Affected: " . $conn->affected_rows . " rows\n";
} else {
    echo "\n❌ Lỗi: " . $conn->error . "\n";
}

// Sau khi restore
$after = $conn->query("SELECT id, room_name, quantity FROM rooms ORDER BY hotel_id, price");
echo "\n=== SAU KHI RESTORE ===\n";
echo str_pad('ID', 5) . str_pad('Room', 30) . "Quantity\n";
echo str_repeat('-', 45) . "\n";
while ($r = $after->fetch_assoc()) {
    echo str_pad($r['id'], 5) . str_pad($r['room_name'], 30) . $r['quantity'] . "\n";
}

echo "\nXong! Hãy xóa file này ngay.\n";
echo '</pre>';
