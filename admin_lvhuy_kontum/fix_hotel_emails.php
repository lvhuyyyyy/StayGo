<?php
require_once __DIR__ . '/../config/database.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'staygo_email_2026') {
    http_response_code(403); echo 'Forbidden'; exit;
}

$password = 'Hotel@2025';
$hashed   = password_hash($password, PASSWORD_BCRYPT);

$hotels = [
    17 => 'indochine@staygo.vn',
    18 => 'cocoland@staygo.vn',
    19 => 'casavanilla@staygo.vn',
    25 => 'greenforest@staygo.vn',
    26 => 'goldenboutique@staygo.vn',
    27 => 'mountainlodge@staygo.vn',
    28 => 'dakke@staygo.vn',
    29 => 'mykhe@staygo.vn',
    30 => 'lysonpearl@staygo.vn',
    31 => 'sahuynh@staygo.vn',
    32 => 'friendlyhome@staygo.vn',
    33 => 'window1@staygo.vn',
    34 => 'bongboutique@staygo.vn',
];

echo '<pre>';
$stmt = $conn->prepare("UPDATE hotels SET partner_email = ?, partner_password = ?, partner_status = 'ACTIVE' WHERE id = ?");

foreach ($hotels as $id => $email) {
    $stmt->bind_param("ssi", $email, $hashed, $id);
    $stmt->execute();
    echo ($stmt->affected_rows > 0 ? "✓" : "~") . " ID $id → $email\n";
}

echo "\n✅ Hoàn tất!\n\n";

$rows = $conn->query("SELECT id, name, partner_email, partner_status FROM hotels WHERE id IN (17,18,19,25,26,27,28,29,30,31,32,33,34) ORDER BY id");
echo str_pad('ID',4) . str_pad('Name',30) . "Email\n" . str_repeat('-',70) . "\n";
while ($r = $rows->fetch_assoc()) {
    echo str_pad($r['id'],4) . str_pad(mb_substr($r['name'],0,28),30) . $r['partner_email'] . "\n";
}
echo "\nPassword: $password\n</pre>";
