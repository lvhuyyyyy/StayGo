<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_PATH . "/auth/login.php");
    exit();
}

$booking_id = (int)($_GET['id'] ?? 0);
$user_id    = $_SESSION['user_id'];

if (!$booking_id) {
    http_response_code(400); exit('Không tìm thấy đơn.');
}

$stmt = $conn->prepare("
    SELECT b.*,
           r.room_name, r.bed_type, r.max_guests,
           h.name AS hotel_name, h.address AS hotel_address,
           h.contact_phone AS hotel_phone,
           u.full_name AS user_fullname, u.email AS user_email
    FROM bookings b
    LEFT JOIN rooms   r ON b.room_id  = r.id
    LEFT JOIN hotels  h ON r.hotel_id = h.id
    LEFT JOIN users   u ON b.user_id  = u.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    http_response_code(403); exit('Không có quyền xem hóa đơn này.');
}

$method_labels = [
    'bank'  => 'Chuyển khoản ngân hàng',
    'momo'  => 'Ví MoMo',
    'vnpay' => 'VNPay',
    'payos' => 'PayOS',
    'hotel' => 'Thanh toán tại khách sạn',
    'card'  => 'Thẻ quốc tế',
];
$method = $method_labels[$b['payment_method'] ?? ''] ?? ($b['payment_method'] ?? '—');
$nights = max(1, (int)((strtotime($b['check_out']) - strtotime($b['check_in'])) / 86400));
$price_per_night = $nights > 0 ? round($b['total_price'] / $nights) : $b['total_price'];
$status_map = [
    'confirmed'  => ['Đã xác nhận', '#276749'],
    'completed'  => ['Hoàn thành',  '#1e40af'],
    'pending'    => ['Chờ xác nhận','#b7791f'],
    'cancelled'  => ['Đã hủy',      '#c53030'],
    'checked_in' => ['Đang ở',      '#6b46c1'],
];
[$status_text, $status_color] = $status_map[$b['status']] ?? ['Không rõ', '#718096'];
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hóa đơn #<?= htmlspecialchars($b['order_code']) ?> - StayGo</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Segoe UI',Arial,sans-serif; background:#f4f6f9; color:#2d3748; }

/* Nút in — ẩn khi in */
.print-bar {
    background:#1e3a5f; padding:14px 32px; display:flex;
    align-items:center; justify-content:space-between;
}
.print-bar h1 { color:#fff; font-size:17px; font-weight:700; }
.print-bar-actions { display:flex; gap:10px; }
.btn-print {
    background:#3182ce; color:#fff; border:none; padding:9px 20px;
    border-radius:8px; font-size:13.5px; font-weight:700; cursor:pointer;
}
.btn-back {
    background:transparent; color:rgba(255,255,255,.75); border:1.5px solid rgba(255,255,255,.3);
    padding:9px 20px; border-radius:8px; font-size:13.5px; font-weight:600;
    cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;
}

/* Invoice container */
.invoice-wrap {
    max-width:760px; margin:32px auto; background:#fff;
    border-radius:16px; overflow:hidden;
    box-shadow:0 4px 24px rgba(0,0,0,.1);
}

/* Header */
.inv-header {
    background:linear-gradient(135deg,#1e3a5f,#1e73be);
    padding:32px 40px; display:flex; justify-content:space-between; align-items:flex-start;
}
.inv-brand h2 { color:#fff; font-size:26px; font-weight:900; letter-spacing:-.5px; }
.inv-brand p  { color:rgba(255,255,255,.65); font-size:12.5px; margin-top:3px; }
.inv-meta     { text-align:right; }
.inv-meta .inv-num  { color:#fff; font-size:20px; font-weight:800; }
.inv-meta .inv-date { color:rgba(255,255,255,.7); font-size:12px; margin-top:4px; }
.inv-status {
    display:inline-block; margin-top:8px; padding:4px 12px; border-radius:20px;
    font-size:12px; font-weight:700; background:rgba(255,255,255,.2); color:#fff;
}

/* Body */
.inv-body { padding:36px 40px; }

/* Info grid */
.inv-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:28px; }
.inv-info-box { background:#f7fafc; border-radius:12px; padding:18px; }
.inv-info-box h4 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#a0aec0; margin-bottom:10px; }
.inv-info-row { display:flex; flex-direction:column; margin-bottom:6px; }
.inv-info-row .lbl { font-size:11.5px; color:#718096; }
.inv-info-row .val { font-size:13.5px; font-weight:600; color:#2d3748; }

/* Table */
.inv-table-wrap { margin-bottom:24px; }
.inv-table-wrap h4 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#a0aec0; margin-bottom:10px; }
.inv-table { width:100%; border-collapse:collapse; }
.inv-table th {
    background:#f7fafc; padding:10px 14px; text-align:left;
    font-size:11.5px; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:.4px;
}
.inv-table td { padding:12px 14px; border-top:1px solid #f0f4f8; font-size:13.5px; }
.inv-table tr:last-child td { border-top:2px solid #e2e8f0; font-weight:800; font-size:15px; color:#1e73be; }
.inv-table .right { text-align:right; }

/* Footer note */
.inv-note {
    background:#ebf8ff; border-radius:10px; padding:14px 18px;
    font-size:12.5px; color:#2b6cb0; line-height:1.6;
}
.inv-footer-bar {
    background:#f7fafc; border-top:1px solid #e2e8f0;
    padding:18px 40px; font-size:12px; color:#a0aec0; text-align:center;
}

@media print {
    .print-bar { display:none !important; }
    body { background:#fff; }
    .invoice-wrap { box-shadow:none; margin:0; border-radius:0; }
    .inv-note { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .inv-header { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}

@media (max-width: 600px) {
    .inv-grid { grid-template-columns:1fr; }
    .inv-header { flex-direction:column; gap:16px; }
    .inv-meta { text-align:left; }
    .inv-body { padding:24px 20px; }
}
</style>
</head>
<body>

<!-- Action bar (ẩn khi in) -->
<div class="print-bar">
    <h1>🧾 Hóa đơn đặt phòng</h1>
    <div class="print-bar-actions">
        <a href="javascript:history.back()" class="btn-back">← Quay lại</a>
        <button class="btn-print" onclick="window.print()">🖨️ In / Lưu PDF</button>
    </div>
</div>

<!-- Invoice -->
<div class="invoice-wrap">

    <!-- Header -->
    <div class="inv-header">
        <div class="inv-brand">
            <h2>🏨 StayGo</h2>
            <p>Nền tảng đặt phòng khách sạn</p>
            <p style="margin-top:2px">146 Nguyễn Văn Cừ, Vũng Tàu</p>
        </div>
        <div class="inv-meta">
            <div class="inv-num">#<?= htmlspecialchars($b['order_code']) ?></div>
            <div class="inv-date">Ngày đặt: <?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></div>
            <div class="inv-status" style="background:<?= $status_color ?>33;color:#fff"><?= $status_text ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="inv-body">

        <!-- Info grid -->
        <div class="inv-grid">
            <div class="inv-info-box">
                <h4>Thông tin khách hàng</h4>
                <div class="inv-info-row">
                    <span class="lbl">Họ và tên</span>
                    <span class="val"><?= htmlspecialchars($b['full_name']) ?></span>
                </div>
                <div class="inv-info-row">
                    <span class="lbl">Email</span>
                    <span class="val"><?= htmlspecialchars($b['email']) ?></span>
                </div>
                <?php if ($b['phone']): ?>
                <div class="inv-info-row">
                    <span class="lbl">Điện thoại</span>
                    <span class="val"><?= htmlspecialchars($b['phone']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="inv-info-box">
                <h4>Thông tin khách sạn</h4>
                <div class="inv-info-row">
                    <span class="lbl">Tên khách sạn</span>
                    <span class="val"><?= htmlspecialchars($b['hotel_name']) ?></span>
                </div>
                <div class="inv-info-row">
                    <span class="lbl">Địa chỉ</span>
                    <span class="val"><?= htmlspecialchars($b['hotel_address'] ?? '—') ?></span>
                </div>
                <?php if ($b['hotel_phone']): ?>
                <div class="inv-info-row">
                    <span class="lbl">Điện thoại KS</span>
                    <span class="val"><?= htmlspecialchars($b['hotel_phone']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chi tiết dịch vụ -->
        <div class="inv-table-wrap">
            <h4>Chi tiết dịch vụ</h4>
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>Mô tả</th>
                        <th>Nhận phòng</th>
                        <th>Trả phòng</th>
                        <th>Số đêm</th>
                        <th class="right">Đơn giá</th>
                        <th class="right">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($b['room_name']) ?></strong>
                            <?php if ($b['bed_type']): ?>
                            <div style="font-size:12px;color:#718096"><?= htmlspecialchars($b['bed_type']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($b['check_in'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($b['check_out'])) ?></td>
                        <td><?= $nights ?></td>
                        <td class="right"><?= number_format($price_per_night, 0, ',', '.') ?>đ</td>
                        <td class="right"><?= number_format($b['total_price'], 0, ',', '.') ?>đ</td>
                    </tr>
                    <tr>
                        <td colspan="5">Tổng cộng</td>
                        <td class="right"><?= number_format($b['total_price'], 0, ',', '.') ?> VNĐ</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phương thức thanh toán -->
        <div style="display:flex;justify-content:space-between;align-items:center;background:#f7fafc;border-radius:10px;padding:14px 18px;margin-bottom:20px">
            <span style="font-size:13px;color:#718096;font-weight:600">Phương thức thanh toán</span>
            <span style="font-size:13.5px;font-weight:700;color:#2d3748"><?= $method ?></span>
        </div>

        <!-- Ghi chú -->
        <div class="inv-note">
            📌 Vui lòng xuất trình hóa đơn này (bản in hoặc ảnh chụp màn hình) khi đến nhận phòng.<br>
            Mọi thắc mắc vui lòng liên hệ <strong>StayGo</strong> qua email <strong>lvhuy.k22tt@kontum.udn.vn</strong> hoặc hotline <strong>037 384 8395</strong>.
        </div>
    </div>

    <div class="inv-footer-bar">
        © 2026 StayGo — Hóa đơn điện tử, không có giá trị tài chính &nbsp;|&nbsp; Mã xác nhận: <?= htmlspecialchars($b['order_code']) ?>
    </div>
</div>

</body>
</html>
