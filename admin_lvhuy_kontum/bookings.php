<?php
include("../config/database.php");

// --- THÊM MỚI: EXPORT CSV ---
// Gọi: bookings.php?export=csv  hoặc  bookings.php?export=csv&status=pending&search=xyz
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exp_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
    $exp_search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $exp_where  = [];
    if ($exp_status) $exp_where[] = "b.status = '$exp_status'";
    if ($exp_search) {
        $sw = "%" . $conn->real_escape_string($exp_search) . "%";
        $exp_where[] = "(b.full_name LIKE '$sw' OR h.name LIKE '$sw')";
    }
    $exp_sql = $exp_where ? 'WHERE ' . implode(' AND ', $exp_where) : '';

    $exp_result = $conn->query("
        SELECT b.order_code, b.full_name, b.email, b.phone,
            h.name AS hotel_name, r.room_name,
            b.check_in, b.check_out, b.total_price,
            b.payment_method, b.status, b.created_at, b.note
        FROM bookings b
        LEFT JOIN rooms r  ON b.room_id  = r.id
        LEFT JOIN hotels h ON r.hotel_id = h.id
        $exp_sql
        ORDER BY b.created_at DESC
    ");

    $status_vi = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
        'paid'      => 'Đã xác nhận',
        'cancel'    => 'Đã huỷ',
        'canceled'  => 'Đã huỷ',
        'done'      => 'Hoàn thành',
        'approved'  => 'Đã xác nhận',
    ];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="danh_sach_booking_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8 để Excel đọc được tiếng Việt

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Mã đơn', 'Họ tên khách', 'Email', 'Số điện thoại',
        'Khách sạn', 'Loại phòng', 'Ngày nhận phòng', 'Ngày trả phòng',
        'Tổng tiền (đ)', 'Phương thức TT', 'Trạng thái', 'Ngày đặt', 'Ghi chú'
    ]);
    while ($row = $exp_result->fetch_assoc()) {
        fputcsv($out, [
            $row['order_code']      ?? '',
            $row['full_name']       ?? '',
            $row['email']           ?? '',
            $row['phone']           ?? '',
            $row['hotel_name']      ?? '—',
            $row['room_name']       ?? '—',
            $row['check_in']        ?? '',
            $row['check_out']       ?? '',
            $row['total_price']     ?? 0,
            strtoupper($row['payment_method'] ?? ''),
            $status_vi[$row['status']] ?? ($row['status'] ?? ''),
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['note']            ?? '',
        ]);
    }
    fclose($out);
    exit;
}
// --- KẾT THÚC EXPORT CSV ---

$page_title    = 'Đặt phòng';
$page_subtitle = 'Quản lý danh sách đặt phòng';

include("../includes/admin_header.php");

// Thông báo sau redirect
$success_map = [
    'confirmed' => ['✅ Đã xác nhận đơn thành công!',   '#276749', '#f0fff4'],
    'cancelled' => ['❌ Đã huỷ đơn thành công!',         '#c53030', '#fff5f5'],
    'completed' => ['🎉 Đã đánh dấu hoàn thành!',        '#1e73be', '#ebf8ff'],
];
$error_map = [
    'invalid'      => 'Yêu cầu không hợp lệ.',
    'notfound'     => 'Không tìm thấy đơn đặt phòng.',
    'already_done' => 'Đơn này đã được xử lý trước đó.',
];
if(isset($_GET['success']) && isset($success_map[$_GET['success']])):
    $n = $success_map[$_GET['success']];
    echo "<div style='padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:{$n[1]};background:{$n[2]};border:1px solid {$n[1]}33'>{$n[0]}</div>";
elseif(isset($_GET['error']) && isset($error_map[$_GET['error']])):
    echo "<div style='padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#c53030;background:#fff5f5;border:1px solid #c5303033'>⚠️ " . $error_map[$_GET['error']] . "</div>";
endif;

// Lọc theo trạng thái
$filter = isset($_GET['status']) ? $_GET['status'] : '';
$where  = $filter ? "WHERE b.status = '" . $conn->real_escape_string($filter) . "'" : '';

// Tìm kiếm theo tên khách hàng
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if($search) {
    $sw     = "%" . $conn->real_escape_string($search) . "%";
    $where  = $where ? "$where AND (b.full_name LIKE '$sw' OR h.name LIKE '$sw')"
                     : "WHERE (b.full_name LIKE '$sw' OR h.name LIKE '$sw')";
}

$result = $conn->query("
    SELECT b.*,
        u.full_name AS user_name,
        r.room_name,
        h.name AS hotel_name
    FROM bookings b
    LEFT JOIN users u  ON b.user_id  = u.id
    LEFT JOIN rooms r  ON b.room_id  = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    $where
    ORDER BY b.created_at DESC
");

$status_map = [
    'pending'   => ['Chờ xác nhận', '#b7791f', '#fffbeb'],
    'confirmed' => ['Đã xác nhận',  '#1e73be', '#ebf8ff'],
    'cancelled' => ['Đã huỷ',       '#c53030', '#fff5f5'],
    'completed' => ['Hoàn thành',   '#276749', '#f0fff4'],
    'paid'      => ['Đã xác nhận',  '#1e73be', '#ebf8ff'],
    'cancel'    => ['Đã huỷ',       '#c53030', '#fff5f5'],
    'canceled'  => ['Đã huỷ',       '#c53030', '#fff5f5'],
    'done'      => ['Hoàn thành',   '#276749', '#f0fff4'],
    'approved'  => ['Đã xác nhận',  '#1e73be', '#ebf8ff'],
];

// Đếm từng trạng thái cho tab filter
$counts = [];
$cr = $conn->query("SELECT COALESCE(NULLIF(status,''),'pending') as s, COUNT(*) as c FROM bookings GROUP BY s");
while($r = $cr->fetch_assoc()) $counts[$r['s']] = $r['c'];
$counts[''] = array_sum($counts);

// URL export giữ nguyên filter + search hiện tại
$export_qs = http_build_query(array_filter([
    'export' => 'csv',
    'status' => $filter,
    'search' => $search,
]));
?>

<!-- --- THÊM MỚI: Header row với nút Export --- -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <div class="page-header" style="margin:0">
        <h2>📋 Danh sách đặt phòng</h2>
    </div>
    <a href="bookings.php?<?= $export_qs ?>"
    style="display:inline-flex;align-items:center;gap:7px;background:#10b981;color:#fff;padding:9px 18px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:700;box-shadow:0 2px 8px rgba(16,185,129,.3);transition:background .2s"
    onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'"
    title="Xuất danh sách đang lọc ra file CSV — mở được bằng Excel">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Xuất Excel / CSV
    </a>
</div>

<!-- Filter tabs -->
<div class="filter-tabs">
    <?php
    $tabs = [
        ''          => '📋 Tất cả',
        'pending'   => '⏳ Chờ xác nhận',
        'confirmed' => '✅ Đã xác nhận',
        'completed' => '🎉 Hoàn thành',
        'cancelled' => '❌ Đã huỷ',
    ];
    foreach($tabs as $val => $label):
        $active = ($filter === $val) ? 'active' : '';
        $cnt    = $counts[$val] ?? 0;
        $href   = 'bookings.php?' . ($val ? "status=$val" : '') . ($search ? "&search=" . urlencode($search) : '');
    ?>
        <a href="<?= $href ?>" class="filter-tab <?= $active ?>">
            <?= $label ?>
            <span class="count"><?= $cnt ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Search bar -->
<form method="GET" action="">
    <?php if($filter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
    <div class="search-bar">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search"
            placeholder="Tìm theo tên khách hàng hoặc khách sạn..."
            value="<?= htmlspecialchars($search) ?>">
        <?php if($search): ?>
            <span class="search-result-info">Tìm thấy <strong><?= $result->num_rows ?></strong> đơn</span>
            <a href="bookings.php<?= $filter ? "?status=$filter" : '' ?>" class="btn-reset">✕ Xóa</a>
        <?php endif; ?>
        <button type="submit" class="btn-search">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
            </svg>
            Tìm kiếm
        </button>
    </div>
</form>

<!-- Table -->
<div class="section-card">
    <table>
        <thead>
            <tr>
                <th>Mã đơn</th>
                <th>Khách hàng</th>
                <th>Khách sạn</th>
                <th>Loại phòng</th>
                <th>Tổng tiền</th>
                <th>Ngày đặt</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if($result->num_rows === 0): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#a0aec0;padding:40px">
                    <?= $search ? "Không tìm thấy đơn nào với \"<strong>" . htmlspecialchars($search) . "</strong>\"" : 'Chưa có đặt phòng nào' ?>
                </td>
            </tr>
        <?php else: ?>
            <?php while($row = $result->fetch_assoc()):
                if(empty($row['status'])) $row['status'] = 'pending';
                $sm = $status_map[$row['status']] ?? ['Không rõ', '#718096', '#f7fafc'];
            ?>
            <tr>
                <td class="td-order"><?= htmlspecialchars($row['order_code'] ?? '#' . $row['id']) ?></td>
                <td class="td-name"><?= htmlspecialchars($row['full_name'] ?? $row['user_name'] ?? '—') ?></td>
                <td style="color:#718096"><?= htmlspecialchars($row['hotel_name'] ?? '—') ?></td>
                <td style="color:#2d3748"><?= htmlspecialchars($row['room_name'] ?? '—') ?></td>
                <td class="td-price"><?= number_format($row['total_price'] ?? 0, 0, ',', '.') ?>đ</td>
                <td style="color:#a0aec0;font-size:12px"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                <td>
                    <span class="status-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>">
                        <?= $sm[0] ?>
                    </span>
                </td>
                <td style="white-space:nowrap">
                    <a href="booking_detail.php?id=<?= $row['id'] ?>"
                       class="btn btn-edit" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd">👁️ Chi tiết</a>
                    <?php
                    $s = $row['status'];
                    $is_pending   = ($s === 'pending');
                    $is_confirmed = in_array($s, ['confirmed', 'paid', 'approved']);
                    $is_done      = in_array($s, ['cancelled', 'cancel', 'canceled', 'completed', 'done']);
                    ?>
                    <?php if($is_pending): ?>
                        <a href="update_booking.php?id=<?= $row['id'] ?>&status=confirmed"
                        class="btn btn-confirm"
                        onclick="return confirm('Xác nhận đơn này?')">✅ Xác nhận</a>
                        <a href="update_booking.php?id=<?= $row['id'] ?>&status=cancelled"
                        class="btn btn-cancel"
                        onclick="return confirm('Huỷ đơn này?')">❌ Huỷ</a>
                    <?php elseif($is_confirmed): ?>
                        <a href="update_booking.php?id=<?= $row['id'] ?>&status=completed"
                        class="btn btn-edit"
                        onclick="return confirm('Đánh dấu hoàn thành?')">🎉 Hoàn thành</a>
                        <a href="update_booking.php?id=<?= $row['id'] ?>&status=cancelled"
                        class="btn btn-cancel"
                        onclick="return confirm('Huỷ đơn này?')">❌ Huỷ</a>
                    <?php else: ?>
                        <?php if(in_array($row['status'], ['completed', 'done'])): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;background:#f0fff4;color:#276749;border-radius:8px;font-size:12.5px;font-weight:600">
                                ✅ Hoàn thành
                            </span>
                        <?php elseif(in_array($row['status'], ['cancelled', 'cancel', 'canceled'])): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;background:#fff5f5;color:#c53030;border-radius:8px;font-size:12.5px;font-weight:600">
                                ❌ Đã huỷ
                            </span>
                        <?php else: ?>
                            <span style="color:#a0aec0;font-size:12px;font-style:italic">—</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include("../includes/admin_footer.php"); ?>