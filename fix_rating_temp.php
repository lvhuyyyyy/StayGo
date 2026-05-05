<?php
require_once __DIR__ . '/config/database.php';

$results = [];

// Mở rộng cột rating từ decimal(2,1) → decimal(3,1)
if ($conn->query("ALTER TABLE hotels MODIFY COLUMN rating DECIMAL(3,1) DEFAULT 0.0")) {
    $results[] = "✅ Đã mở rộng cột rating → DECIMAL(3,1)";
} else {
    $results[] = "ℹ️ Cột rating: " . $conn->error;
}

// Fix Friendly Homestay (id=32): 5 sao × 2 = 10.0
if ($conn->query("UPDATE hotels SET rating = 10.0, review_text = 'Trên cả tuyệt vời' WHERE id = 32")) {
    $results[] = "✅ Friendly Homestay → 10.0 / Trên cả tuyệt vời";
}

// Fix Bông Boutique (id=34): đồng nhất review_text với rating 5.0
if ($conn->query("UPDATE hotels SET review_text = 'Bình thường' WHERE id = 34 AND rating < 7")) {
    $results[] = "✅ Bông Boutique → review_text đồng nhất với rating 5.0";
}

// Verify
$r = $conn->query("SELECT id, name, rating, review_text FROM hotels WHERE id IN (32,34)");
$results[] = "<br><b>Kết quả:</b>";
while ($row = $r->fetch_assoc()) {
    $results[] = "#{$row['id']} {$row['name']}: <b>{$row['rating']}</b> - {$row['review_text']}";
}

foreach ($results as $line) echo $line . "<br>";

unlink(__FILE__);
echo "<br>🗑️ File đã tự xóa.";
