<?php
include "../config/database.php";

// ── Xử lý hành động giải ngân POST ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'payout') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $note       = $conn->real_escape_string(trim($_POST['note'] ?? ''));
    $admin_id   = (int)$_SESSION['admin_id'];
    $admin_name = $conn->real_escape_string($_SESSION['admin_name'] ?? 'Admin');

    if ($booking_id > 0) {
        $b = $conn->query("
            SELECT b.*, r.hotel_id, h.name AS hotel_name
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN hotels h ON r.hotel_id = h.id
            WHERE b.id = $booking_id AND b.payout_status = 'READY'
        ")->fetch_assoc();

        if ($b) {
            $hotel_id         = (int)$b['hotel_id'];
            $amount           = (float)$b['hotel_payout'];
            $commission_amount = (float)$b['platform_revenue'];

            // Ghi vào bảng payouts
            $conn->query("
                INSERT INTO payouts (hotel_id, booking_id, amount, commission_amount,
                                     processed_by_admin_id, processed_by_name, note)
                VALUES ($hotel_id, $booking_id, $amount, $commission_amount,
                        $admin_id, '$admin_name', '$note')
            ");

            // Cập nhật payout_status → PAID
            $conn->query("UPDATE bookings SET payout_status='PAID' WHERE id=$booking_id");

            // Ghi booking_log
            $desc = $conn->real_escape_string("Giải ngân " . number_format($amount, 0, ',', '.') . "đ cho " . $b['hotel_name']);
            $meta = json_encode(['amount' => $amount, 'commission' => $commission_amount, 'admin' => $_SESSION['admin_name'] ?? 'Admin']);
            $meta_esc = $conn->real_escape_string($meta);
            $conn->query("
                INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description, metadata)
                VALUES ($booking_id, 'ADMIN', $admin_id, '$admin_name', 'PAYOUT_PROCESSED', '$desc', '$meta_esc')
            ");

            $redirect_hotel = isset($_POST['hotel_id']) ? '?hotel_id=' . (int)$_POST['hotel_id'] : '';
            header("Location: payout.php{$redirect_hotel}&success=payout");
            exit;
        }
    }
    header("Location: payout.php?error=invalid");
    exit;
}

// ── Auto-complete: chuyển booking quá check_out → completed + READY ──
// Chạy mỗi lần trang load — không cần cron hay trigger thủ công.
// HOLDING → READY ngay khi check_out qua vì không còn rủi ro hủy.
$_ac = $conn->query("
    SELECT b.id, b.total_price, b.payment_flow,
           b.commission_rate, h.commission_rate AS h_commission_rate
    FROM bookings b
    JOIN rooms r  ON b.room_id  = r.id
    JOIN hotels h ON r.hotel_id = h.id
    WHERE b.status = 'confirmed' AND b.check_out < CURDATE()
");
while ($_ac && $_row = $_ac->fetch_assoc()) {
    $_id = (int)$_row['id'];
    if (($_row['payment_flow'] ?? '') === 'hotel_collect') {
        $conn->query("UPDATE bookings SET status='completed', commission_rate=0, platform_revenue=0, hotel_payout=0 WHERE id=$_id");
    } else {
        $_rate   = (float)($_row['commission_rate'] ?: $_row['h_commission_rate'] ?? 10.0);
        $_rev    = round((float)$_row['total_price'] * $_rate / 100, 2);
        $_payout = round((float)$_row['total_price'] - $_rev, 2);
        $conn->query("UPDATE bookings SET status='completed', payout_status='READY', commission_rate=$_rate, platform_revenue=$_rev, hotel_payout=$_payout WHERE id=$_id");
    }
}
unset($_ac, $_row);

$page_title    = 'Giải ngân';
$page_subtitle = 'Quản lý giải ngân cho hotel';
include "../includes/admin_header.php";

// ── Lọc theo hotel ────────────────────────────────────────────────────
$filter_hotel = (int)($_GET['hotel_id'] ?? 0);

// Danh sách hotel để dropdown
$hotels_list = $conn->query("SELECT id, name FROM hotels ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// ── Lấy danh sách booking READY cần giải ngân ─────────────────────────
$hotel_cond = $filter_hotel ? "AND r.hotel_id = $filter_hotel" : '';

$ready_bookings = $conn->query("
    SELECT b.*,
           r.hotel_id, h.name AS hotel_name,
           r.room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN hotels h ON r.hotel_id = h.id
    WHERE b.payout_status = 'READY'
      AND b.payment_flow = 'platform_collect'
    $hotel_cond
    ORDER BY b.updated_at ASC
")->fetch_all(MYSQLI_ASSOC);

// ── Lịch sử giải ngân ─────────────────────────────────────────────────
$hist_cond = $filter_hotel ? "WHERE p.hotel_id = $filter_hotel" : '';

$payout_history = $conn->query("
    SELECT p.*, h.name AS hotel_name, b.order_code, b.full_name AS guest_name
    FROM payouts p
    JOIN hotels h ON p.hotel_id = h.id
    JOIN bookings b ON p.booking_id = b.id
    $hist_cond
    ORDER BY p.processed_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Tổng đã giải ngân (filter)
$total_hist_cond = $filter_hotel ? "WHERE p.hotel_id = $filter_hotel" : '';
$total_paid = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM payouts p $total_hist_cond")->fetch_assoc()['t'];

$success_msg = '';
if (isset($_GET['success']) && $_GET['success'] === 'payout') {
    $success_msg = '✅ Đã giải ngân thành công!';
}
$error_msg = '';
if (isset($_GET['error'])) {
    $error_msg = '⚠️ Yêu cầu không hợp lệ hoặc booking không ở trạng thái READY.';
}
?>

<?php if ($success_msg): ?>
<div style="padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#276749;background:#f0fff4;border:1px solid #276749">
    <?= $success_msg ?>
</div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div style="padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#c53030;background:#fff5f5;border:1px solid #c53030">
    <?= $error_msg ?>
</div>
<?php endif; ?>

<!-- ── Header + filter ───────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h2 style="font-size:20px;font-weight:800;color:#1a202c;margin:0">💸 Giải ngân cho Hotel</h2>
        <p style="color:#718096;font-size:13px;margin:4px 0 0">Danh sách booking đã completed và sẵn sàng giải ngân</p>
    </div>
    <form method="GET" style="display:flex;align-items:center;gap:8px">
        <select name="hotel_id"
                style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#2d3748;background:#fff;cursor:pointer"
                onchange="this.form.submit()">
            <option value="">🏨 Tất cả khách sạn</option>
            <?php foreach ($hotels_list as $hl): ?>
                <option value="<?= $hl['id'] ?>" <?= $filter_hotel === (int)$hl['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($hl['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="finance.php" style="padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#4a5568;font-size:13px;background:#fff">
            ← Finance
        </a>
    </form>
</div>

<!-- ── Summary ───────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:18px 22px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">⏳</div>
        <div>
            <div style="font-size:22px;font-weight:800;color:#1e73be"><?= count($ready_bookings) ?></div>
            <div style="font-size:12.5px;color:#718096;margin-top:2px">Booking chờ giải ngân</div>
        </div>
    </div>
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:18px 22px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">💰</div>
        <div>
            <div style="font-size:18px;font-weight:800;color:#1e73be">
                <?= number_format(array_sum(array_column($ready_bookings, 'hotel_payout')),0,',','.') ?>đ
            </div>
            <div style="font-size:12.5px;color:#718096;margin-top:2px">Tổng cần giải ngân</div>
        </div>
    </div>
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:18px 22px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">✅</div>
        <div>
            <div style="font-size:18px;font-weight:800;color:#276749">
                <?= number_format($total_paid,0,',','.') ?>đ
            </div>
            <div style="font-size:12.5px;color:#718096;margin-top:2px">Tổng đã giải ngân</div>
        </div>
    </div>
</div>

<!-- ── Danh sách READY ───────────────────────────────────────────── -->
<div class="section-card" style="margin-bottom:24px">
    <div class="section-head">
        <h3>⏳ Chờ giải ngân (<?= count($ready_bookings) ?>)</h3>
    </div>
    <div style="overflow-x:auto">
    <table style="min-width:800px">
        <thead>
            <tr>
                <th>Mã đơn</th>
                <th>Khách sạn / Phòng</th>
                <th>Khách hàng</th>
                <th>Ngày lưu trú</th>
                <th>Tổng tiền</th>
                <th>Hoa hồng</th>
                <th>Giải ngân cho hotel</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($ready_bookings)): ?>
            <tr><td colspan="8" style="text-align:center;color:#a0aec0;padding:40px">
                ✅ Không có booking nào chờ giải ngân<?= $filter_hotel ? ' cho khách sạn này' : '' ?>
            </td></tr>
        <?php else: ?>
            <?php foreach ($ready_bookings as $b): ?>
            <tr>
                <td class="td-order"><?= htmlspecialchars($b['order_code']) ?></td>
                <td>
                    <div style="font-weight:600;color:#1a202c;font-size:13px"><?= htmlspecialchars($b['hotel_name']) ?></div>
                    <div style="font-size:11.5px;color:#718096"><?= htmlspecialchars($b['room_name']) ?></div>
                </td>
                <td class="td-name"><?= htmlspecialchars($b['full_name'] ?? '—') ?></td>
                <td style="font-size:12px;color:#718096;white-space:nowrap">
                    <?= date('d/m/Y', strtotime($b['check_in'])) ?> → <?= date('d/m/Y', strtotime($b['check_out'])) ?>
                </td>
                <td class="td-price"><?= number_format($b['total_price'],0,',','.') ?>đ</td>
                <td style="color:#c53030;font-size:12.5px">
                    <?= number_format($b['platform_revenue'] ?? 0,0,',','.') ?>đ
                    <span style="color:#a0aec0;font-size:11px">(<?= number_format($b['commission_rate'] ?? 0,1) ?>%)</span>
                </td>
                <td style="font-weight:700;color:#276749;font-size:15px">
                    <?= number_format($b['hotel_payout'] ?? 0,0,',','.') ?>đ
                </td>
                <td>
                    <button onclick="openPayoutModal(<?= $b['id'] ?>, <?= $b['hotel_id'] ?>, '<?= addslashes($b['hotel_name']) ?>', <?= number_format($b['hotel_payout']??0, 2, '.', '') ?>)"
                            style="padding:6px 14px;background:#1e73be;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:12.5px;font-weight:600">
                        💸 Giải ngân
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── Lịch sử giải ngân ─────────────────────────────────────────── -->
<div class="section-card">
    <div class="section-head">
        <h3>📋 Lịch sử giải ngân</h3>
    </div>
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Thời gian</th>
                <th>Khách sạn</th>
                <th>Mã đơn</th>
                <th>Khách hàng</th>
                <th>Giải ngân</th>
                <th>Hoa hồng</th>
                <th>Thực hiện bởi</th>
                <th>Ghi chú</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($payout_history)): ?>
            <tr><td colspan="8" style="text-align:center;color:#a0aec0;padding:40px">Chưa có lịch sử giải ngân</td></tr>
        <?php else: ?>
            <?php foreach ($payout_history as $p): ?>
            <tr>
                <td style="color:#a0aec0;font-size:12px;white-space:nowrap">
                    <?= date('d/m/Y H:i', strtotime($p['processed_at'])) ?>
                </td>
                <td class="td-name"><?= htmlspecialchars($p['hotel_name']) ?></td>
                <td class="td-order"><?= htmlspecialchars($p['order_code']) ?></td>
                <td style="color:#4a5568;font-size:13px"><?= htmlspecialchars($p['guest_name'] ?? '—') ?></td>
                <td style="font-weight:700;color:#276749"><?= number_format($p['amount'],0,',','.') ?>đ</td>
                <td style="color:#c53030;font-size:12.5px"><?= number_format($p['commission_amount'],0,',','.') ?>đ</td>
                <td style="color:#718096;font-size:12.5px"><?= htmlspecialchars($p['processed_by_name'] ?? '—') ?></td>
                <td style="color:#718096;font-size:12px;max-width:180px"><?= htmlspecialchars($p['note'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── Payout Modal ───────────────────────────────────────────────── -->
<div id="payoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px 32px;max-width:420px;width:100%;margin:16px">
        <h3 style="margin:0 0 6px;font-size:18px;color:#1a202c">💸 Xác nhận giải ngân</h3>
        <p id="modal-hotel-name" style="color:#718096;font-size:13px;margin:0 0 18px"></p>

        <div style="background:#f0fff4;border-radius:10px;padding:14px 18px;margin-bottom:18px;border:1.5px solid #c6f6d5">
            <div style="font-size:12px;color:#276749;font-weight:600;margin-bottom:4px">SỐ TIỀN GIẢI NGÂN</div>
            <div id="modal-amount" style="font-size:28px;font-weight:800;color:#276749"></div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="payout">
            <input type="hidden" name="booking_id" id="modal-booking-id">
            <input type="hidden" name="hotel_id" value="<?= $filter_hotel ?>">
            <div style="margin-bottom:14px">
                <label style="font-size:13px;font-weight:600;color:#4a5568;display:block;margin-bottom:6px">Ghi chú (tuỳ chọn)</label>
                <textarea name="note" rows="3"
                    style="width:100%;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:13px;resize:vertical;box-sizing:border-box"
                    placeholder="VD: Chuyển khoản qua ngân hàng X..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="closePayoutModal()"
                        style="padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:8px;background:#fff;color:#4a5568;cursor:pointer;font-size:13.5px;font-weight:600">
                    Huỷ
                </button>
                <button type="submit"
                        style="padding:10px 24px;background:#276749;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:13.5px;font-weight:700">
                    ✅ Xác nhận giải ngân
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayoutModal(bookingId, hotelId, hotelName, amount) {
    document.getElementById('modal-booking-id').value  = bookingId;
    document.getElementById('modal-hotel-name').textContent = '🏨 ' + hotelName;
    document.getElementById('modal-amount').textContent     = new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
    const modal = document.getElementById('payoutModal');
    modal.style.display = 'flex';
}
function closePayoutModal() {
    document.getElementById('payoutModal').style.display = 'none';
}
document.getElementById('payoutModal').addEventListener('click', function(e) {
    if (e.target === this) closePayoutModal();
});
</script>

<?php include "../includes/admin_footer.php"; ?>
