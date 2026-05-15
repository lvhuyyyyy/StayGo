<?php
$page_title    = 'Lịch sử giải ngân';
$page_subtitle = 'Xem chi tiết các khoản giải ngân từ platform';
require_once __DIR__ . '/../includes/hotel_header.php';

// ── Thống kê tổng giải ngân — chỉ platform_collect ───────────────────
$fin = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN b.payout_status='PAID'    THEN b.hotel_payout END), 0) AS total_paid,
        COALESCE(SUM(CASE WHEN b.payout_status='READY'   THEN b.hotel_payout END), 0) AS total_ready,
        COALESCE(SUM(CASE WHEN b.payout_status='FROZEN'  THEN b.hotel_payout END), 0) AS total_frozen,
        COALESCE(SUM(CASE WHEN b.payout_status='HOLDING' AND b.status IN ('confirmed','checked_in') THEN b.hotel_payout END), 0) AS total_holding,
        COALESCE(SUM(b.platform_revenue), 0) AS total_commission
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
      AND b.status NOT IN ('pending','cancelled')
      AND b.payment_flow = 'platform_collect'
")->fetch_assoc();

// ── Danh sách các khoản từ bảng payouts ────────────────────────────────
$payouts = $conn->query("
    SELECT p.*, b.order_code, b.check_in, b.check_out, b.total_price
    FROM payouts p
    JOIN bookings b ON p.booking_id = b.id
    WHERE p.hotel_id = $hotel_id
    ORDER BY p.processed_at DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Booking READY chờ admin giải ngân — chỉ platform_collect ──────────
$ready_bookings = $conn->query("
    SELECT b.id, b.order_code, b.full_name, b.check_in, b.check_out,
           b.total_price, b.hotel_payout, b.platform_revenue, b.commission_rate,
           b.payout_status, r.room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
      AND b.payout_status = 'READY'
      AND b.payment_flow = 'platform_collect'
    ORDER BY b.check_out ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Booking HOLDING / FROZEN — chỉ platform_collect ──────────────────
$other_bookings = $conn->query("
    SELECT b.id, b.order_code, b.full_name, b.check_in, b.check_out,
           b.total_price, b.hotel_payout, b.commission_rate,
           b.payout_status, b.status, r.room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
      AND b.payout_status IN ('HOLDING','FROZEN')
      AND b.status NOT IN ('cancelled','pending')
      AND b.payment_flow = 'platform_collect'
    ORDER BY b.check_out ASC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

function badge_payout($s) {
    $map = ['HOLDING'=>['badge-holding','Đang giữ'],'READY'=>['badge-ready','Sẵn sàng'],'FROZEN'=>['badge-frozen','Đóng băng'],'PAID'=>['badge-paid','Đã giải ngân']];
    [$cls, $lbl] = $map[$s] ?? ['badge-holding', $s];
    return "<span class=\"badge $cls\">$lbl</span>";
}
?>

<!-- Finance summary -->
<div class="stat-grid" style="margin-bottom:24px">
    <div class="stat-card green">
        <div class="stat-num"><?= number_format((float)$fin['total_paid'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Đã nhận</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-num"><?= number_format((float)$fin['total_ready'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Chờ giải ngân</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-num"><?= number_format((float)$fin['total_holding'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Đang giữ (chưa trả phòng)</div>
    </div>
    <div class="stat-card" style="border:1.5px solid #fee2e2">
        <div class="stat-num" style="color:#c53030"><?= number_format((float)$fin['total_frozen'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Đóng băng (tranh chấp)</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#718096"><?= number_format((float)$fin['total_commission'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Hoa hồng platform</div>
    </div>
</div>

<?php if (!empty($ready_bookings)): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title" style="color:#276749">💰 Sẵn sàng giải ngân (<?= count($ready_bookings) ?> đơn)</span>
        <span style="font-size:12px;color:#a0aec0">Admin sẽ xử lý sớm</span>
    </div>
    <div class="card-body">
        <table>
            <thead>
                <tr><th>Mã đơn</th><th>Khách</th><th>Phòng</th><th>Trả phòng</th><th>Doanh thu</th><th>Hoa hồng (%)</th><th>Bạn nhận</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ready_bookings as $b): ?>
            <tr>
                <td><code style="font-size:12px;background:#f0f4f8;padding:2px 7px;border-radius:5px"><?= htmlspecialchars($b['order_code']) ?></code></td>
                <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($b['full_name']) ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($b['room_name']) ?></td>
                <td style="font-size:12px;color:#718096"><?= date('d/m/Y', strtotime($b['check_out'])) ?></td>
                <td><?= number_format($b['total_price'], 0, ',', '.') ?>đ</td>
                <td style="color:#718096"><?= number_format($b['commission_rate'], 1) ?>%</td>
                <td style="font-weight:700;color:#276749"><?= number_format($b['hotel_payout'], 0, ',', '.') ?>đ</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($other_bookings)): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">⏳ Đang chờ / Đóng băng</span>
    </div>
    <div class="card-body">
        <table>
            <thead>
                <tr><th>Mã đơn</th><th>Khách</th><th>Phòng</th><th>Trả phòng</th><th>Dự kiến nhận</th><th>Trạng thái</th></tr>
            </thead>
            <tbody>
            <?php foreach ($other_bookings as $b): ?>
            <tr>
                <td><code style="font-size:12px;background:#f0f4f8;padding:2px 7px;border-radius:5px"><?= htmlspecialchars($b['order_code']) ?></code></td>
                <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($b['full_name']) ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($b['room_name']) ?></td>
                <td style="font-size:12px;color:#718096"><?= date('d/m/Y', strtotime($b['check_out'])) ?></td>
                <td style="font-weight:600"><?= number_format($b['hotel_payout'], 0, ',', '.') ?>đ</td>
                <td><?= badge_payout($b['payout_status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lịch sử giải ngân đã thực hiện -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📜 Lịch sử giải ngân đã nhận</span>
    </div>
    <div class="card-body">
        <?php if (empty($payouts)): ?>
        <div style="text-align:center;padding:60px;color:#a0aec0">Chưa có khoản giải ngân nào.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Mã đơn</th><th>Ngày ở</th><th>Doanh thu đơn</th><th>Hoa hồng</th><th>Nhận được</th><th>Admin xử lý</th><th>Ngày nhận</th></tr>
            </thead>
            <tbody>
            <?php foreach ($payouts as $i => $p): ?>
            <tr>
                <td style="color:#a0aec0;font-size:12px"><?= $i+1 ?></td>
                <td><code style="font-size:12px;background:#f0f4f8;padding:2px 7px;border-radius:5px"><?= htmlspecialchars($p['order_code']) ?></code></td>
                <td style="font-size:12px;color:#718096">
                    <?= date('d/m/Y', strtotime($p['check_in'])) ?> – <?= date('d/m/Y', strtotime($p['check_out'])) ?>
                </td>
                <td><?= number_format($p['total_price'], 0, ',', '.') ?>đ</td>
                <td style="color:#718096"><?= number_format($p['commission_amount'], 0, ',', '.') ?>đ</td>
                <td style="font-weight:700;color:#276749"><?= number_format($p['amount'], 0, ',', '.') ?>đ</td>
                <td style="font-size:12px"><?= htmlspecialchars($p['processed_by_name'] ?? 'Admin') ?></td>
                <td style="font-size:12px;color:#718096;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($p['processed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
