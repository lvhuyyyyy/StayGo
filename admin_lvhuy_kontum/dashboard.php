<?php
include("../config/database.php");

// Thống kê
$total_users    = $conn->query("SELECT COUNT(*) as t FROM users")->fetch_assoc()['t'];
$total_hotels   = $conn->query("SELECT COUNT(*) as t FROM hotels WHERE is_active=1")->fetch_assoc()['t'];
$total_rooms    = $conn->query("SELECT COUNT(*) as t FROM rooms")->fetch_assoc()['t'];
$total_bookings = $conn->query("SELECT COUNT(*) as t FROM bookings")->fetch_assoc()['t'];
$total_revenue  = $conn->query("SELECT COALESCE(SUM(total_price),0) as t FROM bookings WHERE status != 'cancelled'")->fetch_assoc()['t'];
$pending_count  = $conn->query("SELECT COUNT(*) as t FROM bookings WHERE status='pending'")->fetch_assoc()['t'];
$refund_pending   = (int)$conn->query("SELECT COUNT(*) as c FROM bookings WHERE refund_requested = 1")->fetch_assoc()['c'];
$support_pending  = (int)$conn->query("SELECT COUNT(*) as c FROM support_requests WHERE status='pending'")->fetch_assoc()['c'];

// Doanh thu 6 tháng gần nhất
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m   = date('m', strtotime("-$i months"));
    $y   = date('Y', strtotime("-$i months"));
    $rev = $conn->query("SELECT COALESCE(SUM(total_price),0) as t FROM bookings
                        WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y AND status != 'cancelled'")->fetch_assoc()['t'];
    $cnt = $conn->query("SELECT COUNT(*) as t FROM bookings
                        WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y")->fetch_assoc()['t'];
    $monthly[] = ['label' => "$m/$y", 'revenue' => (int)$rev, 'bookings' => (int)$cnt];
}

// Top 5 khách sạn được đặt nhiều
$top_hotels = $conn->query("
    SELECT h.name, COUNT(b.id) as total_bookings, COALESCE(SUM(b.total_price),0) as revenue
    FROM bookings b
    LEFT JOIN rooms r  ON b.room_id  = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE h.name IS NOT NULL AND b.status != 'cancelled'
    GROUP BY h.id ORDER BY total_bookings DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Tỉ lệ trạng thái
$status_stats_raw = $conn->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status")->fetch_all(MYSQLI_ASSOC);

// 5 booking gần nhất
$recent = $conn->query("
    SELECT b.order_code, b.full_name, b.total_price, b.status, b.created_at,
        h.name AS hotel_name
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    ORDER BY b.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$status_map = [
    'pending'   => ['Chờ xác nhận', '#b7791f', '#fffbeb'],
    'confirmed' => ['Đã xác nhận',  '#1e73be', '#ebf8ff'],
    'cancelled' => ['Đã huỷ',       '#c53030', '#fff5f5'],
    'completed' => ['Hoàn thành',   '#276749', '#f0fff4'],
];

// JSON cho Charts
$chart_labels   = json_encode(array_column($monthly, 'label'));
$chart_revenue  = json_encode(array_column($monthly, 'revenue'));
$chart_bookings = json_encode(array_column($monthly, 'bookings'));
$hotel_names    = json_encode(array_column($top_hotels, 'name'));
$hotel_counts   = json_encode(array_column($top_hotels, 'total_bookings'));

$donut_labels = []; $donut_data = []; $donut_colors = [];
$sc = ['pending'=>'#f59e0b','confirmed'=>'#1e73be','completed'=>'#10b981','cancelled'=>'#ef4444'];
foreach ($status_stats_raw as $ss) {
    $donut_labels[] = $status_map[$ss['status']][0] ?? $ss['status'];
    $donut_data[]   = (int)$ss['cnt'];
    $donut_colors[] = $sc[$ss['status']] ?? '#a0aec0';
}

// -- Dùng admin_header.php để dùng bộ sidebar với toàn bộ admin --
$page_title    = 'Dashboard';
$page_subtitle = 'Chào mừng trở lại · ' . date('d/m/Y');
include("../includes/admin_header.php");
?>

<link rel="stylesheet" href="/assets/css/dashboard.css">

<!-- Stat cards -->
<div class="stat-grid">

    <div class="stat-card revenue">
        <div class="stat-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9"/>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($total_revenue, 0, ',', '.') ?>đ</div>
            <div class="stat-label">Tổng doanh thu</div>
            <div class="stat-sub">Từ các booking không bị huỷ</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#ebf8ff">
            <svg fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $total_users ?></div>
            <div class="stat-label">Người dùng</div>
            <div class="stat-sub">Tổng tài khoản</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fff4">
            <svg fill="none" viewBox="0 0 24 24" stroke="#276749" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $total_hotels ?></div>
            <div class="stat-label">Khách sạn</div>
            <div class="stat-sub">Đang hoạt động</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#fffbeb">
            <svg fill="none" viewBox="0 0 24 24" stroke="#b7791f" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $total_rooms ?></div>
            <div class="stat-label">Phòng</div>
            <div class="stat-sub">Tổng loại phòng</div>
        </div>
    </div>

    <div class="stat-card" style="grid-column: span 2">
        <div class="stat-icon" style="background:#fff5f5">
            <svg fill="none" viewBox="0 0 24 24" stroke="#c53030" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $total_bookings ?></div>
            <div class="stat-label">Tổng đặt phòng</div>
            <div class="stat-sub">
                <?php if($pending_count > 0): ?>
                    <span style="color:#e05c1a;font-weight:600"><?= $pending_count ?> đơn đang chờ xử lý</span>
                <?php else: ?>
                    Không có đơn chờ
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#faf5ff">
            <svg fill="none" viewBox="0 0 24 24" stroke="#7c3aed" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $support_pending ?></div>
            <div class="stat-label">Hỗ trợ chờ xử lý</div>
            <div class="stat-sub">
                <a href="/admin_lvhuy_kontum/support_requests.php"
                   style="color:#7c3aed;font-weight:600;text-decoration:none">
                    Xem tất cả →
                </a>
            </div>
        </div>
    </div>

</div><!-- /.stat-grid -->

<!-- Biểu đồ: Hàng 1 — Line chart + Donut -->
<div class="charts-row">
    <div class="chart-card">
        <div class="chart-head">
            <h3>📈 Doanh thu & Số đơn theo tháng</h3>
            <span>6 tháng gần nhất</span>
        </div>
        <div class="chart-body">
            <canvas id="lineChart" height="115"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-head">
            <h3>🥧 Tỉ lệ trạng thái đơn</h3>
            <span>Tất cả thời gian</span>
        </div>
        <div class="chart-body" style="display:flex;align-items:center;justify-content:center">
            <canvas id="donutChart" style="max-width:200px;max-height:200px"></canvas>
        </div>
    </div>
</div>

<!-- Biểu đồ: Hàng 2 — Bar chart + Bảng xếp hạng -->
<div class="charts-row2">
    <div class="chart-card">
        <div class="chart-head">
            <h3>🏨 Top khách sạn được đặt nhiều</h3>
            <span>Theo số lượt đặt</span>
        </div>
        <div class="chart-body">
            <canvas id="barChart" height="170"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-head">
            <h3>🏆 Bảng xếp hạng doanh thu</h3>
            <span>Top 5 khách sạn</span>
        </div>
        <div class="chart-body">
            <?php if(empty($top_hotels)): ?>
                <p style="color:#a0aec0;text-align:center;padding:30px 0">Chưa có dữ liệu booking</p>
            <?php else: ?>
                <?php foreach($top_hotels as $i => $th): ?>
                <div class="top-hotel-item">
                    <div class="top-rank rank-<?= $i+1 ?>"><?= $i+1 ?></div>
                    <div class="top-hotel-name" title="<?= htmlspecialchars($th['name']) ?>">
                        <?= htmlspecialchars(mb_substr($th['name'], 0, 28)) ?>
                    </div>
                    <div style="text-align:right">
                        <div class="top-hotel-cnt"><?= $th['total_bookings'] ?> đơn</div>
                        <div style="font-size:11px;color:#a0aec0;margin-top:1px"><?= number_format($th['revenue'],0,',','.') ?>đ</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent bookings -->
<div class="section-card">
    <div class="section-head">
        <h3>📋 Đặt phòng gần đây</h3>
        <a href="bookings.php">Xem tất cả →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Mã đơn</th>
                <th>Khách hàng</th>
                <th>Khách sạn</th>
                <th>Tổng tiền</th>
                <th>Ngày đặt</th>
                <th>Trạng thái</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($recent)): ?>
            <tr><td colspan="6" style="text-align:center;color:#a0aec0;padding:30px">Chưa có đặt phòng nào</td></tr>
        <?php else: ?>
            <?php foreach($recent as $b):
                $sm = $status_map[$b['status']] ?? ['Không rõ', '#718096', '#f7fafc'];
            ?>
            <tr>
                <td class="td-order"><?= htmlspecialchars($b['order_code']) ?></td>
                <td class="td-name"><?= htmlspecialchars($b['full_name']) ?></td>
                <td style="color:#718096"><?= htmlspecialchars($b['hotel_name'] ?? '—') ?></td>
                <td class="td-price"><?= number_format($b['total_price'],0,',','.') ?>đ</td>
                <td style="color:#a0aec0;font-size:12.5px"><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></td>
                <td>
                    <span class="status-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>">
                        <?= $sm[0] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Truyền dữ liệu PHP vào JS -->
<script>
    const chartLabels   = <?= $chart_labels ?>;
    const chartRevenue  = <?= $chart_revenue ?>;
    const chartBookings = <?= $chart_bookings ?>;
    const hotelNames    = <?= $hotel_names ?>;
    const hotelCounts   = <?= $hotel_counts ?>;
    const donutLabels   = <?= json_encode($donut_labels) ?>;
    const donutData     = <?= json_encode($donut_data) ?>;
    const donutColors   = <?= json_encode($donut_colors) ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<?php include("../includes/admin_footer.php"); ?>