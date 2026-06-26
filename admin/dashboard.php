<?php
include "../config/database.php";

// Safe query helper — trả default nếu query fail (column/table chưa migrate)
function qval(mysqli $db, string $sql, string $col = 't', $default = 0) {
    $r = @$db->query($sql);
    return $r ? ($r->fetch_assoc()[$col] ?? $default) : $default;
}
function qrows(mysqli $db, string $sql): array {
    $r = @$db->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// Thống kê cơ bản (base schema — luôn tồn tại)
$total_users    = qval($conn, "SELECT COUNT(*) as t FROM users");
$total_hotels   = qval($conn, "SELECT COUNT(*) as t FROM hotels WHERE is_active=1");
$total_rooms    = qval($conn, "SELECT COUNT(*) as t FROM rooms");
$total_bookings = qval($conn, "SELECT COUNT(*) as t FROM bookings");
$total_revenue  = qval($conn, "SELECT COALESCE(SUM(total_price),0) as t FROM bookings WHERE status != 'cancelled'");
$pending_count  = qval($conn, "SELECT COUNT(*) as t FROM bookings WHERE status='pending'");
$refund_pending   = (int)qval($conn, "SELECT COUNT(*) as c FROM bookings WHERE refund_requested = 1", 'c');
$support_pending  = (int)qval($conn, "SELECT COUNT(*) as c FROM support_requests WHERE status='pending'", 'c');

// Platform finance metrics (cột từ migration — safe nếu chưa migrate)
$platform_gmv        = (float)qval($conn, "SELECT COALESCE(SUM(total_price),0) as t FROM bookings WHERE status IN ('confirmed','checked_in','completed')");
$platform_commission = (float)qval($conn, "SELECT COALESCE(SUM(platform_revenue),0) as t FROM bookings WHERE status != 'cancelled'");
$payout_ready_amt    = (float)qval($conn, "SELECT COALESCE(SUM(hotel_payout),0) as t FROM bookings WHERE payout_status='READY'");
$payout_frozen_cnt   = (int)qval($conn,   "SELECT COUNT(*) as t FROM bookings WHERE payout_status='FROZEN'");

// Guest vs User bookings
$bookings_by_user  = (int)qval($conn, "SELECT COUNT(*) as t FROM bookings WHERE user_id IS NOT NULL");
$bookings_by_guest = (int)qval($conn, "SELECT COUNT(*) as t FROM bookings WHERE user_id IS NULL");

// Hotel partner alerts (cột partner_status từ migration)
$hotels_pending_approval = (int)qval($conn, "SELECT COUNT(*) as t FROM hotels WHERE partner_status='PENDING'");

// Dispute alerts (bảng disputes từ migration)
$dispute_open = (int)qval($conn, "SELECT COUNT(*) as c FROM disputes WHERE status='OPEN'", 'c');

// New users this month
$new_users_month = (int)qval($conn, "SELECT COUNT(*) as t FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND role='user'");

// Bookings today
$bookings_today = (int)qval($conn, "SELECT COUNT(*) as t FROM bookings WHERE DATE(created_at)=CURDATE()");

// Doanh thu 6 tháng gần nhất
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $m   = date('m', strtotime("-$i months"));
    $y   = date('Y', strtotime("-$i months"));
    $rev = qval($conn, "SELECT COALESCE(SUM(total_price),0) as t FROM bookings WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y AND status != 'cancelled'");
    $cnt = qval($conn, "SELECT COUNT(*) as t FROM bookings WHERE MONTH(created_at)=$m AND YEAR(created_at)=$y");
    $monthly[] = ['label' => "$m/$y", 'revenue' => (int)$rev, 'bookings' => (int)$cnt];
}

// Top 5 khách sạn được đặt nhiều
$top_hotels = qrows($conn, "
    SELECT h.name, COUNT(b.id) as total_bookings, COALESCE(SUM(b.total_price),0) as revenue
    FROM bookings b
    LEFT JOIN rooms r  ON b.room_id  = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE h.name IS NOT NULL AND b.status != 'cancelled'
    GROUP BY h.id ORDER BY total_bookings DESC LIMIT 5
");

// Tỉ lệ trạng thái
$status_stats_raw = qrows($conn, "SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status");

// 5 booking gần nhất
$recent = qrows($conn, "
    SELECT b.order_code, b.full_name, b.total_price, b.status, b.created_at,
        h.name AS hotel_name
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    ORDER BY b.created_at DESC
    LIMIT 5
");

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
include "../includes/admin_header.php";
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
                <a href="/admin/support_requests.php"
                   style="color:#7c3aed;font-weight:600;text-decoration:none">
                    Xem tất cả →
                </a>
            </div>
        </div>
    </div>

</div><!-- /.stat-grid -->

<!-- Platform Finance Summary -->
<div class="charts-row" style="margin-bottom:20px">
    <div class="chart-card" style="flex:2">
        <div class="chart-head"><h3>💰 Tài chính Platform</h3><a href="/admin/finance.php" style="font-size:13px;color:#1e73be;text-decoration:none">Xem chi tiết →</a></div>
        <div class="chart-body" style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;padding:4px 0">
            <div style="background:#f0fff4;border-radius:12px;padding:16px">
                <div style="font-size:11.5px;color:#276749;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">GMV (Đang hoạt động)</div>
                <div style="font-size:22px;font-weight:800;color:#276749"><?= number_format($platform_gmv,0,',','.') ?>đ</div>
                <div style="font-size:11.5px;color:#68d391;margin-top:3px">Tổng giá trị đơn xác nhận+hoàn thành</div>
            </div>
            <div style="background:#ebf8ff;border-radius:12px;padding:16px">
                <div style="font-size:11.5px;color:#1e73be;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Hoa hồng đã thu</div>
                <div style="font-size:22px;font-weight:800;color:#1e73be"><?= number_format($platform_commission,0,',','.') ?>đ</div>
                <div style="font-size:11.5px;color:#63b3ed;margin-top:3px">Doanh thu platform (commission)</div>
            </div>
            <div style="background:#fffbeb;border-radius:12px;padding:16px">
                <div style="font-size:11.5px;color:#b7791f;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Chờ giải ngân</div>
                <div style="font-size:22px;font-weight:800;color:#b7791f"><?= number_format($payout_ready_amt,0,',','.') ?>đ</div>
                <div style="font-size:11.5px;color:#f6ad55;margin-top:3px"><a href="/admin/payout.php" style="color:inherit;text-decoration:none">Giải ngân ngay →</a></div>
            </div>
            <div style="background:<?= $payout_frozen_cnt>0?'#fff5f5':'#f7fafc' ?>;border-radius:12px;padding:16px">
                <div style="font-size:11.5px;color:<?= $payout_frozen_cnt>0?'#c53030':'#a0aec0' ?>;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Đóng băng (dispute)</div>
                <div style="font-size:22px;font-weight:800;color:<?= $payout_frozen_cnt>0?'#c53030':'#718096' ?>"><?= $payout_frozen_cnt ?> đơn</div>
                <div style="font-size:11.5px;color:<?= $payout_frozen_cnt>0?'#fc8181':'#a0aec0' ?>;margin-top:3px"><?= $payout_frozen_cnt>0?'Cần xử lý khiếu nại':'Không có tranh chấp' ?></div>
            </div>
        </div>
    </div>

    <div class="chart-card" style="flex:1;min-width:240px">
        <div class="chart-head"><h3>👥 Guest vs User</h3><span>Tỉ lệ đặt phòng</span></div>
        <div class="chart-body" style="display:flex;flex-direction:column;gap:14px;justify-content:center">
            <?php
            $total_b = $bookings_by_user + $bookings_by_guest;
            $user_pct  = $total_b > 0 ? round($bookings_by_user  / $total_b * 100) : 0;
            $guest_pct = $total_b > 0 ? round($bookings_by_guest / $total_b * 100) : 0;
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:5px">
                    <span style="color:#1e73be">User (đã đăng nhập)</span>
                    <span><?= $bookings_by_user ?> (<?= $user_pct ?>%)</span>
                </div>
                <div style="height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden">
                    <div style="height:100%;width:<?= $user_pct ?>%;background:linear-gradient(90deg,#1e73be,#2d9cdb);border-radius:5px"></div>
                </div>
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:5px">
                    <span style="color:#744210">Guest (không đăng nhập)</span>
                    <span><?= $bookings_by_guest ?> (<?= $guest_pct ?>%)</span>
                </div>
                <div style="height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden">
                    <div style="height:100%;width:<?= $guest_pct ?>%;background:linear-gradient(90deg,#744210,#b7791f);border-radius:5px"></div>
                </div>
            </div>
            <div style="margin-top:6px;padding-top:14px;border-top:1px solid #f0f4f8">
                <div style="display:flex;justify-content:space-between;font-size:13px">
                    <span style="color:#718096">Đặt phòng hôm nay</span>
                    <strong style="color:#1a202c"><?= $bookings_today ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px">
                    <span style="color:#718096">User mới tháng này</span>
                    <strong style="color:#276749">+<?= $new_users_month ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Alerts -->
<?php
$alerts = [];
if ($pending_count > 0)             $alerts[] = ['🕐', "$pending_count đơn đặt phòng đang chờ xác nhận.", 'bookings.php?status=pending', '#b7791f', '#fffbeb'];
if ($refund_pending > 0)            $alerts[] = ['💸', "$refund_pending yêu cầu hoàn tiền chưa xử lý.", 'refund_requests.php', '#c53030', '#fff5f5'];
if ($dispute_open > 0)              $alerts[] = ['⚖️', "$dispute_open khiếu nại mới đang chờ xử lý.", 'disputes.php?status=OPEN', '#c53030', '#fff5f5'];
if ($hotels_pending_approval > 0)   $alerts[] = ['🏨', "$hotels_pending_approval khách sạn đối tác đang chờ phê duyệt.", 'hotels.php?filter=PENDING', '#1e73be', '#ebf8ff'];
if ($payout_frozen_cnt > 0)         $alerts[] = ['🔒', "$payout_frozen_cnt đơn giải ngân đang bị đóng băng do tranh chấp.", 'disputes.php', '#553c9a', '#faf5ff'];
if ($support_pending > 0)           $alerts[] = ['📩', "$support_pending yêu cầu hỗ trợ chưa trả lời.", 'support_requests.php', '#276749', '#f0fff4'];
?>
<?php if (!empty($alerts)): ?>
<div class="section-card" style="margin-bottom:20px">
    <div class="section-head"><h3>🚨 Cảnh báo hệ thống</h3><span style="font-size:12.5px;color:#a0aec0"><?= count($alerts) ?> mục cần chú ý</span></div>
    <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
        <?php foreach ($alerts as [$icon, $msg, $url, $color, $bg]): ?>
        <a href="<?= $url ?>" style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:<?= $bg ?>;border-radius:10px;text-decoration:none;transition:opacity .15s" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <span style="font-size:18px"><?= $icon ?></span>
            <span style="font-size:13.5px;font-weight:600;color:<?= $color ?>"><?= $msg ?></span>
            <span style="margin-left:auto;font-size:12px;color:<?= $color ?>;opacity:.7">Xem →</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

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

<?php include "../includes/admin_footer.php"; ?>