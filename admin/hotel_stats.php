<?php
include "../config/database.php";

$hotel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---- Danh sách khách sạn để chọn ----
$hotels_list = $conn->query("SELECT id, name FROM hotels ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$hotel = null;
$stats = [];

if ($hotel_id) {
    $hotel = $conn->query("SELECT * FROM hotels WHERE id = $hotel_id")->fetch_assoc();
    if (!$hotel) $hotel_id = 0;
}

if ($hotel_id) {
    // ---- Tổng quan ----
    $stats['total_bookings']   = $conn->query("SELECT COUNT(*) as c FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id")->fetch_assoc()['c'];
    $stats['total_revenue']    = $conn->query("SELECT COALESCE(SUM(b.total_price),0) as t FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id AND b.status != 'cancelled'")->fetch_assoc()['t'];
    $stats['pending_count']    = $conn->query("SELECT COUNT(*) as c FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id AND b.status='pending'")->fetch_assoc()['c'];
    $stats['cancelled_count']  = $conn->query("SELECT COUNT(*) as c FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id AND b.status='cancelled'")->fetch_assoc()['c'];
    $stats['completed_count']  = $conn->query("SELECT COUNT(*) as c FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE r.hotel_id=$hotel_id AND b.status='completed'")->fetch_assoc()['c'];
    $stats['total_reviews']    = $conn->query("SELECT COUNT(*) as c FROM reviews WHERE hotel_id=$hotel_id AND is_active=1")->fetch_assoc()['c'];
    $stats['avg_rating']       = $conn->query("SELECT ROUND(AVG(rating),1) as r FROM reviews WHERE hotel_id=$hotel_id AND is_active=1")->fetch_assoc()['r'];
    $stats['total_rooms']      = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE hotel_id=$hotel_id")->fetch_assoc()['c'];

    // ---- Doanh thu 6 tháng ----
    $monthly = [];
    for ($i = 5; $i >= 0; $i--) {
        $m   = date('m', strtotime("-$i months"));
        $y   = date('Y', strtotime("-$i months"));
        $rev = $conn->query("SELECT COALESCE(SUM(b.total_price),0) as t FROM bookings b JOIN rooms r ON b.room_id=r.id
            WHERE r.hotel_id=$hotel_id AND MONTH(b.created_at)=$m AND YEAR(b.created_at)=$y AND b.status!='cancelled'")->fetch_assoc()['t'];
        $cnt = $conn->query("SELECT COUNT(*) as c FROM bookings b JOIN rooms r ON b.room_id=r.id
            WHERE r.hotel_id=$hotel_id AND MONTH(b.created_at)=$m AND YEAR(b.created_at)=$y")->fetch_assoc()['c'];
        $monthly[] = ['label' => "$m/$y", 'revenue' => (int)$rev, 'bookings' => (int)$cnt];
    }

    // ---- Doanh thu theo phòng ----
    $room_stats = $conn->query("
        SELECT r.room_name, r.price,
            COUNT(b.id) as total_bookings,
            COALESCE(SUM(b.total_price),0) as revenue
        FROM rooms r
        LEFT JOIN bookings b ON b.room_id = r.id AND b.status != 'cancelled'
        WHERE r.hotel_id = $hotel_id
        GROUP BY r.id
        ORDER BY revenue DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // ---- 10 đặt phòng gần nhất ----
    $recent_bookings = $conn->query("
        SELECT b.order_code, b.full_name, b.total_price, b.status,
               b.check_in, b.check_out, b.created_at, r.room_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        WHERE r.hotel_id = $hotel_id
        ORDER BY b.created_at DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);

    // ---- Đánh giá gần nhất ----
    $recent_reviews = $conn->query("
        SELECT rv.rating, rv.comment, rv.created_at, u.full_name
        FROM reviews rv
        JOIN users u ON u.id = rv.user_id
        WHERE rv.hotel_id = $hotel_id AND rv.is_active = 1
        ORDER BY rv.created_at DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    $chart_labels   = json_encode(array_column($monthly, 'label'));
    $chart_revenue  = json_encode(array_column($monthly, 'revenue'));
    $chart_bookings = json_encode(array_column($monthly, 'bookings'));
}

$page_title    = $hotel ? 'Thống kê: ' . htmlspecialchars($hotel['name']) : 'Thống kê khách sạn';
$page_subtitle = 'Phân tích chi tiết theo từng khách sạn';
include "../includes/admin_header.php";

$status_map = [
    'pending'   => ['Chờ xác nhận', '#b7791f', '#fffbeb'],
    'confirmed' => ['Đã xác nhận',  '#1e73be', '#ebf8ff'],
    'cancelled' => ['Đã huỷ',       '#c53030', '#fff5f5'],
    'completed' => ['Hoàn thành',   '#276749', '#f0fff4'],
];
?>

<!-- Chọn khách sạn -->
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:18px 24px;margin-bottom:22px">
    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <label style="font-size:13px;font-weight:700;color:#4a5568">🏨 Chọn khách sạn:</label>
        <select name="id" onchange="this.form.submit()"
            style="padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;background:#fff;min-width:260px">
            <option value="">-- Chọn khách sạn --</option>
            <?php foreach ($hotels_list as $h): ?>
            <option value="<?= $h['id'] ?>" <?= $hotel_id == $h['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($h['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$hotel_id): ?>
<div style="text-align:center;padding:80px 40px;color:#a0aec0">
    <svg width="56" height="56" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.2" style="margin-bottom:14px">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
    </svg>
    <p style="font-size:15px;font-weight:600;margin:0">Chọn một khách sạn để xem thống kê chi tiết</p>
</div>
<?php else: ?>

<!-- Stat cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px">
    <?php
    $cards = [
        ['💰 Doanh thu',      number_format($stats['total_revenue'],0,',','.').'đ', 'từ đơn không hủy',             '#276749','#f0fff4'],
        ['📋 Tổng đặt phòng', $stats['total_bookings'],                             $stats['pending_count'].' chờ xử lý', '#1e73be','#ebf8ff'],
        ['🎉 Hoàn thành',     $stats['completed_count'],                            $stats['cancelled_count'].' đã huỷ',   '#276749','#f0fff4'],
        ['⭐ Đánh giá',       $stats['total_reviews'] . ' đánh giá',               'TB: '.($stats['avg_rating']??'—').'★','#b7791f','#fffbeb'],
        ['🛏️ Loại phòng',     $stats['total_rooms'],                               'trong khách sạn',               '#6d28d9','#f5f3ff'],
    ];
    foreach ($cards as $c):
    ?>
    <div style="background:#fff;border-radius:12px;border:1.5px solid #e2e8f0;padding:18px 20px">
        <div style="font-size:12px;color:#718096;font-weight:600;margin-bottom:6px"><?= $c[0] ?></div>
        <div style="font-size:18px;font-weight:800;color:#1a202c;margin-bottom:3px"><?= $c[1] ?></div>
        <div style="font-size:11.5px;color:#a0aec0"><?= $c[2] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Biểu đồ doanh thu -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:22px">
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:14px;font-weight:700;color:#1a202c;margin-bottom:16px">📈 Doanh thu & Số đơn 6 tháng</div>
        <canvas id="lineChart" height="110"></canvas>
    </div>
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:14px;font-weight:700;color:#1a202c;margin-bottom:16px">🛏️ Doanh thu theo phòng</div>
        <?php if (empty($room_stats)): ?>
            <p style="color:#a0aec0;text-align:center;padding:20px 0">Chưa có dữ liệu</p>
        <?php else: ?>
            <?php
            $max_rev = (float)max(array_column($room_stats, 'revenue'));
            if ($max_rev == 0) $max_rev = 1;
            foreach ($room_stats as $rs):
                $pct = round((float)$rs['revenue'] / $max_rev * 100);
            ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:4px">
                    <span style="font-weight:600;color:#1a202c"><?= htmlspecialchars(mb_substr($rs['room_name'],0,22)) ?></span>
                    <span style="color:#718096"><?= $rs['total_bookings'] ?> đơn</span>
                </div>
                <div style="background:#f1f5f9;border-radius:6px;height:8px;overflow:hidden">
                    <div style="background:#1e73be;height:100%;width:<?= $pct ?>%;border-radius:6px;transition:width .4s"></div>
                </div>
                <div style="font-size:11.5px;color:#a0aec0;margin-top:2px"><?= number_format($rs['revenue'],0,',','.') ?>đ</div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Đặt phòng gần đây + Đánh giá -->
<div style="display:grid;grid-template-columns:3fr 2fr;gap:18px;margin-bottom:22px">
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:14px;font-weight:700;color:#1a202c;margin-bottom:14px">📋 10 đặt phòng gần nhất</div>
        <table style="width:100%;border-collapse:collapse;font-size:12.5px">
            <thead>
                <tr style="background:#f8fafc">
                    <th style="padding:8px 10px;text-align:left;color:#718096;font-weight:600">Mã đơn</th>
                    <th style="padding:8px 10px;text-align:left;color:#718096;font-weight:600">Khách</th>
                    <th style="padding:8px 10px;text-align:left;color:#718096;font-weight:600">Phòng</th>
                    <th style="padding:8px 10px;text-align:right;color:#718096;font-weight:600">Tiền</th>
                    <th style="padding:8px 10px;text-align:left;color:#718096;font-weight:600">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_bookings as $rb):
                $sm = $status_map[$rb['status']] ?? ['Không rõ','#718096','#f7fafc'];
            ?>
            <tr style="border-bottom:1px solid #f1f5f9">
                <td style="padding:8px 10px;color:#a0aec0;font-family:monospace;font-size:11px;white-space:nowrap"><?= htmlspecialchars($rb['order_code']) ?></td>
                <td style="padding:8px 10px;font-weight:600;color:#1a202c;max-width:110px"><?= htmlspecialchars(mb_substr($rb['full_name'],0,18)) ?></td>
                <td style="padding:8px 10px;color:#718096;max-width:130px;word-break:break-word"><?= htmlspecialchars($rb['room_name']) ?></td>
                <td style="padding:8px 10px;text-align:right;font-weight:700;color:#1a202c"><?= number_format($rb['total_price'],0,',','.') ?>đ</td>
                <td style="padding:8px 10px">
                    <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;color:<?= $sm[1] ?>;background:<?= $sm[2] ?>"><?= $sm[0] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <a href="bookings.php" style="display:block;text-align:center;margin-top:12px;font-size:12.5px;color:#1e73be;text-decoration:none;font-weight:600">Xem tất cả →</a>
    </div>

    <!-- Đánh giá gần nhất -->
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:14px;font-weight:700;color:#1a202c;margin-bottom:14px">⭐ Đánh giá gần nhất</div>
        <?php if (empty($recent_reviews)): ?>
            <p style="color:#a0aec0;text-align:center;padding:30px 0;font-size:13px">Chưa có đánh giá</p>
        <?php else: ?>
        <?php foreach ($recent_reviews as $rv): ?>
        <div style="border-bottom:1px solid #f1f5f9;padding:10px 0">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                <span style="font-weight:600;font-size:13px;color:#1a202c"><?= htmlspecialchars($rv['full_name']) ?></span>
                <span style="font-size:13px;color:#f59e0b;font-weight:700"><?= str_repeat('★', (int)$rv['rating']) ?><?= str_repeat('☆', 5 - (int)$rv['rating']) ?></span>
            </div>
            <p style="font-size:12.5px;color:#4a5568;margin:0;line-height:1.5"><?= htmlspecialchars(mb_substr($rv['comment'],0,80)) ?><?= mb_strlen($rv['comment'])>80?'...':'' ?></p>
            <div style="font-size:11px;color:#a0aec0;margin-top:3px"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <a href="reviews.php?hotel_id=<?= $hotel_id ?>" style="display:block;text-align:center;margin-top:12px;font-size:12.5px;color:#1e73be;text-decoration:none;font-weight:600">Xem tất cả →</a>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const chartLabels   = <?= $chart_labels ?>;
const chartRevenue  = <?= $chart_revenue ?>;
const chartBookings = <?= $chart_bookings ?>;
new Chart(document.getElementById('lineChart'), {
    data: {
        labels: chartLabels,
        datasets: [
            {type:'bar',  label:'Số đơn',    data:chartBookings, backgroundColor:'#bfdbfe', yAxisID:'y2', order:2},
            {type:'line', label:'Doanh thu',  data:chartRevenue,  borderColor:'#1e73be', backgroundColor:'#1e73be22',
             pointBackgroundColor:'#1e73be', tension:.35, fill:true, yAxisID:'y1', order:1},
        ]
    },
    options: {
        responsive:true, interaction:{mode:'index'},
        plugins:{legend:{labels:{font:{size:12}}}},
        scales:{
            y1:{type:'linear',position:'left',grid:{color:'#f1f5f9'},ticks:{callback:v=>v.toLocaleString('vi-VN')+'đ',font:{size:11}}},
            y2:{type:'linear',position:'right',grid:{display:false},ticks:{font:{size:11}}}
        }
    }
});
</script>

<?php endif; ?>

<?php include "../includes/admin_footer.php"; ?>
