<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])): ?>

<div style="max-width:500px;margin:80px auto;text-align:center;padding:48px 36px;background:#fff;border-radius:24px;box-shadow:0 8px 40px rgba(0,0,0,.10)">
    <div style="width:80px;height:80px;background:linear-gradient(135deg,#ebf8ff,#bee3f8);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px">
        🔐
    </div>
    <h2 style="font-size:22px;font-weight:800;color:#1a202c;margin:0 0 10px">
        Hành trình của bạn đang chờ!
    </h2>
    <p style="font-size:14px;color:#718096;line-height:1.7;margin:0 0 28px">
        Đăng nhập để xem toàn bộ lịch sử đặt phòng,<br>
        theo dõi trạng thái và quản lý chuyến đi của bạn.
    </p>
    <a href="/tour_khach_san_project/auth/login.php"
    style="display:inline-block;background:linear-gradient(135deg,#1e73be,#2d9cdb);color:#fff;padding:14px 36px;border-radius:12px;font-weight:700;font-size:15px;text-decoration:none;box-shadow:0 4px 16px rgba(30,115,190,.35);transition:all .2s">
        🚀 Đăng nhập ngay
    </a>
    <div style="margin-top:16px;font-size:13px;color:#a0aec0">
        Chưa có tài khoản?
        <a href="/tour_khach_san_project/auth/register.php"
        style="color:#1e73be;font-weight:700;text-decoration:none">
            Đăng ký miễn phí →
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; exit(); endif;

$user_id = $_SESSION['user_id'];

// Thông báo
$notify = '';
if (isset($_GET['cancelled'])) {
    $notify = '<div style="padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13.5px;font-weight:600;color:#276749;background:#f0fff4;border:1px solid #9ae6b4">
        ✅ Đã hủy đơn thành công!
    </div>';
} elseif (isset($_GET['refund'])) {
    if ($_GET['refund'] === 'requested') {
        $notify = '<div style="padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13.5px;font-weight:600;color:#1e73be;background:#ebf8ff;border:1px solid #bee3f8">
            ✅ Yêu cầu hoàn tiền đã được gửi! Admin sẽ xử lý trong 1-3 ngày làm việc.
        </div>';
    }
} elseif (isset($_GET['error'])) {
    $error_msgs = [
        'invalid'           => 'Yêu cầu không hợp lệ.',
        'notfound'          => 'Không tìm thấy đơn đặt phòng.',
        'not_confirmed'     => 'Chỉ có thể hoàn tiền đơn đã xác nhận.',
        'already_requested' => 'Bạn đã gửi yêu cầu hoàn tiền cho đơn này rồi.',
        'too_late'          => 'Không thể hoàn tiền khi check-in trong vòng 24h.',
    ];
    $msg = $error_msgs[$_GET['error']] ?? 'Có lỗi xảy ra.';
    $notify = "<div style='padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13.5px;font-weight:600;color:#c53030;background:#fff5f5;border:1px solid #feb2b2'>⚠️ $msg</div>";
}

// Lấy tất cả booking của user
$stmt = $conn->prepare("
    SELECT 
        b.id, b.order_code, b.full_name, b.email, b.phone,
        b.check_in, b.check_out, b.total_price,
        b.payment_method, b.note, b.status, b.created_at,
        b.refund_requested, b.refund_amount,
        r.room_name, r.price AS room_price,
        h.name AS hotel_name, h.address AS hotel_address
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$count_all       = count($bookings);
$count_pending   = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$count_confirmed = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed' && empty($b['refund_requested'])));
$count_refunding = count(array_filter($bookings, fn($b) => !empty($b['refund_requested']) && $b['refund_requested'] == 1));
$count_cancelled = count(array_filter($bookings, fn($b) => $b['status'] === 'cancelled'));
$count_completed = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));

// Filter theo tab
$filter = $_GET['status'] ?? 'all';
if ($filter === 'refunding') {
    $bookings = array_filter($bookings, fn($b) => !empty($b['refund_requested']) && $b['refund_requested'] == 1);
} elseif ($filter === 'confirmed') {
    $bookings = array_filter($bookings, fn($b) => $b['status'] === 'confirmed' && empty($b['refund_requested']));
} elseif ($filter !== 'all') {
    $bookings = array_filter($bookings, fn($b) => $b['status'] === $filter);
}

$status_map = [
    'pending'   => ['Chờ xác nhận', '#b7791f', '#fffbeb', '#fef3c7'],
    'confirmed' => ['Đã xác nhận',  '#2b6cb0', '#ebf8ff', '#bee3f8'],
    'cancelled' => ['Đã hủy',       '#c53030', '#fff5f5', '#fed7d7'],
    'completed' => ['Hoàn thành',   '#276749', '#f0fff4', '#9ae6b4'],
];

$method_map = [
    'bank'  => ['Chuyển khoản', '🏦'],
    'momo'  => ['Ví MoMo',      '💜'],
    'vnpay' => ['VNPay',        '💳'],
    'hotel' => ['Tại khách sạn','🏨'],
    'card'  => ['Thẻ quốc tế',  '💳'],
];
?>

<div class="mybk-page">

    <?= $notify ?>

    <!-- Header -->
    <div class="mybk-header">
        <div class="mybk-title">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Lịch sử đặt phòng
        </div>
        <a href="hotels.php" class="mybk-new-btn">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Đặt phòng mới
        </a>
    </div>

    <!-- Filter tabs -->
    <div class="mybk-tabs">
        <?php
        $tabs = [
            'all'       => ["Tất cả",        $count_all],
            'pending'   => ["Chờ xác nhận",  $count_pending],
            'confirmed' => ["Đã xác nhận",   $count_confirmed],
            'refunding' => ["Chờ hoàn tiền", $count_refunding],
            'completed' => ["Hoàn thành",    $count_completed],
            'cancelled' => ["Đã hủy",        $count_cancelled],
        ];
        foreach ($tabs as $key => [$label, $count]):
        ?>
        <a href="?status=<?= $key ?>" class="tab-item <?= $filter === $key ? 'active' : '' ?>">
            <?= $label ?>
            <span class="tab-count"><?= $count ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Danh sách booking -->
    <?php if (empty($bookings)): ?>
        <div class="mybk-empty">
            <svg width="56" height="56" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p>Không có đơn đặt phòng nào</p>
            <a href="hotels.php" class="mybk-new-btn" style="margin-top:14px">Khám phá khách sạn ngay</a>
        </div>
    <?php else: ?>
        <div class="mybk-list">
        <?php foreach ($bookings as $b):
            $sm = $status_map[$b['status']] ?? ['Không rõ','#718096','#f7fafc','#e2e8f0'];
            $pm = $method_map[$b['payment_method']] ?? [$b['payment_method'], '💳'];
            $ci = $b['check_in']  ? date('d/m/Y', strtotime($b['check_in']))  : '?';
            $co = $b['check_out'] ? date('d/m/Y', strtotime($b['check_out'])) : '?';
            $nights = ($b['check_in'] && $b['check_out'])
                ? max(1, intval((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400))
                : 0;
            $created_date = $b['created_at'] ? date('d/m/Y', strtotime($b['created_at'])) : '?';
            $created_time = $b['created_at'] ? date('H:i', strtotime($b['created_at'])) : '?';

            // Kiểm tra điều kiện hoàn tiền
            $can_refund    = false;
            $refund_amount = 0;
            $refund_fee    = 0;
            if ($b['status'] === 'confirmed' && empty($b['refund_requested'])) {
                $hours_to_checkin = (strtotime($b['check_in']) - time()) / 3600;
                if ($hours_to_checkin > 24) {
                    $can_refund    = true;
                    $refund_amount = $b['total_price'] * 0.8;
                    $refund_fee    = $b['total_price'] * 0.2;
                }
            }

            $refund_status = (int)($b['refund_requested'] ?? 0);
        ?>
        <div class="bk-card">

            <!-- Card header -->
            <div class="bk-card-head">
                <div class="bk-head-left">
                    <div class="bk-hotel-icon">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
                        </svg>
                    </div>
                    <div>
                        <div class="bk-hotel-name"><?= htmlspecialchars($b['hotel_name'] ?? 'Khách sạn') ?></div>
                        <div class="bk-hotel-addr"><?= htmlspecialchars($b['hotel_address'] ?? '') ?></div>
                    </div>
                </div>
                <div class="bk-head-right">
                    <?php if ($refund_status === 1): ?>
                        <span class="bk-status-badge" style="color:#b7791f;background:#fffbeb;border:1px solid #fef3c7">
                            ⏳ Chờ hoàn tiền
                        </span>
                    <?php elseif ($refund_status === 2): ?>
                        <span class="bk-status-badge" style="color:#276749;background:#f0fff4;border:1px solid #9ae6b4">
                            ✅ Đã hoàn tiền
                        </span>
                    <?php else: ?>
                        <span class="bk-status-badge" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;border:1px solid <?= $sm[3] ?>">
                            <?= $sm[0] ?>
                        </span>
                    <?php endif; ?>
                    <div class="bk-order-code"># <?= htmlspecialchars($b['order_code']) ?></div>
                </div>
            </div>

            <!-- Card body -->
            <div class="bk-card-body">

                <!-- Cột 1: Thông tin người đặt -->
                <div class="bk-col">
                    <div class="bk-col-title">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                        Thông tin người đặt
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Họ tên</span>
                        <span class="bk-info-val"><?= htmlspecialchars($b['full_name']) ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Email</span>
                        <span class="bk-info-val"><?= htmlspecialchars($b['email']) ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Điện thoại</span>
                        <span class="bk-info-val"><?= htmlspecialchars($b['phone']) ?></span>
                    </div>
                    <?php if($b['note']): ?>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Ghi chú</span>
                        <span class="bk-info-val" style="color:#718096;font-style:italic"><?= htmlspecialchars($b['note']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Cột 2: Chi tiết đặt phòng -->
                <div class="bk-col">
                    <div class="bk-col-title">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25"/></svg>
                        Chi tiết đặt phòng
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Loại phòng</span>
                        <span class="bk-info-val"><?= htmlspecialchars($b['room_name'] ?? '?') ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Nhận phòng</span>
                        <span class="bk-info-val"><?= $ci ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Trả phòng</span>
                        <span class="bk-info-val"><?= $co ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Số đêm</span>
                        <span class="bk-info-val"><?= $nights ?> đêm</span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Ngày đặt</span>
                        <span class="bk-info-val"><?= $created_date ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Giờ đặt</span>
                        <span class="bk-info-val"><?= $created_time ?></span>
                    </div>
                </div>

                <!-- Cột 3: Thanh toán -->
                <div class="bk-col">
                    <div class="bk-col-title">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                        Thanh toán
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Phương thức</span>
                        <span class="bk-info-val"><?= $pm[1] ?> <?= $pm[0] ?></span>
                    </div>
                    <div class="bk-info-row">
                        <span class="bk-info-label">Giá/đêm</span>
                        <span class="bk-info-val"><?= $b['room_price'] ? number_format($b['room_price'],0,',','.') . ' VNĐ' : '?' ?></span>
                    </div>
                    <div class="bk-total-box">
                        <span>Tổng tiền</span>
                        <strong><?= number_format($b['total_price'],0,',','.') ?> VNĐ</strong>
                    </div>

                    <?php if ($refund_status === 1): ?>
                        <div class="refund-notice refund-pending">
                            ⏳ Đang chờ admin xử lý hoàn tiền
                        </div>

                    <?php elseif ($refund_status === 2): ?>
                        <div class="refund-notice refund-approved">
                            ✅ Hoàn tiền đã được duyệt — <?= number_format($b['refund_amount'],0,',','.') ?> VNĐ
                        </div>

                    <?php elseif ($refund_status === 3): ?>
                        <div class="refund-notice refund-rejected">
                            ❌ Yêu cầu hoàn tiền đã bị từ chối
                        </div>

                    <?php elseif ($can_refund): ?>
                        <div class="refund-info-box">
                            <div class="refund-info-text">
                                Hoàn tiền sẽ nhận được:
                                <strong style="color:#276749"><?= number_format($refund_amount,0,',','.') ?> VNĐ</strong><br>
                                <span style="color:#c53030;font-size:11px">(trừ 20% phí hủy = <?= number_format($refund_fee,0,',','.') ?> VNĐ)</span>
                            </div>
                            <a href="javascript:void(0)"
                               class="btn-refund-action"
                               onclick="showRefundModal('request_refund.php?id=<?= $b['id'] ?>', '<?= number_format($refund_amount,0,',','.') ?>', '<?= number_format($refund_fee,0,',','.') ?>')">
                                💰 Yêu cầu hoàn tiền
                            </a>
                        </div>

                    <?php elseif ($b['status'] === 'confirmed'): ?>
                        <div class="refund-notice refund-toolate">
                            ⚠️ Không thể hoàn tiền (check-in trong vòng 24h)
                        </div>

                    <?php elseif ($b['status'] === 'pending'): ?>
                        <div class="refund-notice" style="background:#fffbeb;border:1.5px solid #fbd38d;color:#b7791f">
                            ⏳ Chờ khách sạn xác nhận đơn
                        </div>
                        <a href="javascript:void(0)"
                           onclick="confirmCancel(<?= $b['id'] ?>, '<?= htmlspecialchars($b['order_code'], ENT_QUOTES) ?>')"
                           class="btn-cancel-action">
                            ❌ Hủy đơn (miễn phí)
                        </a>

                    <?php endif; ?>

                </div>

            </div><!-- .bk-card-body -->

        </div><!-- .bk-card -->
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- Modal hoàn tiền -->
<div id="refundModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:32px 28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);text-align:center;animation:popIn .2s ease">
        <div style="width:64px;height:64px;background:#fff5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px">💸</div>
        <h3 style="font-size:18px;font-weight:800;color:#1a202c;margin:0 0 6px">Xác nhận hoàn tiền?</h3>
        <p style="font-size:13px;color:#718096;margin:0 0 20px">Yêu cầu sẽ được gửi đến admin và xử lý<br>trong <strong>1–3 ngày làm việc</strong>.</p>
        <div style="background:#f7fafc;border-radius:12px;padding:16px;margin-bottom:20px;text-align:left">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px">
                <span style="color:#718096">Phí hủy (20%)</span>
                <span id="modal-fee" style="font-weight:700;color:#c53030"></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0 4px;font-size:14px">
                <span style="font-weight:700;color:#2d3748">Hoàn lại cho bạn</span>
                <span id="modal-amount" style="font-size:17px;font-weight:800;color:#276749"></span>
            </div>
        </div>
        <div style="display:flex;gap:10px">
            <button onclick="closeRefundModal()"
                style="flex:1;padding:12px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;color:#718096;font-size:14px;font-weight:600;cursor:pointer"
                onmouseover="this.style.background='#f1f5f9'"
                onmouseout="this.style.background='#fff'">
                Hủy bỏ
            </button>
            <a id="modal-confirm-btn" href="#"
                style="flex:1;padding:12px;border-radius:10px;background:#e53e3e;color:#fff;font-size:14px;font-weight:700;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px"
                onmouseover="this.style.background='#c53030'"
                onmouseout="this.style.background='#e53e3e'">
                ✅ Xác nhận
            </a>
        </div>
    </div>
</div>

<!-- Modal hủy đơn pending -->
<div id="cancelModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:32px 28px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);text-align:center;animation:popIn .2s ease">
        <div style="width:64px;height:64px;background:#fff5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px">🗑️</div>
        <h3 style="font-size:18px;font-weight:800;color:#1a202c;margin:0 0 6px">Hủy đơn đặt phòng?</h3>
        <p style="font-size:13px;color:#718096;margin:0 0 8px">Bạn sắp hủy đơn <strong id="cancel-order-code"></strong></p>
        <div style="background:#f0fff4;border:1.5px solid #9ae6b4;border-radius:10px;padding:10px 14px;margin-bottom:20px;font-size:13px;color:#276749;font-weight:600">
            ✅ Đơn chưa thanh toán — hủy hoàn toàn miễn phí!
        </div>
        <div style="display:flex;gap:10px">
            <button onclick="closeCancelModal()"
                style="flex:1;padding:12px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;color:#718096;font-size:14px;font-weight:600;cursor:pointer"
                onmouseover="this.style.background='#f1f5f9'"
                onmouseout="this.style.background='#fff'">
                Giữ đơn
            </button>
            <a id="cancel-confirm-btn" href="#"
                style="flex:1;padding:12px;border-radius:10px;background:#e53e3e;color:#fff;font-size:14px;font-weight:700;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px"
                onmouseover="this.style.background='#c53030'"
                onmouseout="this.style.background='#e53e3e'">
                ✅ Xác nhận hủy
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>