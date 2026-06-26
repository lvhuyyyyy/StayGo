<?php
include "../config/database.php";

$page_title    = 'Finance';
$page_subtitle = 'Tổng quan tài chính nền tảng';
include "../includes/admin_header.php";

// ── Tổng quan platform — chỉ tính platform_collect (hotel_collect = 0 commission) ───
$overview = $conn->query("
    SELECT
        COUNT(*) AS total_bookings,
        COALESCE(SUM(total_price), 0) AS total_gmv,
        COALESCE(SUM(platform_revenue), 0) AS total_commission,
        COALESCE(SUM(CASE WHEN payout_status='PAID'    THEN hotel_payout ELSE 0 END), 0) AS total_paid_out,
        COALESCE(SUM(CASE WHEN payout_status='READY'   THEN hotel_payout ELSE 0 END), 0) AS total_ready,
        COALESCE(SUM(CASE WHEN payout_status='HOLDING' THEN hotel_payout ELSE 0 END), 0) AS total_holding,
        COALESCE(SUM(CASE WHEN payout_status='FROZEN'  THEN hotel_payout ELSE 0 END), 0) AS total_frozen
    FROM bookings
    WHERE status NOT IN ('cancelled')
      AND payment_flow = 'platform_collect'
")->fetch_assoc();

// ── Doanh thu hoa hồng theo tháng (6 tháng gần nhất) ─────────────────
$monthly_commission = [];
for ($i = 5; $i >= 0; $i--) {
    $m   = date('m', strtotime("-$i months"));
    $y   = date('Y', strtotime("-$i months"));
    $row = $conn->query("
        SELECT
            COALESCE(SUM(platform_revenue), 0) AS commission,
            COALESCE(SUM(total_price), 0) AS gmv,
            COUNT(*) AS cnt
        FROM bookings
        WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y
          AND status NOT IN ('cancelled')
          AND payment_flow = 'platform_collect'
    ")->fetch_assoc();
    $monthly_commission[] = [
        'label'      => "$m/$y",
        'commission' => (float)$row['commission'],
        'gmv'        => (float)$row['gmv'],
        'cnt'        => (int)$row['cnt'],
    ];
}

// ── Sao kê per hotel ──────────────────────────────────────────────────
$hotel_stats = $conn->query("
    SELECT
        h.id,
        h.name,
        h.commission_rate,
        h.partner_status,
        COUNT(b.id)                                                            AS total_bookings,
        COALESCE(SUM(b.total_price), 0)                                       AS total_gmv,
        COALESCE(SUM(b.platform_revenue), 0)                                   AS total_commission,
        COALESCE(SUM(CASE WHEN b.payout_status='PAID'    THEN b.hotel_payout ELSE 0 END), 0) AS paid_out,
        COALESCE(SUM(CASE WHEN b.payout_status='READY'   THEN b.hotel_payout ELSE 0 END), 0) AS ready,
        COALESCE(SUM(CASE WHEN b.payout_status='HOLDING' THEN b.hotel_payout ELSE 0 END), 0) AS holding,
        COALESCE(SUM(CASE WHEN b.payout_status='FROZEN'  THEN b.hotel_payout ELSE 0 END), 0) AS frozen
    FROM hotels h
    LEFT JOIN rooms r ON r.hotel_id = h.id
    LEFT JOIN bookings b ON b.room_id = r.id
                         AND b.status NOT IN ('cancelled')
                         AND b.payment_flow = 'platform_collect'
    GROUP BY h.id
    ORDER BY total_commission DESC
")->fetch_all(MYSQLI_ASSOC);

// ── Reconciliation: expected vs actual cash received ─────────────────
// Expected = tổng tiền khách đã xác nhận trả (booking confirmed/completed, platform_collect)
// Actual   = tổng tiền thực tế ghi nhận trong bảng payments (payment_status = 'paid')
// Diff > 0 → có booking chưa thu được tiền → cần kiểm tra
$recon = $conn->query("
    SELECT
        COALESCE(SUM(b.total_price), 0) AS expected
    FROM bookings b
    WHERE b.payment_flow = 'platform_collect'
      AND b.status IN ('confirmed', 'completed')
")->fetch_assoc();

$actual_row = $conn->query("
    SELECT COALESCE(SUM(p.amount), 0) AS actual
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE p.payment_status = 'paid'
      AND b.payment_flow   = 'platform_collect'
")->fetch_assoc();

$recon_expected = (float)$recon['expected'];
$recon_actual   = (float)$actual_row['actual'];
$recon_diff     = $recon_expected - $recon_actual;

$partner_status_map = [
    'ACTIVE'    => ['Hoạt động',   '#276749', '#f0fff4'],
    'PENDING'   => ['Chờ duyệt',   '#b7791f', '#fffbeb'],
    'SUSPENDED' => ['Tạm đình chỉ','#c53030', '#fff5f5'],
    'REJECTED'  => ['Từ chối',     '#718096', '#f7fafc'],
];

$chart_labels     = json_encode(array_column($monthly_commission, 'label'));
$chart_commission = json_encode(array_column($monthly_commission, 'commission'));
$chart_gmv        = json_encode(array_column($monthly_commission, 'gmv'));
?>

<!-- ── Summary cards ─────────────────────────────────────────────── -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">

    <div class="stat-card revenue">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($overview['total_commission'],0,',','.') ?>đ</div>
            <div class="stat-label">Tổng hoa hồng thu</div>
            <div class="stat-sub">GMV: <?= number_format($overview['total_gmv'],0,',','.') ?>đ</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fff4">✅</div>
        <div class="stat-info">
            <div class="stat-value" style="color:#276749"><?= number_format($overview['total_paid_out'],0,',','.') ?>đ</div>
            <div class="stat-label">Đã giải ngân cho hotel</div>
            <div class="stat-sub">payout_status = PAID</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#ebf8ff">⏳</div>
        <div class="stat-info">
            <div class="stat-value" style="color:#1e73be"><?= number_format($overview['total_ready'],0,',','.') ?>đ</div>
            <div class="stat-label">Sẵn sàng giải ngân</div>
            <div class="stat-sub">Booking đã Completed</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#fff5f5">❄️</div>
        <div class="stat-info">
            <div class="stat-value" style="color:#c53030"><?= number_format($overview['total_frozen'],0,',','.') ?>đ</div>
            <div class="stat-label">Đang đóng băng</div>
            <div class="stat-sub">Đang có dispute</div>
        </div>
    </div>
</div>

<!-- ── Biểu đồ hoa hồng theo tháng ───────────────────────────────── -->
<div class="section-card" style="margin-bottom:24px">
    <div class="section-head">
        <h3>📈 Hoa hồng & GMV theo tháng (6 tháng gần nhất)</h3>
    </div>
    <canvas id="commissionChart" height="90"></canvas>
</div>

<!-- ── Sao kê per hotel ───────────────────────────────────────────── -->
<div class="section-card">
    <div class="section-head">
        <h3>🏨 Sao kê theo khách sạn</h3>
        <a href="payout.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1e73be;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600">
            💸 Trang giải ngân →
        </a>
    </div>

    <div style="overflow-x:auto">
    <table style="min-width:900px">
        <thead>
            <tr>
                <th>Khách sạn</th>
                <th>Hoa hồng (%)</th>
                <th>Trạng thái</th>
                <th>Tổng booking</th>
                <th>GMV</th>
                <th>Hoa hồng thu</th>
                <th>Đã giải ngân</th>
                <th>Sẵn sàng</th>
                <th>Đang giữ</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($hotel_stats)): ?>
            <tr><td colspan="10" style="text-align:center;color:#a0aec0;padding:40px">Chưa có dữ liệu</td></tr>
        <?php else: ?>
            <?php foreach ($hotel_stats as $h):
                $ps = $partner_status_map[$h['partner_status']] ?? ['Không rõ','#718096','#f7fafc'];
            ?>
            <tr>
                <td class="td-name"><?= htmlspecialchars($h['name']) ?></td>
                <td style="text-align:center;font-weight:700;color:#1e73be"><?= number_format($h['commission_rate'],1) ?>%</td>
                <td>
                    <span class="status-badge" style="color:<?= $ps[1] ?>;background:<?= $ps[2] ?>">
                        <?= $ps[0] ?>
                    </span>
                </td>
                <td style="text-align:center"><?= $h['total_bookings'] ?></td>
                <td class="td-price"><?= number_format($h['total_gmv'],0,',','.') ?>đ</td>
                <td style="font-weight:700;color:#276749"><?= number_format($h['total_commission'],0,',','.') ?>đ</td>
                <td style="color:#276749"><?= number_format($h['paid_out'],0,',','.') ?>đ</td>
                <td style="color:#1e73be;font-weight:700">
                    <?= number_format($h['ready'],0,',','.') ?>đ
                    <?php if ($h['ready'] > 0): ?>
                        <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#1e73be;margin-left:4px"></span>
                    <?php endif; ?>
                </td>
                <td style="color:#b7791f"><?= number_format($h['holding'],0,',','.') ?>đ</td>
                <td>
                    <a href="payout.php?hotel_id=<?= $h['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:#ebf8ff;color:#1e73be;border-radius:7px;text-decoration:none;font-size:12.5px;font-weight:600;border:1.5px solid #bee3f8">
                        💸 Giải ngân
                    </a>
                    <a href="manage_hotel.php?id=<?= $h['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:#f8fafc;color:#4a5568;border-radius:7px;text-decoration:none;font-size:12.5px;font-weight:600;border:1.5px solid #e2e8f0;margin-left:4px">
                        ⚙️ Cấu hình
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── Reconciliation ─────────────────────────────────────────────── -->
<div class="section-card" style="margin-bottom:24px">
    <div class="section-head">
        <h3>🔍 Đối soát thu chi (Reconciliation)</h3>
        <span style="font-size:12px;color:#a0aec0">platform_collect · confirmed + completed</span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;padding:4px 0 8px">

        <div style="background:#f8fafc;border-radius:12px;padding:18px 20px;border:1.5px solid #e2e8f0">
            <div style="font-size:11px;color:#718096;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Expected (Booking ghi nhận)</div>
            <div style="font-size:22px;font-weight:800;color:#1a202c"><?= number_format($recon_expected, 0, ',', '.') ?>đ</div>
            <div style="font-size:11.5px;color:#a0aec0;margin-top:4px">Tổng confirmed + completed</div>
        </div>

        <div style="background:#f8fafc;border-radius:12px;padding:18px 20px;border:1.5px solid #e2e8f0">
            <div style="font-size:11px;color:#718096;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Actual (Payments nhận được)</div>
            <div style="font-size:22px;font-weight:800;color:#276749"><?= number_format($recon_actual, 0, ',', '.') ?>đ</div>
            <div style="font-size:11.5px;color:#a0aec0;margin-top:4px">payment_status = paid</div>
        </div>

        <div style="background:<?= $recon_diff > 0 ? '#fff5f5' : '#f0fff4' ?>;border-radius:12px;padding:18px 20px;border:1.5px solid <?= $recon_diff > 0 ? '#fed7d7' : '#c6f6d5' ?>">
            <div style="font-size:11px;color:#718096;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Chênh lệch</div>
            <div style="font-size:22px;font-weight:800;color:<?= $recon_diff > 0 ? '#c53030' : '#276749' ?>">
                <?= $recon_diff > 0 ? '+' : '' ?><?= number_format($recon_diff, 0, ',', '.') ?>đ
            </div>
            <div style="font-size:11.5px;margin-top:4px;font-weight:600;color:<?= $recon_diff > 0 ? '#c53030' : '#276749' ?>">
                <?php if ($recon_diff > abs(1000)): ?>
                    ⚠️ Có booking chưa thu tiền — cần kiểm tra
                <?php elseif ($recon_diff < -1000): ?>
                    ⚠️ Payments vượt booking — cần kiểm tra
                <?php else: ?>
                    ✅ Cân bằng
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php if (abs($recon_diff) > 1000): ?>
    <div style="margin-top:12px;padding:10px 14px;background:<?= $recon_diff > 0 ? '#fffbeb' : '#ebf8ff' ?>;border-radius:8px;font-size:12.5px;color:#4a5568;border-left:3px solid <?= $recon_diff > 0 ? '#f6ad55' : '#63b3ed' ?>">
        <?php if ($recon_diff > 0): ?>
            Có thể do: khách chọn chuyển khoản ngân hàng nhưng chưa thực hiện, webhook VNPay/MoMo chưa xác nhận, hoặc booking được admin xác nhận thủ công chưa có payment.
        <?php else: ?>
            Có thể do: có payment ghi nhận nhưng booking bị xóa, hoặc có payment trùng lặp chưa được deduplicate.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const labels     = <?= $chart_labels ?>;
const commission = <?= $chart_commission ?>;
const gmv        = <?= $chart_gmv ?>;

new Chart(document.getElementById('commissionChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Hoa hồng platform (đ)',
                data: commission,
                backgroundColor: 'rgba(30,115,190,.85)',
                borderRadius: 6,
                order: 1,
            },
            {
                label: 'GMV (đ)',
                data: gmv,
                type: 'line',
                borderColor: '#276749',
                backgroundColor: 'rgba(39,103,73,.1)',
                tension: .4,
                fill: true,
                order: 0,
                pointRadius: 4,
                pointBackgroundColor: '#276749',
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(ctx.raw) + 'đ'
                }
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: v => new Intl.NumberFormat('vi-VN', {notation:'compact'}).format(v) + 'đ'
                }
            }
        }
    }
});
</script>

<?php include "../includes/admin_footer.php"; ?>
