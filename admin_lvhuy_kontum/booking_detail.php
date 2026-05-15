<?php
include "../config/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: bookings.php");
    exit;
}

$booking = $conn->query("
    SELECT b.*,
        u.full_name AS user_full_name, u.email AS user_email, u.phone AS user_phone, u.id AS user_id,
        r.room_name, r.price AS room_price, r.id AS room_id,
        h.name AS hotel_name, h.address AS hotel_address, h.id AS hotel_id
    FROM bookings b
    LEFT JOIN users  u  ON b.user_id  = u.id
    LEFT JOIN rooms  r  ON b.room_id  = r.id
    LEFT JOIN hotels h  ON r.hotel_id = h.id
    WHERE b.id = $id
")->fetch_assoc();

if (!$booking) {
    header("Location: bookings.php?error=notfound");
    exit;
}

// Lấy lịch sử thanh toán
$payments = $conn->query("
    SELECT * FROM payments WHERE booking_id = $id ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Lấy timeline log
$logs = $conn->query("
    SELECT * FROM booking_logs WHERE booking_id = $id ORDER BY created_at ASC
")->fetch_all(MYSQLI_ASSOC);

$page_title    = 'Chi tiết đơn #' . htmlspecialchars($booking['order_code']);
$page_subtitle = 'Xem toàn bộ thông tin đặt phòng';
include "../includes/admin_header.php";

$status_map = [
    'pending'   => ['Chờ xác nhận', '#b7791f', '#fffbeb'],
    'confirmed' => ['Đã xác nhận',  '#1e73be', '#ebf8ff'],
    'cancelled' => ['Đã huỷ',       '#c53030', '#fff5f5'],
    'completed' => ['Hoàn thành',   '#276749', '#f0fff4'],
];

$HOTEL_PAY_METHODS = ['hotel', 'Thanh toán tại khách sạn'];
$is_hotel_pay  = in_array($booking['payment_method'] ?? '', $HOTEL_PAY_METHODS);
$is_online_pay = !$is_hotel_pay;

// pending + online = chờ thanh toán (không phải chờ admin xác nhận)
if ($booking['status'] === 'pending' && $is_online_pay) {
    $sm = ['⏳ Chờ thanh toán', '#6b46c1', '#faf5ff'];
} else {
    $sm = $status_map[$booking['status']] ?? ['Không rõ', '#718096', '#f7fafc'];
}

$pay_map = ['bank'=>'Chuyển khoản','momo'=>'MoMo','vnpay'=>'VNPay','hotel'=>'Tại khách sạn','card'=>'Thẻ tín dụng'];

$nights = 0;
if ($booking['check_in'] && $booking['check_out']) {
    $nights = (int)round((strtotime($booking['check_out']) - strtotime($booking['check_in'])) / 86400);
}

$refund_status_map = [
    0 => null,
    1 => ['Chờ duyệt hoàn tiền', '#b7791f', '#fffbeb'],
    2 => ['Đã hoàn tiền',        '#276749', '#f0fff4'],
    3 => ['Từ chối hoàn tiền',   '#c53030', '#fff5f5'],
];
$rs = $refund_status_map[(int)($booking['refund_requested'] ?? 0)];
?>

<!-- Back button -->
<div style="margin-bottom:18px">
    <a href="bookings.php" style="display:inline-flex;align-items:center;gap:6px;color:#4a5568;text-decoration:none;font-size:13.5px;font-weight:600;
        padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;background:#fff;transition:background .15s"
        onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
        ← Quay lại danh sách
    </a>
</div>

<!-- Header card -->
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:24px 28px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
        <div style="font-size:11px;color:#a0aec0;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Mã đơn</div>
        <div style="font-size:22px;font-weight:800;color:#1a202c"><?= htmlspecialchars($booking['order_code']) ?></div>
        <div style="font-size:12px;color:#a0aec0;margin-top:4px">
            Đặt lúc: <?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="padding:8px 18px;border-radius:20px;font-size:13.5px;font-weight:700;
            color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;border:1.5px solid <?= $sm[1] ?>33">
            <?= $sm[0] ?>
        </span>
        <?php if ($rs): ?>
        <span style="padding:8px 18px;border-radius:20px;font-size:13px;font-weight:700;
            color:<?= $rs[1] ?>;background:<?= $rs[2] ?>;border:1.5px solid <?= $rs[1] ?>33">
            💰 <?= $rs[0] ?>
        </span>
        <?php endif; ?>
        <!-- Nút hành động -->
        <?php
        $s = $booking['status'];
        $is_pending   = ($s === 'pending');
        $is_confirmed = in_array($s, ['confirmed', 'paid', 'approved']);
        ?>
        <?php if ($is_pending): ?>
            <?php if ($is_hotel_pay): ?>
            <form id="bd_confirm" method="POST" action="update_booking.php" style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $booking['id'] ?>">
                <input type="hidden" name="status" value="confirmed">
                <input type="hidden" name="redirect" value="detail">
            </form>
            <button type="button" class="btn btn-confirm"
                onclick="adminConfirmPost('Xác nhận còn phòng cho đơn này?', 'bd_confirm', '✅')">
                ✅ Xác nhận
            </button>
            <?php else: ?>
            <span style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#faf5ff;color:#6b46c1;border-radius:9px;font-size:12.5px;font-weight:600;border:1px solid #d6bcfa">
                ⏳ Hệ thống tự xác nhận khi thanh toán
            </span>
            <?php endif; ?>
            <form id="bd_cancel" method="POST" action="update_booking.php" style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $booking['id'] ?>">
                <input type="hidden" name="status" value="cancelled">
                <input type="hidden" name="redirect" value="detail">
            </form>
            <button type="button" class="btn btn-cancel"
                onclick="adminConfirmPost('Huỷ đơn này?', 'bd_cancel', '❌')">
                ❌ Huỷ
            </button>
        <?php elseif ($is_confirmed): ?>
            <form id="bd_complete" method="POST" action="update_booking.php" style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $booking['id'] ?>">
                <input type="hidden" name="status" value="completed">
                <input type="hidden" name="redirect" value="detail">
            </form>
            <button type="button" class="btn btn-edit"
                onclick="adminConfirmPost('Đánh dấu hoàn thành?', 'bd_complete', '🎉')">
                🎉 Hoàn thành
            </button>
            <form id="bd_cancel2" method="POST" action="update_booking.php" style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $booking['id'] ?>">
                <input type="hidden" name="status" value="cancelled">
                <input type="hidden" name="redirect" value="detail">
            </form>
            <button type="button" class="btn btn-cancel"
                onclick="adminConfirmPost('Huỷ đơn này?', 'bd_cancel2', '❌')">
                ❌ Huỷ
            </button>
        <?php endif; ?>
        <a href="booking_edit.php?id=<?= $booking['id'] ?>"
           style="display:inline-flex;align-items:center;gap:5px;padding:8px 16px;background:#f8fafc;color:#4a5568;border:1.5px solid #e2e8f0;border-radius:9px;text-decoration:none;font-size:13px;font-weight:600">
            ✏️ Sửa
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px">

    <!-- Thông tin khách hàng -->
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
            👤 Thông tin khách hàng
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <tr>
                <td style="padding:6px 0;color:#718096;width:38%">Họ tên</td>
                <td style="padding:6px 0;font-weight:600;color:#1a202c"><?= htmlspecialchars($booking['full_name'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Email</td>
                <td style="padding:6px 0;color:#1a202c"><?= htmlspecialchars($booking['email'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Điện thoại</td>
                <td style="padding:6px 0;color:#1a202c"><?= htmlspecialchars($booking['phone'] ?? '—') ?></td>
            </tr>
            <?php if ($booking['user_id']): ?>
            <tr>
                <td style="padding:6px 0;color:#718096">Tài khoản</td>
                <td style="padding:6px 0">
                    <a href="edit_user.php?action=edit&id=<?= $booking['user_id'] ?>"
                       style="color:#1e73be;font-size:12.5px;font-weight:600">
                        Xem tài khoản →
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Thông tin khách sạn / phòng -->
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
            🏨 Khách sạn & Phòng
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <tr>
                <td style="padding:6px 0;color:#718096;width:38%">Khách sạn</td>
                <td style="padding:6px 0;font-weight:600;color:#1a202c"><?= htmlspecialchars($booking['hotel_name'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Địa chỉ</td>
                <td style="padding:6px 0;color:#718096;font-size:12.5px"><?= htmlspecialchars($booking['hotel_address'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Loại phòng</td>
                <td style="padding:6px 0;color:#1a202c"><?= htmlspecialchars($booking['room_name'] ?? '—') ?></td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Giá phòng/đêm</td>
                <td style="padding:6px 0;color:#1a202c"><?= $booking['room_price'] ? number_format($booking['room_price'], 0, ',', '.') . 'đ' : '—' ?></td>
            </tr>
        </table>
    </div>

    <!-- Thông tin lịch -->
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
            📅 Lịch nhận / trả phòng
        </div>
        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center;margin-bottom:14px">
            <div style="text-align:center;padding:14px;background:#f8fafc;border-radius:10px;border:1.5px solid #e2e8f0">
                <div style="font-size:11px;color:#a0aec0;font-weight:600;margin-bottom:4px">NHẬN PHÒNG</div>
                <div style="font-size:17px;font-weight:800;color:#276749">
                    <?= $booking['check_in'] ? date('d/m/Y', strtotime($booking['check_in'])) : '—' ?>
                </div>
                <?php if ($booking['check_in']): ?>
                <div style="font-size:11px;color:#a0aec0;margin-top:3px"><?= date('l', strtotime($booking['check_in'])) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:center">
                <div style="font-size:12px;font-weight:700;color:#a0aec0"><?= $nights ?> đêm</div>
                <div style="color:#cbd5e0;font-size:18px">→</div>
            </div>
            <div style="text-align:center;padding:14px;background:#f8fafc;border-radius:10px;border:1.5px solid #e2e8f0">
                <div style="font-size:11px;color:#a0aec0;font-weight:600;margin-bottom:4px">TRẢ PHÒNG</div>
                <div style="font-size:17px;font-weight:800;color:#c53030">
                    <?= $booking['check_out'] ? date('d/m/Y', strtotime($booking['check_out'])) : '—' ?>
                </div>
                <?php if ($booking['check_out']): ?>
                <div style="font-size:11px;color:#a0aec0;margin-top:3px"><?= date('l', strtotime($booking['check_out'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Thanh toán -->
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px">
        <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
            💳 Thanh toán
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <tr>
                <td style="padding:6px 0;color:#718096;width:45%">Phương thức</td>
                <td style="padding:6px 0;font-weight:600;color:#1a202c">
                    <?= htmlspecialchars($pay_map[$booking['payment_method']] ?? strtoupper($booking['payment_method'])) ?>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Số đêm</td>
                <td style="padding:6px 0;color:#1a202c"><?= $nights ?> đêm</td>
            </tr>
            <?php if ($booking['refund_amount'] > 0 && (int)$booking['refund_requested'] >= 2): ?>
            <tr>
                <td style="padding:6px 0;color:#718096">Phí huỷ (20%)</td>
                <td style="padding:6px 0;color:#c53030;font-weight:600">
                    -<?= number_format($booking['total_price'] * 0.2, 0, ',', '.') ?>đ
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#718096">Hoàn lại</td>
                <td style="padding:6px 0;color:#276749;font-weight:700">
                    <?= number_format($booking['refund_amount'], 0, ',', '.') ?>đ
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td style="padding:10px 0 4px;color:#1a202c;font-weight:700;border-top:1.5px solid #f1f5f9">Tổng cộng</td>
                <td style="padding:10px 0 4px;font-size:18px;font-weight:800;color:#1a202c;border-top:1.5px solid #f1f5f9">
                    <?= number_format($booking['total_price'], 0, ',', '.') ?>đ
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- Ghi chú -->
<?php if ($booking['note']): ?>
<div style="background:#fffbeb;border-radius:14px;border:1.5px solid #fde68a;padding:20px 24px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:700;color:#b7791f;margin-bottom:8px">📝 Ghi chú của khách</div>
    <div style="font-size:13.5px;color:#744210;line-height:1.7"><?= nl2br(htmlspecialchars($booking['note'])) ?></div>
</div>
<?php endif; ?>

<!-- Lịch sử thanh toán -->
<?php if (!empty($payments)): ?>
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
        🧾 Lịch sử giao dịch
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f8fafc">
                <th style="padding:10px 12px;text-align:left;color:#718096;font-weight:600">Mã GD</th>
                <th style="padding:10px 12px;text-align:left;color:#718096;font-weight:600">Số tiền</th>
                <th style="padding:10px 12px;text-align:left;color:#718096;font-weight:600">Phương thức</th>
                <th style="padding:10px 12px;text-align:left;color:#718096;font-weight:600">Trạng thái</th>
                <th style="padding:10px 12px;text-align:left;color:#718096;font-weight:600">Thời gian</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $ps_map = ['pending'=>['Chờ xử lý','#b7791f','#fffbeb'],'paid'=>['Đã thanh toán','#276749','#f0fff4'],'failed'=>['Thất bại','#c53030','#fff5f5'],'refunded'=>['Đã hoàn tiền','#1e73be','#ebf8ff']];
        foreach ($payments as $p):
            $ps = $ps_map[$p['payment_status']] ?? ['Không rõ','#718096','#f7fafc'];
        ?>
        <tr style="border-bottom:1px solid #f1f5f9">
            <td style="padding:10px 12px;color:#a0aec0;font-size:11.5px"><?= htmlspecialchars($p['transaction_id'] ?? '—') ?></td>
            <td style="padding:10px 12px;font-weight:700;color:#1a202c"><?= number_format($p['amount'] ?? 0, 0, ',', '.') ?>đ</td>
            <td style="padding:10px 12px;color:#4a5568"><?= htmlspecialchars($pay_map[$p['payment_method'] ?? ''] ?? strtoupper($p['payment_method'] ?? '')) ?></td>
            <td style="padding:10px 12px">
                <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;color:<?= $ps[1] ?>;background:<?= $ps[2] ?>">
                    <?= $ps[0] ?>
                </span>
            </td>
            <td style="padding:10px 12px;color:#a0aec0;font-size:12px"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Finance info (platform_revenue / hotel_payout) -->
<?php if (!empty($booking['platform_revenue']) || !empty($booking['hotel_payout'])): ?>
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
        💰 Tài chính nền tảng
    </div>
    <?php
    $payout_status_map = [
        'HOLDING' => ['Đang giữ',        '#b7791f', '#fffbeb'],
        'READY'   => ['Sẵn sàng giải ngân','#1e73be', '#ebf8ff'],
        'FROZEN'  => ['Đóng băng',        '#c53030', '#fff5f5'],
        'PAID'    => ['Đã giải ngân',     '#276749', '#f0fff4'],
    ];
    $ps = $booking['payout_status'] ?? 'HOLDING';
    $psm = $payout_status_map[$ps] ?? $payout_status_map['HOLDING'];
    ?>
    <table style="width:100%;border-collapse:collapse;font-size:13.5px">
        <tr>
            <td style="padding:6px 0;color:#718096;width:40%">Hoa hồng (%)</td>
            <td style="padding:6px 0;font-weight:600;color:#c53030">
                <?= number_format($booking['commission_rate'] ?? 0, 1) ?>%
            </td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#718096">Platform thu</td>
            <td style="padding:6px 0;color:#c53030;font-weight:600">
                <?= number_format($booking['platform_revenue'] ?? 0, 0, ',', '.') ?>đ
            </td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#718096">Giải ngân cho hotel</td>
            <td style="padding:6px 0;color:#276749;font-weight:700;font-size:15px">
                <?= number_format($booking['hotel_payout'] ?? 0, 0, ',', '.') ?>đ
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0 4px;color:#718096;border-top:1.5px solid #f1f5f9">Trạng thái payout</td>
            <td style="padding:8px 0 4px;border-top:1.5px solid #f1f5f9">
                <span style="padding:4px 12px;border-radius:20px;font-size:12.5px;font-weight:700;
                      color:<?= $psm[1] ?>;background:<?= $psm[2] ?>">
                    <?= $psm[0] ?>
                </span>
                <?php if ($ps === 'READY'): ?>
                <a href="payout.php?hotel_id=<?= $booking['hotel_id'] ?>"
                   style="margin-left:10px;font-size:12px;color:#1e73be;text-decoration:none;font-weight:600">
                    💸 Đến trang giải ngân →
                </a>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
<?php endif; ?>

<!-- Booking Timeline Log -->
<?php if (!empty($logs)): ?>
<div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:22px 24px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:20px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
        📋 Timeline hoạt động
    </div>
    <div style="position:relative">
        <!-- Đường dọc -->
        <div style="position:absolute;left:17px;top:8px;bottom:8px;width:2px;background:#e2e8f0"></div>
        <?php
        $actor_icon = [
            'ADMIN'  => ['⚙️', '#1e73be', '#ebf8ff'],
            'SYSTEM' => ['🤖', '#718096', '#f7fafc'],
            'USER'   => ['👤', '#276749', '#f0fff4'],
            'GUEST'  => ['🧳', '#b7791f', '#fffbeb'],
            'HOTEL'  => ['🏨', '#6d28d9', '#f5f3ff'],
        ];
        $action_label = [
            'BOOKING_CREATED'    => 'Đặt phòng được tạo',
            'PAYMENT_SUCCESS'    => 'Thanh toán thành công',
            'BOOKING_CONFIRMED'  => 'Đơn được xác nhận',
            'GUEST_CHECKED_IN'   => 'Khách nhận phòng',
            'BOOKING_COMPLETED'  => 'Booking hoàn thành',
            'BOOKING_CANCELLED'  => 'Booking bị huỷ',
            'PAYOUT_PROCESSED'   => 'Đã giải ngân cho hotel',
            'REFUND_REQUESTED'   => 'Yêu cầu hoàn tiền',
            'REFUND_APPROVED'    => 'Hoàn tiền được duyệt',
            'DISPUTE_OPENED'     => 'Khiếu nại được mở',
            'DISPUTE_RESOLVED'   => 'Khiếu nại được đóng',
        ];
        foreach ($logs as $log):
            $ai = $actor_icon[$log['actor_type']] ?? $actor_icon['SYSTEM'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px;position:relative">
            <!-- Icon -->
            <div style="width:36px;height:36px;border-radius:50%;background:<?= $ai[2] ?>;
                        display:flex;align-items:center;justify-content:center;
                        font-size:16px;flex-shrink:0;z-index:1;border:2px solid #fff;
                        box-shadow:0 0 0 2px <?= $ai[1] ?>33">
                <?= $ai[0] ?>
            </div>
            <!-- Content -->
            <div style="flex:1;padding-top:4px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span style="font-weight:700;font-size:13.5px;color:#1a202c">
                        <?= htmlspecialchars($action_label[$log['action']] ?? $log['action']) ?>
                    </span>
                    <span style="font-size:11.5px;color:#a0aec0">
                        <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                    </span>
                    <?php if (!empty($log['actor_name'])): ?>
                    <span style="font-size:11px;padding:2px 8px;border-radius:10px;
                          background:<?= $ai[2] ?>;color:<?= $ai[1] ?>;font-weight:600">
                        <?= htmlspecialchars($log['actor_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($log['description'])): ?>
                <div style="font-size:13px;color:#4a5568;margin-top:4px">
                    <?= htmlspecialchars($log['description']) ?>
                </div>
                <?php endif; ?>
                <?php
                // Hiển thị metadata nếu có (chỉ show thông tin không nhạy cảm)
                if (!empty($log['metadata'])) {
                    $meta = json_decode($log['metadata'], true);
                    if (is_array($meta) && isset($meta['commission_rate'])) {
                        echo '<div style="margin-top:6px;font-size:12px;color:#718096">';
                        echo 'Hoa hồng: ' . number_format($meta['commission_rate'], 1) . '%';
                        if (isset($meta['platform_revenue'])) {
                            echo ' · Platform: ' . number_format($meta['platform_revenue'], 0, ',', '.') . 'đ';
                        }
                        if (isset($meta['hotel_payout'])) {
                            echo ' · Hotel nhận: ' . number_format($meta['hotel_payout'], 0, ',', '.') . 'đ';
                        }
                        echo '</div>';
                    } elseif (is_array($meta) && isset($meta['amount'])) {
                        echo '<div style="margin-top:6px;font-size:12px;color:#718096">';
                        echo 'Số tiền: ' . number_format($meta['amount'], 0, ',', '.') . 'đ';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include "../includes/admin_footer.php"; ?>
