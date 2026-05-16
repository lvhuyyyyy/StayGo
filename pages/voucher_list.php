<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

$amount = (float)($_POST['amount'] ?? 0);

$rows = $conn->query("
    SELECT code, type, value, min_order, expires_at, description
    FROM vouchers
    WHERE is_active = 1
      AND (expires_at IS NULL OR expires_at >= CURDATE())
      AND used_count < max_uses
    ORDER BY value DESC
    LIMIT 20
");

$list = [];
while ($r = $rows->fetch_assoc()) {
    $applicable = $amount <= 0 || $amount >= (float)$r['min_order'];
    $list[] = [
        'code'        => $r['code'],
        'type'        => $r['type'],
        'value'       => (float)$r['value'],
        'min_order'   => (float)$r['min_order'],
        'expires_at'  => $r['expires_at'],
        'description' => $r['description'],
        'applicable'  => $applicable,
    ];
}

echo json_encode($list, JSON_UNESCAPED_UNICODE);
