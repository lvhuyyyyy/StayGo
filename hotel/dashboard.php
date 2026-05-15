<?php
$page_title    = 'Dashboard';
$page_subtitle = 'Tổng quan hoạt động khách sạn';
require_once __DIR__ . '/../includes/hotel_header.php';

// ── Thống kê tổng quan ──────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*) AS total_bookings,
        SUM(b.status = 'pending')    AS pending_cnt,
        SUM(b.status = 'confirmed')  AS confirmed_cnt,
        SUM(b.status = 'completed')  AS completed_cnt,
        SUM(b.status = 'cancelled')  AS cancelled_cnt,
        -- Doanh thu: tất cả đơn (cả 2 luồng)
        COALESCE(SUM(CASE WHEN b.status IN ('confirmed','completed','checked_in') THEN b.total_price END), 0) AS total_revenue,
        -- Giải ngân: chỉ platform_collect (hotel_collect không qua payout)
        COALESCE(SUM(CASE WHEN b.payment_flow='platform_collect' AND b.payout_status='PAID'  THEN b.hotel_payout END), 0) AS total_received,
        COALESCE(SUM(CASE WHEN b.payment_flow='platform_collect' AND b.payout_status='READY' THEN b.hotel_payout END), 0) AS pending_payout
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
")->fetch_assoc();

// ── Bookings gần đây ─────────────────────────────────────────────────
$recent = $conn->query("
    SELECT b.id, b.order_code, b.full_name, b.email, b.check_in, b.check_out,
           b.total_price, b.status, b.payout_status, b.created_at, r.room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
    ORDER BY b.created_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ── Phòng đang active ─────────────────────────────────────────────────
$rooms_active = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE hotel_id = $hotel_id AND is_active = 1")->fetch_assoc()['c'];
$rooms_total  = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE hotel_id = $hotel_id")->fetch_assoc()['c'];
$total_capacity = (int)$conn->query("SELECT COALESCE(SUM(quantity),0) as c FROM rooms WHERE hotel_id = $hotel_id AND is_active = 1")->fetch_assoc()['c'];

// ── Occupancy rate hôm nay ────────────────────────────────────────────
$today = date('Y-m-d');
$occ_res = $conn->query("
    SELECT COUNT(*) AS occupied FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
      AND b.status IN ('confirmed','checked_in')
      AND b.check_in <= '$today' AND b.check_out > '$today'
");
$occupied_today = (int)($occ_res->fetch_assoc()['occupied'] ?? 0);
$occupancy_rate = $total_capacity > 0 ? round($occupied_today / $total_capacity * 100) : 0;

// ── Doanh thu 6 tháng gần nhất ───────────────────────────────────────
$revenue_months = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $label = date('m/Y', strtotime("-$i months"));
    $rev = $conn->query("
        SELECT COALESCE(SUM(b.total_price),0) AS rev
        FROM bookings b JOIN rooms r ON b.room_id = r.id
        WHERE r.hotel_id = $hotel_id
          AND b.status IN ('confirmed','checked_in','completed')
          AND DATE_FORMAT(b.created_at,'%Y-%m') = '$m'
    ")->fetch_assoc()['rev'] ?? 0;
    $revenue_months[] = ['label' => $label, 'value' => (float)$rev];
}

function badge_status(string $s): string {
    $map = ['pending'=>'badge-pending','confirmed'=>'badge-confirmed','completed'=>'badge-completed','cancelled'=>'badge-cancelled','checked_in'=>'badge-checked_in'];
    $lbl = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','completed'=>'Hoàn thành','cancelled'=>'Đã hủy','checked_in'=>'Đang ở'];
    return '<span class="badge '.($map[$s]??'badge-pending').'">'.($lbl[$s]??$s).'</span>';
}
?>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-num"><?= number_format((float)$stats['total_revenue'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Tổng doanh thu</div>
    </div>
    <div class="stat-card green">
        <div class="stat-num"><?= number_format((float)$stats['total_received'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Đã nhận giải ngân</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-num"><?= number_format((float)$stats['pending_payout'], 0, ',', '.') ?>đ</div>
        <div class="stat-label">Chờ giải ngân</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-num"><?= (int)$stats['total_bookings'] ?></div>
        <div class="stat-label">Tổng đặt phòng</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#92400e"><?= (int)$stats['pending_cnt'] ?></div>
        <div class="stat-label">Chờ xác nhận</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#1e40af"><?= (int)$stats['confirmed_cnt'] ?></div>
        <div class="stat-label">Đã xác nhận</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#065f46"><?= (int)$stats['completed_cnt'] ?></div>
        <div class="stat-label">Hoàn thành</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= $rooms_active ?> / <?= $rooms_total ?></div>
        <div class="stat-label">Phòng đang hoạt động</div>
    </div>
    <div class="stat-card" style="position:relative;overflow:hidden">
        <div style="display:flex;align-items:flex-end;gap:6px">
            <div class="stat-num" style="color:<?= $occupancy_rate >= 70 ? '#276749' : ($occupancy_rate >= 40 ? '#b7791f' : '#718096') ?>">
                <?= $occupancy_rate ?>%
            </div>
        </div>
        <div class="stat-label">Tỷ lệ lấp đầy hôm nay</div>
        <div style="margin-top:8px;background:#e2e8f0;border-radius:99px;height:6px;overflow:hidden">
            <div style="height:100%;width:<?= $occupancy_rate ?>%;background:<?= $occupancy_rate >= 70 ? '#38a169' : ($occupancy_rate >= 40 ? '#dd6b20' : '#a0aec0') ?>;border-radius:99px;transition:.3s"></div>
        </div>
        <div style="font-size:11px;color:#a0aec0;margin-top:4px"><?= $occupied_today ?>/<?= $total_capacity ?> phòng đang có khách</div>
    </div>
</div>

<!-- Alert: pending bookings cần xử lý -->
<?php if ($stats['pending_cnt'] > 0): ?>
<div class="alert alert-info" style="margin-bottom:20px">
    📢 Bạn có <strong><?= (int)$stats['pending_cnt'] ?></strong> đặt phòng đang chờ xác nhận.
    <a href="/hotel/bookings.php?status=pending" style="color:#1a365d;font-weight:700;margin-left:8px">Xem ngay →</a>
</div>
<?php endif; ?>

<!-- Recent bookings -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📋 Đặt phòng gần đây</span>
        <a href="/hotel/bookings.php" class="btn btn-outline btn-sm">Xem tất cả</a>
    </div>
    <div class="card-body">
        <?php if (empty($recent)): ?>
        <div style="text-align:center;padding:40px;color:#a0aec0">Chưa có đặt phòng nào.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách</th>
                    <th>Phòng</th>
                    <th>Ngày ở</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $b): ?>
            <tr>
                <td><code style="font-size:12px;background:#f0f4f8;padding:2px 7px;border-radius:5px"><?= htmlspecialchars($b['order_code']) ?></code></td>
                <td>
                    <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($b['full_name']) ?></div>
                    <div style="font-size:11px;color:#a0aec0"><?= htmlspecialchars($b['email']) ?></div>
                </td>
                <td style="font-size:13px"><?= htmlspecialchars($b['room_name']) ?></td>
                <td style="font-size:12px;white-space:nowrap;color:#718096">
                    <?= date('d/m/Y', strtotime($b['check_in'])) ?><br>
                    → <?= date('d/m/Y', strtotime($b['check_out'])) ?>
                </td>
                <td style="font-weight:700;color:#276749"><?= number_format($b['total_price'], 0, ',', '.') ?>đ</td>
                <td><?= badge_status($b['status']) ?></td>
                <td>
                    <?php if ($b['status'] === 'pending'): ?>
                    <a href="/hotel/bookings.php?highlight=<?= $b['id'] ?>" class="btn btn-primary btn-sm">Xử lý</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Biểu đồ doanh thu 6 tháng -->
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <span class="card-title">📈 Doanh thu 6 tháng gần nhất</span>
    </div>
    <div class="card-body" style="padding:20px">
        <canvas id="revenueChart" height="90"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    var labels = <?= json_encode(array_column($revenue_months, 'label')) ?>;
    var values = <?= json_encode(array_column($revenue_months, 'value')) ?>;
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: values,
                backgroundColor: 'rgba(49,130,206,0.18)',
                borderColor: '#3182ce',
                borderWidth: 2,
                borderRadius: 8,
                hoverBackgroundColor: 'rgba(49,130,206,0.35)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + parseInt(ctx.raw).toLocaleString('vi') + ' VNĐ';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            if (v >= 1000000) return (v/1000000).toFixed(0) + 'tr';
                            if (v >= 1000)    return (v/1000).toFixed(0) + 'k';
                            return v;
                        },
                        font: { size: 11 }
                    },
                    grid: { color: '#f0f4f8' }
                },
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
