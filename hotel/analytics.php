<?php
$page_title    = 'Phân tích hiệu suất';
$page_subtitle = 'Occupancy · ADR · RevPAR · Lead time · Doanh thu theo phòng';
require_once __DIR__ . '/../includes/hotel_header.php';

// ── Kỳ phân tích ──────────────────────────────────────────────────────────────
$period   = $_GET['period'] ?? 'month';   // month | quarter | year
$validP   = ['month', 'quarter', 'year'];
if (!in_array($period, $validP)) $period = 'month';

switch ($period) {
    case 'quarter':
        $dateFrom    = date('Y-m-01', strtotime('-2 months'));
        $periodLabel = '3 tháng gần nhất';
        $days        = 90;
        break;
    case 'year':
        $dateFrom    = date('Y-01-01');
        $periodLabel = 'Năm ' . date('Y');
        $days        = (int)date('z') + 1;
        break;
    default:
        $dateFrom    = date('Y-m-01');
        $periodLabel = 'Tháng ' . date('m/Y');
        $days        = (int)date('j');
}
$dateFromSafe = $conn->real_escape_string($dateFrom);

// ── Helper ───────────────────────────────────────────────────────────────────
function aqv(mysqli $db, string $sql, string $col = 'v', $def = 0) {
    $r = @$db->query($sql);
    return $r ? ($r->fetch_assoc()[$col] ?? $def) : $def;
}
function aqrows(mysqli $db, string $sql): array {
    $r = @$db->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function afmt(float $n): string { return number_format($n, 0, ',', '.'); }
function apct(float $v, bool $showSign = true): string {
    $s = $showSign && $v > 0 ? '+' : '';
    return $s . round($v, 1) . '%';
}

// ── Tổng số phòng ────────────────────────────────────────────────────────────
$totalRooms = (int)aqv($conn, "SELECT COUNT(*) v FROM rooms WHERE hotel_id=$hotel_id AND is_available=1");

// ── KPI chính ────────────────────────────────────────────────────────────────
$kpi = $conn->query("
    SELECT
        COUNT(b.id)                                                 AS bookings,
        SUM(DATEDIFF(b.check_out, b.check_in))                     AS nights_sold,
        COALESCE(SUM(b.total_price), 0)                            AS revenue,
        COALESCE(SUM(b.hotel_payout), 0)                           AS net_payout,
        SUM(b.status = 'cancelled')                                AS cancelled,
        AVG(DATEDIFF(b.check_in, b.created_at))                    AS avg_lead_time
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
      AND b.created_at >= '$dateFromSafe'
      AND b.status != 'pending'
")->fetch_assoc();

$nightsSold   = (int)($kpi['nights_sold'] ?? 0);
$revenue      = (float)($kpi['revenue'] ?? 0);
$bookings     = (int)($kpi['bookings'] ?? 0);
$cancelled    = (int)($kpi['cancelled'] ?? 0);
$leadTime     = round((float)($kpi['avg_lead_time'] ?? 0), 1);
$availNights  = $totalRooms * $days;
$occupancy    = $availNights > 0 ? round($nightsSold / $availNights * 100, 1) : 0;
$adr          = $nightsSold  > 0 ? round($revenue / $nightsSold) : 0;
$revpar       = $availNights > 0 ? round($revenue / $availNights) : 0;
$cancelRate   = $bookings    > 0 ? round($cancelled / $bookings * 100, 1) : 0;

// ── KPI tháng/kỳ trước (để so sánh) ─────────────────────────────────────────
$prevFrom = match($period) {
    'quarter' => date('Y-m-01', strtotime('-5 months')),
    'year'    => date('Y-01-01', strtotime('-1 year')),
    default   => date('Y-m-01', strtotime('-1 month')),
};
$prevTo   = match($period) {
    'quarter' => date('Y-m-t', strtotime('-3 months')) . ' 23:59:59',
    'year'    => date('Y-12-31', strtotime('-1 year')) . ' 23:59:59',
    default   => date('Y-m-t', strtotime('-1 month'))  . ' 23:59:59',
};
$prevFromSafe = $conn->real_escape_string($prevFrom);
$prevToSafe   = $conn->real_escape_string($prevTo);
$prevDays     = match($period) {
    'quarter' => 90,
    'year'    => 365,
    default   => (int)date('t', strtotime('-1 month')),
};

$prev = $conn->query("
    SELECT
        COALESCE(SUM(b.total_price), 0)               AS revenue,
        COALESCE(SUM(DATEDIFF(b.check_out,b.check_in)),0) AS nights_sold
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id
      AND b.created_at BETWEEN '$prevFromSafe' AND '$prevToSafe'
      AND b.status NOT IN ('pending','cancelled')
")->fetch_assoc();
$prevRevenue = (float)($prev['revenue'] ?? 0);
$prevNights  = (int)($prev['nights_sold'] ?? 0);
$prevAvail   = $totalRooms * $prevDays;
$prevOcc     = $prevAvail  > 0 ? round($prevNights / $prevAvail * 100, 1) : 0;
$prevAdr     = $prevNights > 0 ? round($prevRevenue / $prevNights) : 0;
$prevRevpar  = $prevAvail  > 0 ? round($prevRevenue / $prevAvail) : 0;
$revChange   = $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100, 1) : 0;
$occChange   = $prevOcc > 0     ? round(($occupancy - $prevOcc) / $prevOcc * 100, 1)   : 0;
$adrChange   = $prevAdr > 0     ? round(($adr - $prevAdr) / $prevAdr * 100, 1)         : 0;

// ── Doanh thu theo loại phòng ────────────────────────────────────────────────
$byRoom = aqrows($conn, "
    SELECT r.room_name, r.price AS base_price,
           COUNT(b.id)                                       AS bk_cnt,
           COALESCE(SUM(DATEDIFF(b.check_out,b.check_in)),0) AS nights,
           COALESCE(SUM(b.total_price),0)                    AS revenue
    FROM rooms r
    LEFT JOIN bookings b ON b.room_id = r.id
        AND b.status NOT IN ('pending','cancelled')
        AND b.created_at >= '$dateFromSafe'
    WHERE r.hotel_id = $hotel_id AND r.is_available = 1
    GROUP BY r.id
    ORDER BY revenue DESC
");

// ── Doanh thu 6 tháng (bar chart data) ───────────────────────────────────────
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m   = date('Y-m', strtotime("-$i months"));
    $lbl = date('m/Y', strtotime("-$i months"));
    $rev = (float)aqv($conn, "SELECT COALESCE(SUM(b.total_price),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id AND DATE_FORMAT(b.created_at,'%Y-%m')='$m' AND b.status NOT IN ('pending','cancelled')");
    $nts = (int)aqv($conn,   "SELECT COALESCE(SUM(DATEDIFF(b.check_out,b.check_in)),0) v FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id AND DATE_FORMAT(b.created_at,'%Y-%m')='$m' AND b.status NOT IN ('pending','cancelled')");
    $dIM = (int)date('t', strtotime("-$i months"));
    $occ = $totalRooms > 0 ? round($nts / ($totalRooms * $dIM) * 100, 1) : 0;
    $monthly[] = ['label' => $lbl, 'revenue' => $rev, 'occupancy' => $occ];
}
$maxRev = max(array_column($monthly, 'revenue') ?: [1]);

// ── Lead time phân nhóm ──────────────────────────────────────────────────────
$leadGroups = [
    'Cùng ngày (0 ngày)'     => [0, 0],
    'Đặt 1-3 ngày trước'     => [1, 3],
    'Đặt 4-7 ngày trước'     => [4, 7],
    'Đặt 8-30 ngày trước'    => [8, 30],
    'Đặt >30 ngày trước'     => [31, 9999],
];
$leadData = [];
foreach ($leadGroups as $label => [$min, $max]) {
    $cnt = (int)aqv($conn, "
        SELECT COUNT(*) v FROM bookings b JOIN rooms r ON b.room_id=r.id
        WHERE r.hotel_id=$hotel_id AND b.created_at >= '$dateFromSafe'
          AND b.status NOT IN ('pending','cancelled')
          AND DATEDIFF(b.check_in, b.created_at) BETWEEN $min AND $max
    ");
    $leadData[] = ['label' => $label, 'cnt' => $cnt];
}
$maxLead = max(array_column($leadData, 'cnt') ?: [1]);

// ── Đánh giá tóm tắt ─────────────────────────────────────────────────────────
$rvStats = $conn->query("SELECT ROUND(AVG(rating),2) avg_r, COUNT(*) total FROM reviews WHERE hotel_id=$hotel_id AND is_active=1")->fetch_assoc();
?>

<!-- ── Period selector ──────────────────────────────────────────────────────── -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <span style="font-size:13px;font-weight:600;color:#4a5568">Kỳ xem:</span>
    <?php foreach (['month'=>'Tháng này','quarter'=>'3 tháng','year'=>'Năm nay'] as $k => $lbl): ?>
    <a href="?period=<?= $k ?>"
       class="btn btn-sm <?= $period === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
    <span style="margin-left:auto;font-size:13px;color:#a0aec0"><?= $periodLabel ?> · <?= $totalRooms ?> phòng</span>
</div>

<!-- ── KPI cards ─────────────────────────────────────────────────────────────── -->
<div class="stat-grid" style="margin-bottom:20px">
    <?php
    function kpiCard(string $title, string $value, string $unit, float $change, string $prevLabel = ''): void {
        $color  = $change > 0 ? '#276749' : ($change < 0 ? '#c53030' : '#718096');
        $icon   = $change > 0 ? '↑' : ($change < 0 ? '↓' : '→');
        $chgStr = ($change > 0 ? '+' : '') . round($change, 1) . '%';
    ?>
    <div class="stat-card">
        <div style="font-size:12px;font-weight:600;color:#718096;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px"><?= $title ?></div>
        <div style="display:flex;align-items:baseline;gap:4px">
            <span style="font-size:26px;font-weight:800;color:#1a365d;line-height:1"><?= $value ?></span>
            <span style="font-size:13px;color:#718096"><?= $unit ?></span>
        </div>
        <?php if ($prevLabel): ?>
        <div style="margin-top:4px;font-size:12px;color:<?= $color ?>;font-weight:600">
            <?= $icon ?> <?= $chgStr ?> <span style="color:#a0aec0;font-weight:400">vs <?= $prevLabel ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php } ?>

    <?php kpiCard('Occupancy Rate', $occupancy, '%', $occChange, 'kỳ trước'); ?>
    <?php kpiCard('ADR', afmt($adr), 'đ/đêm', $adrChange, 'kỳ trước'); ?>
    <?php kpiCard('RevPAR', afmt($revpar), 'đ', $revChange > 0 ? abs($adrChange) : -abs($adrChange), 'kỳ trước'); ?>
    <?php kpiCard('Doanh thu', afmt($revenue), 'đ', $revChange, 'kỳ trước'); ?>
    <?php kpiCard('Đặt phòng', $bookings, 'booking', 0); ?>

    <div class="stat-card">
        <div style="font-size:12px;font-weight:600;color:#718096;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Cancel Rate</div>
        <div style="display:flex;align-items:baseline;gap:4px">
            <span style="font-size:26px;font-weight:800;color:<?= $cancelRate > 15 ? '#c53030' : '#1a365d' ?>;line-height:1"><?= $cancelRate ?></span>
            <span style="font-size:13px;color:#718096">%</span>
        </div>
        <div style="margin-top:4px;font-size:12px;color:<?= $cancelRate > 15 ? '#c53030' : '#276749' ?>">
            <?= $cancelRate > 15 ? '⚠️ Vượt ngưỡng 15%' : '✓ Trong ngưỡng an toàn' ?>
        </div>
    </div>

    <div class="stat-card">
        <div style="font-size:12px;font-weight:600;color:#718096;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Đặt trước TB</div>
        <div style="display:flex;align-items:baseline;gap:4px">
            <span style="font-size:26px;font-weight:800;color:#1a365d;line-height:1"><?= $leadTime ?></span>
            <span style="font-size:13px;color:#718096">ngày</span>
        </div>
        <div style="margin-top:4px;font-size:12px;color:#718096">Lead time trung bình</div>
    </div>

    <div class="stat-card">
        <div style="font-size:12px;font-weight:600;color:#718096;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Đánh giá TB</div>
        <div style="display:flex;align-items:baseline;gap:4px">
            <span style="font-size:26px;font-weight:800;color:<?= ($rvStats['avg_r'] ?? 0) >= 4.2 ? '#276749' : '#c53030' ?>;line-height:1"><?= number_format((float)($rvStats['avg_r'] ?? 0), 1) ?></span>
            <span style="font-size:13px;color:#718096">/ 5★</span>
        </div>
        <div style="margin-top:4px;font-size:12px;color:#a0aec0"><?= (int)($rvStats['total'] ?? 0) ?> đánh giá · Mục tiêu ≥4.2</div>
    </div>
</div>

<!-- ── Revenue chart + Lead time chart ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">

    <!-- Revenue 6 months -->
    <div class="card">
        <div class="card-header"><span class="card-title">📈 Doanh thu 6 tháng gần nhất</span></div>
        <div style="padding:20px">
            <div style="display:flex;align-items:flex-end;gap:10px;height:140px">
                <?php foreach ($monthly as $m):
                    $barH    = $maxRev > 0 ? round($m['revenue'] / $maxRev * 130) : 0;
                    $isCur   = $m['label'] === date('m/Y');
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="font-size:10px;color:#718096;font-weight:600"><?= $m['occupancy'] ?>%</div>
                    <div style="width:100%;background:<?= $isCur ? 'linear-gradient(180deg,#3182ce,#63b3ed)' : '#bee3f8' ?>;border-radius:6px 6px 0 0;height:<?= max($barH, 4) ?>px;transition:.3s"
                         title="<?= afmt($m['revenue']) ?>đ"></div>
                    <div style="font-size:10px;color:#718096;white-space:nowrap"><?= $m['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;justify-content:center;gap:16px;margin-top:10px;font-size:11.5px;color:#718096">
                <span><span style="display:inline-block;width:12px;height:12px;background:#3182ce;border-radius:2px;vertical-align:middle;margin-right:4px"></span>Tháng hiện tại</span>
                <span><span style="display:inline-block;width:12px;height:12px;background:#bee3f8;border-radius:2px;vertical-align:middle;margin-right:4px"></span>Tháng trước</span>
            </div>
        </div>
    </div>

    <!-- Lead time distribution -->
    <div class="card">
        <div class="card-header"><span class="card-title">⏱️ Phân tích Lead Time</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:10px">
            <?php foreach ($leadData as $ld):
                $pct = $maxLead > 0 ? round($ld['cnt'] / $maxLead * 100) : 0;
                $pctOfTotal = $bookings > 0 ? round($ld['cnt'] / $bookings * 100, 0) : 0;
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
                    <span style="color:#4a5568;font-weight:500"><?= $ld['label'] ?></span>
                    <span style="color:#718096"><?= $ld['cnt'] ?> đặt phòng <span style="color:#a0aec0">(<?= $pctOfTotal ?>%)</span></span>
                </div>
                <div style="background:#ebf8ff;border-radius:6px;height:8px;overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,#3182ce,#63b3ed);border-radius:6px;transition:.4s"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- ── Room breakdown ─────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">🛏️ Hiệu suất theo loại phòng — <?= $periodLabel ?></span>
    </div>
    <div class="card-body">
        <table>
            <thead>
                <tr>
                    <th>Loại phòng</th>
                    <th>Giá cơ bản</th>
                    <th>Đặt phòng</th>
                    <th>Đêm bán</th>
                    <th>Occupancy</th>
                    <th>ADR</th>
                    <th>Doanh thu</th>
                    <th>Tỷ trọng</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byRoom as $rm):
                $rmOcc = $days > 0 ? round($rm['nights'] / $days * 100, 0) : 0;
                $rmAdr = $rm['nights'] > 0 ? round($rm['revenue'] / $rm['nights']) : 0;
                $rmShare = $revenue > 0 ? round($rm['revenue'] / $revenue * 100, 0) : 0;
                $occColor = $rmOcc >= 70 ? '#276749' : ($rmOcc >= 50 ? '#92400e' : '#c53030');
            ?>
            <tr>
                <td style="font-weight:600;color:#1a365d"><?= htmlspecialchars($rm['room_name']) ?></td>
                <td><?= afmt((float)$rm['base_price']) ?>đ</td>
                <td><?= (int)$rm['bk_cnt'] ?></td>
                <td><?= (int)$rm['nights'] ?></td>
                <td>
                    <span style="font-weight:700;color:<?= $occColor ?>"><?= $rmOcc ?>%</span>
                    <div style="width:60px;background:#edf2f7;border-radius:4px;height:4px;margin-top:3px">
                        <div style="width:<?= min($rmOcc, 100) ?>%;height:100%;background:<?= $occColor ?>;border-radius:4px"></div>
                    </div>
                </td>
                <td><?= afmt($rmAdr) ?>đ</td>
                <td style="font-weight:700;color:#1a365d"><?= afmt((float)$rm['revenue']) ?>đ</td>
                <td>
                    <div style="display:flex;align-items:center;gap:6px">
                        <div style="width:50px;background:#edf2f7;border-radius:4px;height:6px;overflow:hidden">
                            <div style="width:<?= $rmShare ?>%;height:100%;background:#3182ce;border-radius:4px"></div>
                        </div>
                        <span style="font-size:12px;color:#718096"><?= $rmShare ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($byRoom)): ?>
            <tr><td colspan="8" style="text-align:center;color:#a0aec0;padding:40px">Chưa có dữ liệu</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Occupancy benchmark ───────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header"><span class="card-title">🎯 Đánh giá chỉ số so với mục tiêu</span></div>
    <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
        <?php
        $benchmarks = [
            ['Occupancy Rate', $occupancy, 70, '%', $occupancy >= 70 ? 'Đạt mục tiêu' : 'Dưới mục tiêu 70%'],
            ['Cancel Rate',    $cancelRate, 15,  '%', $cancelRate <= 15 ? 'Trong ngưỡng' : 'Cần cải thiện', true],
            ['Điểm đánh giá',  (float)($rvStats['avg_r'] ?? 0), 4.2, '/5', ($rvStats['avg_r'] ?? 0) >= 4.2 ? 'Đạt mục tiêu' : 'Cần tăng điểm', false, true],
        ];
        foreach ($benchmarks as $bm):
            [$title, $val, $target, $unit, $note] = $bm;
            $invertOk = $bm[5] ?? false;
            $isFloat  = $bm[6] ?? false;
            $ok    = $invertOk ? $val <= $target : $val >= $target;
            $color = $ok ? '#276749' : '#c53030';
            $bg    = $ok ? '#f0fff4' : '#fff5f5';
            $icon  = $ok ? '✅' : '⚠️';
            $displayVal = $isFloat ? number_format($val, 1) : $val;
        ?>
        <div style="background:<?= $bg ?>;border-radius:12px;padding:16px;text-align:center">
            <div style="font-size:13px;color:#4a5568;font-weight:600;margin-bottom:8px"><?= $title ?></div>
            <div style="font-size:30px;font-weight:800;color:<?= $color ?>"><?= $displayVal ?><span style="font-size:14px;font-weight:400"><?= $unit ?></span></div>
            <div style="font-size:12px;color:#718096;margin:4px 0">Mục tiêu: <?= $isFloat ? number_format($target, 1) : $target ?><?= $unit ?></div>
            <div style="font-size:12.5px;font-weight:600;color:<?= $color ?>"><?= $icon ?> <?= $note ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
