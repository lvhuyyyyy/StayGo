<?php
/**
 * One-time script: bcrypt-hash partner_password for hotels that have
 * NULL or plaintext (< 60 chars) passwords.
 * Delete this file after running.
 */
require_once __DIR__ . '/../config/database.php';

// Very simple auth gate — change or remove after use
$secret = $_GET['secret'] ?? '';
if ($secret !== 'staygo_reset_2026') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$new_password = 'Hotel@2025'; // default password for all hotels
$hashed       = password_hash($new_password, PASSWORD_BCRYPT);

// Force update ALL hotels that have a partner_email (regardless of current password)
$sql  = "UPDATE hotels SET partner_password = ? WHERE partner_email IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $hashed);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo '<pre>';
echo "Updated: {$affected} hotel(s)\n";
echo "New password hash (60 chars): " . $hashed . "\n";
echo "Verify test: " . (password_verify($new_password, $hashed) ? 'PASS ✓' : 'FAIL ✗') . "\n";

// Show current state
$rows = $conn->query("SELECT id, name, partner_email, partner_status, CHAR_LENGTH(partner_password) AS pw_len FROM hotels WHERE partner_email IS NOT NULL ORDER BY id");
if ($rows) {
    echo "\nHotel partner accounts:\n";
    echo str_pad('ID', 5) . str_pad('Name', 30) . str_pad('Email', 30) . str_pad('Status', 12) . "PW len\n";
    echo str_repeat('-', 82) . "\n";
    while ($r = $rows->fetch_assoc()) {
        echo str_pad($r['id'], 5)
           . str_pad($r['name'], 30)
           . str_pad($r['partner_email'] ?? '—', 30)
           . str_pad($r['partner_status'] ?? '—', 12)
           . ($r['pw_len'] ?? 0) . "\n";
    }
}
echo '</pre>';
