<?php
include "../config/database.php";

$page_title    = 'Thanh toán';
$page_subtitle = 'Lịch sử thanh toán';
include "../includes/admin_header.php";

// ── Thông báo sau redirect ────────────────────────────────────────────────────
$success_map = [
    'paid'     => ['✅ Đã xác nhận thanh toán thành công!', '#276749', '#f0fff4'],
    'failed'   => ['❌ Đã từ chối thanh toán!',             '#c53030', '#fff5f5'],
    'refunded' => ['💰 Đã hoàn tiền thành công!',           '#1e73be', '#ebf8ff'],
];
$error_map = [
    'invalid'      => 'Yêu cầu không hợp lệ.',
    'notfound'     => 'Không tìm thấy giao dịch.',
    'already_done' => 'Giao dịch này đã được xử lý trước đó.',
];
if (isset($_GET['success']) && isset($success_map[$_GET['success']])):
    $n = $success_map[$_GET['success']];
    echo "<div style='padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:{$n[1]};background:{$n[2]};border:1px solid {$n[1]}33'>{$n[0]}</div>";
elseif (isset($_GET['error']) && isset($error_map[$_GET['error']])):
    echo "<div style='padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#c53030;background:#fff5f5;border:1px solid #c5303033'>⚠️ " . $error_map[$_GET['error']] . "</div>";
endif;

// ── Tham số lọc ───────────────────────────────────────────────────────────────
$filter        = isset($_GET['status'])  ? trim($_GET['status'])  : '';
$filter_method = isset($_GET['method'])  ? trim($_GET['method'])  : '';
$search        = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$date_from     = isset($_GET['from'])    ? trim($_GET['from'])    : '';
$date_to       = isset($_GET['to'])      ? trim($_GET['to'])      : '';
// special: '' | 'duplicate' | 'conflict'
$special       = isset($_GET['special']) ? trim($_GET['special']) : '';

// ── Validate & whitelist các tham số đầu vào ─────────────────────────────────
// status: chỉ cho phép các giá trị hợp lệ
$allowed_statuses = ['', 'pending', 'paid', 'failed', 'refunded'];
if (!in_array($filter, $allowed_statuses, true)) $filter = '';

// special: chỉ cho phép các chế độ đã định nghĩa
$allowed_special = ['', 'duplicate', 'conflict'];
if (!in_array($special, $allowed_special, true)) $special = '';

// date: phải đúng định dạng YYYY-MM-DD
if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
if ($date_to   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = '';

// ── Helper: build query string giữ nguyên tất cả tham số hiện tại ─────────────
function payments_qs($overrides = []) {
    $base = [
        'status'  => isset($_GET['status'])  ? $_GET['status']  : '',
        'method'  => isset($_GET['method'])  ? $_GET['method']  : '',
        'search'  => isset($_GET['search'])  ? $_GET['search']  : '',
        'from'    => isset($_GET['from'])    ? $_GET['from']    : '',
        'to'      => isset($_GET['to'])      ? $_GET['to']      : '',
        'special' => isset($_GET['special']) ? $_GET['special'] : '',
    ];
    $merged = array_merge($base, $overrides);
    $filtered = array();
    foreach ($merged as $k => $v) {
        if ($v !== '') $filtered[$k] = $v;
    }
    return http_build_query($filtered);
}

// ── Tính toán trước: Duplicate khách hàng ────────────────────────────────────
// Cùng email hoặc cùng SĐT có > 1 giao dịch pending
$dup_email_set = [];
$dr = $conn->query("SELECT email FROM payments WHERE payment_status='pending' AND email IS NOT NULL AND email!='' GROUP BY email HAVING COUNT(*)>1");
while ($r = $dr->fetch_assoc()) $dup_email_set[$r['email']] = true;

$dup_phone_set = [];
$dr2 = $conn->query("SELECT phone FROM payments WHERE payment_status='pending' AND phone IS NOT NULL AND phone!='' GROUP BY phone HAVING COUNT(*)>1");
while ($r = $dr2->fetch_assoc()) $dup_phone_set[$r['phone']] = true;

// ── Tính toán trước: Xung đột phòng ──────────────────────────────────────────
// Hai đặt phòng cùng room_id, ngày chồng chéo, đều chưa hủy
$conflict_booking_ids = [];  // booking_id => true
$conflict_room_orders = [];  // booking_id => thứ tự (1=đặt trước, 2+=đặt sau)
$room_order_counter   = [];  // room_id => số thứ tự đang đếm

$cconf = $conn->query("
    SELECT DISTINCT b1.id AS bid, b1.room_id, b1.created_at
    FROM bookings b1
    JOIN bookings b2
      ON b1.room_id    = b2.room_id
     AND b1.id        != b2.id
     AND b1.check_in  < b2.check_out
     AND b1.check_out > b2.check_in
    WHERE b1.status NOT IN ('cancelled','cancel','canceled')
      AND b2.status NOT IN ('cancelled','cancel','canceled')
    ORDER BY b1.room_id ASC, b1.created_at ASC
");
while ($row = $cconf->fetch_assoc()) {
    $rid = $row['room_id'];
    if (!isset($room_order_counter[$rid])) $room_order_counter[$rid] = 0;
    $room_order_counter[$rid]++;
    $conflict_booking_ids[$row['bid']] = true;
    $conflict_room_orders[$row['bid']] = $room_order_counter[$rid];
}

// ── Đếm cho tab đặc biệt ─────────────────────────────────────────────────────
$count_duplicate = 0;
$dr3 = $conn->query("
    SELECT COUNT(*) as c FROM payments
    WHERE payment_status = 'pending'
      AND (
        email IN (SELECT email FROM (SELECT email FROM payments WHERE payment_status='pending' GROUP BY email HAVING COUNT(*)>1) AS de)
        OR phone IN (SELECT phone FROM (SELECT phone FROM payments WHERE payment_status='pending' GROUP BY phone HAVING COUNT(*)>1) AS dp)
      )
");
if ($r = $dr3->fetch_assoc()) $count_duplicate = (int)$r['c'];

$count_conflict = 0;
$dr4 = $conn->query("
    SELECT COUNT(DISTINCT p.id) as c
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    WHERE b.id IN (
        SELECT DISTINCT b1.id FROM bookings b1
        JOIN bookings b2
          ON b1.room_id = b2.room_id AND b1.id != b2.id
         AND b1.check_in < b2.check_out AND b1.check_out > b2.check_in
        WHERE b1.status NOT IN ('cancelled','cancel','canceled')
          AND b2.status NOT IN ('cancelled','cancel','canceled')
    )
");
if ($r = $dr4->fetch_assoc()) $count_conflict = (int)$r['c'];

// ── Lấy danh sách method để dropdown ─────────────────────────────────────────
$available_methods = [];
$mr = $conn->query("SELECT DISTINCT payment_method FROM payments WHERE payment_method IS NOT NULL AND payment_method!='' ORDER BY payment_method");
while ($row = $mr->fetch_assoc()) $available_methods[] = $row['payment_method'];

// Validate $filter_method: chỉ chấp nhận method có trong DB
if ($filter_method && !in_array($filter_method, $available_methods, true)) $filter_method = '';

// ── Xây dựng WHERE ────────────────────────────────────────────────────────────
$conditions = [];

if ($filter)
    $conditions[] = "p.payment_status = '" . $conn->real_escape_string($filter) . "'";

if ($filter_method)
    $conditions[] = "p.payment_method = '" . $conn->real_escape_string($filter_method) . "'";

if ($search) {
    $sw = '%' . $conn->real_escape_string($search) . '%';
    $conditions[] = "(b.full_name LIKE '$sw' OR p.full_name LIKE '$sw' OR h.name LIKE '$sw' OR p.payment_method LIKE '$sw' OR p.email LIKE '$sw' OR p.phone LIKE '$sw')";
}

if ($date_from) $conditions[] = "DATE(p.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to)   $conditions[] = "DATE(p.created_at) <= '" . $conn->real_escape_string($date_to) . "'";

if ($special === 'duplicate') {
    $conditions[] = "p.payment_status = 'pending'";
    $conditions[] = "(
        p.email IN (SELECT email FROM (SELECT email FROM payments WHERE payment_status='pending' GROUP BY email HAVING COUNT(*)>1) AS de2)
        OR p.phone IN (SELECT phone FROM (SELECT phone FROM payments WHERE payment_status='pending' GROUP BY phone HAVING COUNT(*)>1) AS dp2)
    )";
} elseif ($special === 'conflict') {
    $conditions[] = "b.id IN (
        SELECT DISTINCT b1.id FROM bookings b1
        JOIN bookings b2
          ON b1.room_id = b2.room_id AND b1.id != b2.id
         AND b1.check_in < b2.check_out AND b1.check_out > b2.check_in
        WHERE b1.status NOT IN ('cancelled','cancel','canceled')
          AND b2.status NOT IN ('cancelled','cancel','canceled')
    )";
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$result = $conn->query("
    SELECT p.*,
        b.order_code,
        b.full_name  AS b_full_name,
        b.total_price,
        b.created_at AS booking_date,
        b.check_in,
        b.check_out,
        b.id         AS b_id,
        r.room_name,
        h.name       AS hotel_name
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN rooms    r ON b.room_id    = r.id
    LEFT JOIN hotels   h ON r.hotel_id   = h.id
    $where
    ORDER BY p.id DESC
");

// ── Đếm theo trạng thái (cho tab) ────────────────────────────────────────────
$counts = [];
$cr = $conn->query("SELECT payment_status, COUNT(*) as c FROM payments GROUP BY payment_status");
while ($r = $cr->fetch_assoc()) $counts[$r['payment_status']] = $r['c'];
$counts[''] = array_sum($counts);

// ── Maps ──────────────────────────────────────────────────────────────────────
$status_map = [
    'pending'  => ['Chờ xử lý',    '#b7791f', '#fffbeb'],
    'paid'     => ['Đã thanh toán', '#276749', '#f0fff4'],
    'failed'   => ['Thất bại',      '#c53030', '#fff5f5'],
    'refunded' => ['Hoàn tiền',     '#1e73be', '#ebf8ff'],
];

$method_map = [
    'VNPay'                    => ['💳 VNPay',                    '#6d28d9', '#f5f3ff'],
    'vnpay'                    => ['💳 VNPay',                    '#6d28d9', '#f5f3ff'],
    'MoMo'                     => ['📱 MoMo',                     '#be185d', '#fdf2f8'],
    'momo'                     => ['📱 MoMo',                     '#be185d', '#fdf2f8'],
    'bank_transfer'            => ['🏦 Chuyển khoản',             '#1e73be', '#ebf8ff'],
    'Bank Transfer'            => ['🏦 Chuyển khoản',             '#1e73be', '#ebf8ff'],
    'banking'                  => ['🏦 Chuyển khoản',             '#1e73be', '#ebf8ff'],
    'bank'                     => ['🏦 Chuyển khoản',             '#1e73be', '#ebf8ff'],
    'cash'                     => ['🏨 Tại khách sạn',            '#276749', '#f0fff4'],
    'pay_at_hotel'             => ['🏨 Tại khách sạn',            '#276749', '#f0fff4'],
    'at_hotel'                 => ['🏨 Tại khách sạn',            '#276749', '#f0fff4'],
    'hotel'                    => ['🏨 Tại khách sạn',            '#276749', '#f0fff4'],
    'credit_card'              => ['💳 Thẻ quốc tế',              '#0f766e', '#f0fdfa'],
    'international_card'       => ['💳 Thẻ quốc tế',              '#0f766e', '#f0fdfa'],
    'card'                     => ['💳 Thẻ quốc tế',              '#0f766e', '#f0fdfa'],
    'visa'                     => ['💳 Visa',                     '#1a56db', '#eff6ff'],
    'Visa'                     => ['💳 Visa',                     '#1a56db', '#eff6ff'],
    'mastercard'               => ['💳 Mastercard',               '#dc2626', '#fef2f2'],
    'Mastercard'               => ['💳 Mastercard',               '#dc2626', '#fef2f2'],
    'jcb'                      => ['💳 JCB',                      '#15803d', '#f0fdf4'],
    'JCB'                      => ['💳 JCB',                      '#15803d', '#f0fdf4'],
    'Chuyển khoản ngân hàng'   => ['🏦 Chuyển khoản ngân hàng',   '#1e73be', '#ebf8ff'],
    'Ví MoMo'                  => ['📱 Ví MoMo',                  '#be185d', '#fdf2f8'],
    'Thanh toán tại khách sạn' => ['🏨 Thanh toán tại khách sạn', '#276749', '#f0fff4'],
    'Thẻ quốc tế'              => ['💳 Thẻ quốc tế',              '#0f766e', '#f0fdfa'],
    'amex'                     => ['💳 American Express',          '#1e40af', '#eff6ff'],
    'american_express'         => ['💳 American Express',          '#1e40af', '#eff6ff'],
    'American Express'         => ['💳 American Express',          '#1e40af', '#eff6ff'],
];
?>

<div class="page-header">
    <h2>💳 Lịch sử thanh toán</h2>
</div>

<!-- Summary cards -->
<?php
$total_paid    = $conn->query("SELECT COALESCE(SUM(b.total_price),0) as t FROM payments p LEFT JOIN bookings b ON p.booking_id=b.id WHERE p.payment_status='paid' AND b.payment_flow='platform_collect'")->fetch_assoc()['t'];
$count_paid    = $counts['paid']    ?? 0;
$count_pending = $counts['pending'] ?? 0;
$count_failed  = $counts['failed']  ?? 0;
?>
<div class="summary-row">
    <div class="summary-card">
        <div class="s-icon" style="background:#f0fff4">💰</div>
        <div>
            <div class="s-val" style="color:#276749"><?= number_format($total_paid,0,',','.') ?>đ</div>
            <div class="s-lbl">Tổng đã thu</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="s-icon" style="background:#f0fff4">✅</div>
        <div>
            <div class="s-val"><?= $count_paid ?></div>
            <div class="s-lbl">Đã thanh toán</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="s-icon" style="background:#fffbeb">⏳</div>
        <div>
            <div class="s-val" style="color:#b7791f"><?= $count_pending ?></div>
            <div class="s-lbl">Chờ xử lý</div>
        </div>
    </div>
    <div class="summary-card">
        <div class="s-icon" style="background:#fff5f5">❌</div>
        <div>
            <div class="s-val" style="color:#c53030"><?= $count_failed ?></div>
            <div class="s-lbl">Thất bại</div>
        </div>
    </div>
</div>

<!-- Filter tabs – Trạng thái thanh toán + tab đặc biệt -->
<div class="filter-tabs" style="flex-wrap:wrap;gap:6px">
    <?php
    $tabs = [
        ''         => '📋 Tất cả',
        'pending'  => '⏳ Chờ xử lý',
        'paid'     => '✅ Đã thanh toán',
        'failed'   => '❌ Thất bại',
        'refunded' => '💰 Hoàn tiền',
    ];
    foreach ($tabs as $val => $label):
        $active = (!$special && $filter === $val) ? 'active' : '';
        $cnt    = $counts[$val] ?? 0;
        $href   = 'payments.php?' . payments_qs(['status' => $val, 'special' => '']);
    ?>
        <a href="<?= $href ?>" class="filter-tab <?= $active ?>">
            <?= $label ?> <span class="count"><?= $cnt ?></span>
        </a>
    <?php endforeach; ?>

    <!-- Tab: Duplicate khách hàng -->
    <a href="payments.php?<?= payments_qs(['special' => 'duplicate', 'status' => '']) ?>"
       class="filter-tab <?= $special === 'duplicate' ? 'active' : '' ?>"
       style="<?= $special !== 'duplicate' ? 'border-color:#d97706;color:#92400e' : '' ?>">
        ⚠️ Cảnh báo KH
        <span class="count" style="background:#fef3c7;color:#b45309"><?= $count_duplicate ?></span>
    </a>

    <!-- Tab: Xung đột phòng -->
    <a href="payments.php?<?= payments_qs(['special' => 'conflict', 'status' => '']) ?>"
       class="filter-tab <?= $special === 'conflict' ? 'active' : '' ?>"
       style="<?= $special !== 'conflict' ? 'border-color:#dc2626;color:#dc2626' : '' ?>">
        🔴 Xung đột phòng
        <span class="count" style="background:#fee2e2;color:#dc2626"><?= $count_conflict ?></span>
    </a>
</div>

<!-- Banner mô tả chế độ đặc biệt -->
<?php if ($special === 'duplicate'): ?>
<div style="padding:10px 16px;border-radius:10px;background:#fffbeb;border:1.5px solid #d97706;margin-bottom:12px;font-size:13px;color:#92400e;line-height:1.6">
    ⚠️ <strong>Chế độ: Cảnh báo khách hàng</strong> — Hiển thị các giao dịch <em>đang chờ thanh toán</em> có cùng email hoặc SĐT xuất hiện nhiều lần. Cần xác nhận khách đặt nhầm hay cố tình đặt nhiều đơn.
</div>
<?php elseif ($special === 'conflict'): ?>
<div style="padding:10px 16px;border-radius:10px;background:#fff1f2;border:1.5px solid #dc2626;margin-bottom:12px;font-size:13px;color:#991b1b;line-height:1.6">
    🔴 <strong>Chế độ: Xung đột phòng</strong> — Hiển thị các giao dịch có ngày lưu trú <strong>chồng chéo nhau</strong> cho cùng một phòng. Nhãn <strong style="background:#fee2e2;padding:1px 5px;border-radius:4px">🔴 Đặt #1</strong> = đặt trước nhất, <strong style="background:#fee2e2;padding:1px 5px;border-radius:4px">🔴 Đặt #2</strong> = đặt sau — cần ưu tiên xử lý theo thứ tự.
</div>
<?php endif; ?>

<!-- Bộ lọc nâng cao: phương thức + khoảng ngày + tìm kiếm -->
<form method="GET" action="">
    <?php if ($filter):  ?><input type="hidden" name="status"  value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
    <?php if ($special): ?><input type="hidden" name="special" value="<?= htmlspecialchars($special) ?>"><?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;align-items:flex-end">

        <!-- Tìm kiếm -->
        <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-width:210px">
            <label style="font-size:11px;font-weight:600;color:#718096;text-transform:uppercase;letter-spacing:.5px">Tìm kiếm</label>
            <div class="search-bar" style="margin:0;flex:1">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
                    <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                </svg>
                <input type="text" name="search"
                    placeholder="Tên, khách sạn, email, SĐT..."
                    value="<?= htmlspecialchars($search) ?>"
                    style="border:none;outline:none;background:transparent;width:100%;font-size:13px">
            </div>
        </div>

        <!-- Phương thức thanh toán -->
        <div style="display:flex;flex-direction:column;gap:3px">
            <label style="font-size:11px;font-weight:600;color:#718096;text-transform:uppercase;letter-spacing:.5px">Phương thức</label>
            <select name="method"
                style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#2d3748;background:#fff;min-width:180px;cursor:pointer;height:38px"
                onchange="this.form.submit()">
                <option value="">🔀 Tất cả phương thức</option>
                <?php foreach ($available_methods as $m):
                    $mm_opt = isset($method_map[trim($m)]) ? $method_map[trim($m)][0] : htmlspecialchars($m);
                ?>
                <option value="<?= htmlspecialchars($m) ?>" <?= ($filter_method === $m) ? 'selected' : '' ?>>
                    <?= $mm_opt ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Từ ngày -->
        <div style="display:flex;flex-direction:column;gap:3px">
            <label style="font-size:11px;font-weight:600;color:#718096;text-transform:uppercase;letter-spacing:.5px">Từ ngày</label>
            <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>"
                style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#2d3748;background:#fff;height:38px">
        </div>

        <!-- Đến ngày -->
        <div style="display:flex;flex-direction:column;gap:3px">
            <label style="font-size:11px;font-weight:600;color:#718096;text-transform:uppercase;letter-spacing:.5px">Đến ngày</label>
            <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>"
                style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#2d3748;background:#fff;height:38px">
        </div>

        <button type="submit" class="btn-search" style="height:38px;padding:0 18px;white-space:nowrap;align-self:flex-end">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
            </svg>
            Lọc
        </button>

        <?php if ($search || $filter_method || $date_from || $date_to): ?>
        <a href="payments.php?<?= payments_qs(['search' => '', 'method' => '', 'from' => '', 'to' => '']) ?>"
           class="btn-reset"
           style="height:38px;display:inline-flex;align-items:center;padding:0 14px;text-decoration:none;border-radius:8px;align-self:flex-end;font-size:13px">
            ✕ Xóa lọc
        </a>
        <?php endif; ?>
    </div>

    <!-- Thông tin kết quả lọc -->
    <?php if ($search || $filter_method || $date_from || $date_to): ?>
    <div style="font-size:12.5px;color:#718096;margin-bottom:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <span>Tìm thấy <strong style="color:#2d3748"><?= $result->num_rows ?></strong> giao dịch</span>
        <?php if ($filter_method): ?>
            <span style="padding:2px 8px;background:#e9d8fd;color:#553c9a;border-radius:5px;font-size:12px">
                🔀 <?= htmlspecialchars($filter_method) ?>
            </span>
        <?php endif; ?>
        <?php if ($date_from || $date_to): ?>
            <span style="padding:2px 8px;background:#bee3f8;color:#2a4365;border-radius:5px;font-size:12px">
                📅 <?= $date_from ? htmlspecialchars($date_from) : '...' ?> → <?= $date_to ? htmlspecialchars($date_to) : '...' ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</form>

<div class="section-card">
  <div style="overflow-x:auto">
    <table style="min-width:1000px">
        <thead>
            <tr>
                <th>ID</th>
                <th>Mã đơn</th>
                <th>Khách hàng</th>
                <th>Khách sạn / Phòng</th>
                <th>Ngày lưu trú</th>
                <th>Số tiền</th>
                <th>Phương thức</th>
                <th>Ngày thanh toán</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="10" style="text-align:center;color:#a0aec0;padding:40px">
                    <?php if ($special === 'duplicate'): ?>
                        ✅ Không có khách hàng trùng lặp trong các giao dịch đang chờ
                    <?php elseif ($special === 'conflict'): ?>
                        ✅ Không có xung đột phòng nào
                    <?php elseif ($search): ?>
                        Không tìm thấy giao dịch nào với &quot;<strong><?= htmlspecialchars($search) ?></strong>&quot;
                    <?php else: ?>
                        Chưa có giao dịch nào
                    <?php endif; ?>
                </td>
            </tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()):
                $row['payment_status'] = trim($row['payment_status'] ?? '');
                if (empty($row['payment_status'])) $row['payment_status'] = 'pending';
                $sm = isset($status_map[$row['payment_status']]) ? $status_map[$row['payment_status']] : ['Không rõ', '#718096', '#f7fafc'];
                $mm = isset($method_map[trim($row['payment_method'] ?? '')]) ? $method_map[trim($row['payment_method'] ?? '')] : ['💳 ' . ($row['payment_method'] ?? '—'), '#718096', '#f7fafc'];

                // Cờ duplicate: pending + trùng email hoặc SĐT
                $is_dup = ($row['payment_status'] === 'pending') && (
                    (isset($row['email']) && $row['email'] !== '' && isset($dup_email_set[$row['email']])) ||
                    (isset($row['phone']) && $row['phone'] !== '' && isset($dup_phone_set[$row['phone']]))
                );

                // Cờ xung đột phòng
                $b_id        = isset($row['b_id']) ? (int)$row['b_id'] : 0;
                $is_conflict = $b_id > 0 && isset($conflict_booking_ids[$b_id]);
                $conf_order  = $is_conflict ? ($conflict_room_orders[$b_id] ?? '?') : null;

                // Màu nền dòng
                $row_bg = '';
                if ($is_conflict) $row_bg = 'background:#fff8f8';
                elseif ($is_dup)  $row_bg = 'background:#fffef0';

                // Tên hiển thị: ưu tiên payment.full_name, fallback booking.full_name
                $display_name = !empty($row['full_name']) ? $row['full_name'] : ($row['b_full_name'] ?? '—');
            ?>
            <tr style="<?= $row_bg ?>">
                <td class="td-order">#<?= $row['id'] ?></td>
                <td class="td-order"><?= htmlspecialchars($row['order_code'] ?? '—') ?></td>

                <!-- Khách hàng + badge -->
                <td class="td-name">
                    <?= htmlspecialchars($display_name) ?>
                    <?php if ($is_dup): ?>
                    <br><span style="display:inline-block;margin-top:3px;padding:2px 7px;border-radius:5px;font-size:10.5px;font-weight:700;background:#fef3c7;color:#b45309">⚠️ Cảnh báo</span>
                    <?php endif; ?>
                    <?php if (!empty($row['email']) || !empty($row['phone'])): ?>
                    <br><span style="font-size:11px;color:#a0aec0">
                        <?= htmlspecialchars($row['email'] ?? '') ?>
                        <?= (!empty($row['email']) && !empty($row['phone'])) ? ' · ' : '' ?>
                        <?= htmlspecialchars($row['phone'] ?? '') ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Khách sạn / Phòng + badge xung đột -->
                <td>
                    <div style="color:#2d3748;font-size:13px"><?= htmlspecialchars($row['hotel_name'] ?? '—') ?></div>
                    <?php if (!empty($row['room_name'])): ?>
                    <div style="font-size:11.5px;color:#718096;margin-top:2px">
                        <?= htmlspecialchars($row['room_name']) ?>
                        <?php if ($is_conflict): ?>
                        <span style="display:inline-block;margin-left:4px;padding:1px 6px;border-radius:5px;font-size:10.5px;font-weight:700;background:#fee2e2;color:#dc2626">
                            🔴 Đặt #<?= $conf_order ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($is_conflict): ?>
                    <span style="display:inline-block;margin-top:2px;padding:1px 6px;border-radius:5px;font-size:10.5px;font-weight:700;background:#fee2e2;color:#dc2626">
                        🔴 Đặt #<?= $conf_order ?>
                    </span>
                    <?php endif; ?>
                </td>

                <!-- Ngày lưu trú -->
                <td style="font-size:12px;color:#718096;white-space:nowrap">
                    <?php if (!empty($row['check_in']) && !empty($row['check_out'])): ?>
                        <?= date('d/m/Y', strtotime($row['check_in'])) ?>
                        <span style="color:#cbd5e0"> → </span>
                        <?= date('d/m/Y', strtotime($row['check_out'])) ?>
                    <?php else: ?>—<?php endif; ?>
                </td>

                <td class="td-price"><?= number_format($row['total_price'] ?? $row['amount'] ?? 0, 0, ',', '.') ?>đ</td>

                <td>
                    <span class="status-badge" style="color:<?= $mm[1] ?>;background:<?= $mm[2] ?>">
                        <?= $mm[0] ?>
                    </span>
                </td>

                <td style="color:#a0aec0;font-size:12px;white-space:nowrap">
                    <?= !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '—' ?>
                </td>

                <td>
                    <?php if ($row['payment_status'] === 'paid'): ?>
                        <span class="status-badge" style="color:#276749;background:#f0fff4">✅ Đã thanh toán</span>
                    <?php elseif ($row['payment_status'] === 'failed'): ?>
                        <span class="status-badge" style="color:#c53030;background:#fff5f5">❌ Đã từ chối</span>
                    <?php elseif ($row['payment_status'] === 'refunded'): ?>
                        <span class="status-badge" style="color:#1e73be;background:#ebf8ff">💰 Hoàn tiền</span>
                    <?php else: ?>
                        <span class="status-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>"><?= $sm[0] ?></span>
                    <?php endif; ?>
                </td>

                <td style="white-space:nowrap">
                    <?php if ($row['payment_status'] === 'pending'): ?>
                        <a href="update_payment.php?id=<?= $row['id'] ?>&status=paid"
                           class="btn btn-paid"
                           onclick="adminConfirm('Xác nhận thanh toán đơn này?', this.href, '✅'); return false;">✅ Xác nhận</a>
                        <a href="update_payment.php?id=<?= $row['id'] ?>&status=failed"
                           class="btn btn-reject"
                           onclick="adminConfirm('Từ chối thanh toán đơn này?', this.href, '❌'); return false;">❌ Từ chối</a>
                    <?php elseif ($row['payment_status'] === 'paid'): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#f0fff4;color:#276749;border-radius:50%;font-size:16px;font-weight:700" title="Đã thanh toán">✅</span>
                    <?php elseif ($row['payment_status'] === 'failed'): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#fff5f5;color:#c53030;border-radius:50%;font-size:16px;font-weight:700" title="Đã từ chối">❌</span>
                    <?php elseif ($row['payment_status'] === 'refunded'): ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#ebf8ff;color:#1e73be;border-radius:50%;font-size:14px;font-weight:700" title="Đã hoàn tiền">💰</span>
                    <?php else: ?>
                        <span style="color:#a0aec0;font-size:12px;font-style:italic">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
  </div>
</div>

<?php include "../includes/admin_footer.php"; ?>
