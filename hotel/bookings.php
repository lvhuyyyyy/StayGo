<?php
$page_title    = 'Quản lý đặt phòng';
$page_subtitle = 'Xác nhận, từ chối và theo dõi đặt phòng';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_helper.php';

$msg   = null;
$error = null;

// ── POST: Confirm / Reject booking ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['booking_action'])) {
    csrf_check();
    $bid    = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['booking_action']; // confirm | reject
    $reason = trim($_POST['reject_reason'] ?? '');

    // Verify booking thuộc hotel này
    $chk = $conn->prepare("
        SELECT b.id, b.status, b.order_code, b.email, b.full_name,
               b.check_in, b.check_out, b.total_price
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        WHERE b.id = ? AND r.hotel_id = ? AND b.status = 'pending'
    ");
    $chk->bind_param("ii", $bid, $hotel_id);
    $chk->execute();
    $target = $chk->get_result()->fetch_assoc();

    if (!$target) {
        $error = 'Không tìm thấy đơn hoặc đơn không ở trạng thái chờ.';
    } elseif ($action === 'confirm') {
        // Fix #8: transaction + FOR UPDATE để chặn overbooking race condition
        $conn->begin_transaction();
        try {
            // Lock room row để đọc quantity chính xác
            $room_lock = $conn->prepare("
                SELECT r.quantity, b2.room_id
                FROM bookings b2
                JOIN rooms r ON r.id = b2.room_id
                WHERE b2.id = ? FOR UPDATE
            ");
            $room_lock->bind_param('i', $bid);
            $room_lock->execute();
            $lock_row = $room_lock->get_result()->fetch_assoc();

            if (!$lock_row) {
                $conn->rollback();
                $error = 'Không tìm thấy thông tin phòng.';
            } else {
                // Đếm booking đang active cùng phòng, cùng ngày (không tính booking này)
                $ovb = $conn->prepare("
                    SELECT COUNT(*) AS cnt FROM bookings b2
                    WHERE b2.room_id = ?
                      AND b2.id != ?
                      AND b2.status IN ('confirmed','checked_in')
                      AND b2.check_in  < ?
                      AND b2.check_out > ?
                ");
                $ovb->bind_param('iiss', $lock_row['room_id'], $bid,
                                         $target['check_out'], $target['check_in']);
                $ovb->execute();
                $overlap_count = (int)$ovb->get_result()->fetch_assoc()['cnt'];

                if ($overlap_count >= (int)$lock_row['quantity']) {
                    $conn->rollback();
                    $error = 'Phòng đã hết chỗ trong ngày này, không thể xác nhận.';
                } else {
                    $upd_bk = $conn->prepare("UPDATE bookings SET status='confirmed' WHERE id=?");
                    $upd_bk->bind_param('i', $bid);
                    $upd_bk->execute();
                    $upd_bk->close();
                    $hotel_name_s = $_SESSION['hotel_name'];
                    $log_s = $conn->prepare("
                        INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
                        VALUES (?, 'HOTEL', ?, ?, 'CONFIRMED', 'Khách sạn đã xác nhận đặt phòng.')
                    ");
                    $log_s->bind_param('iis', $bid, $hotel_id, $hotel_name_s);
                    $log_s->execute();
                    $log_s->close();
                    $conn->commit();
                    $msg = 'Đã xác nhận đặt phòng ' . htmlspecialchars($target['order_code']) . '.';
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Lỗi hệ thống khi xác nhận. Vui lòng thử lại.';
        }
    } elseif ($action === 'reject') {
        // P14: không cho từ chối khi check-in còn dưới 24 giờ — khách không kịp tìm chỗ khác
        $hours_to_checkin = (strtotime($target['check_in']) - time()) / 3600;
        if ($hours_to_checkin < 24) {
            $error = 'Không thể từ chối: ngày nhận phòng còn dưới 24 giờ. Vui lòng liên hệ trực tiếp với khách và báo Admin xử lý qua trang Disputes.';
        } else {
        if (!$reason) $reason = 'Khách sạn không thể nhận đặt phòng này.';
        $upd_rej = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
        $upd_rej->bind_param('i', $bid);
        $upd_rej->execute();
        $upd_rej->close();
        $hotel_name_s = $_SESSION['hotel_name'];
        $log_rej = $conn->prepare("
            INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
            VALUES (?, 'HOTEL', ?, ?, 'REJECTED', ?)
        ");
        $log_rej->bind_param('iiss', $bid, $hotel_id, $hotel_name_s, $reason);
        $log_rej->execute();
        $log_rej->close();
        send_booking_rejected_email($target['email'], $target['full_name'], [
            'order_code'  => $target['order_code'],
            'hotel_name'  => $_SESSION['hotel_name'],
            'check_in'    => $target['check_in'],
            'check_out'   => $target['check_out'],
            'total_price' => $target['total_price'],
            'reason'      => $reason,
        ]);
        $msg = 'Đã từ chối đặt phòng ' . htmlspecialchars($target['order_code']) . '.';
        } // end else (hours_to_checkin >= 24)
    }
}

// ── Filters ───────────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'all';
$highlight_id  = (int)($_GET['highlight'] ?? 0);
$valid_statuses = ['all','pending','confirmed','checked_in','completed','cancelled'];
if (!in_array($filter_status, $valid_statuses, true)) $filter_status = 'all';

$where_status = $filter_status !== 'all' ? "AND b.status = '" . $conn->real_escape_string($filter_status) . "'" : '';

$bookings = $conn->query("
    SELECT b.id, b.order_code, b.full_name, b.email, b.phone,
           b.check_in, b.check_out, b.total_price,
           b.status, b.payout_status, b.created_at, b.note,
           b.payment_method, r.room_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    WHERE r.hotel_id = $hotel_id $where_status
    ORDER BY
        FIELD(b.status,'pending','confirmed','checked_in','completed','cancelled'),
        b.created_at DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

function badge_status(string $s): string {
    $map = ['pending'=>'badge-pending','confirmed'=>'badge-confirmed','completed'=>'badge-completed','cancelled'=>'badge-cancelled','checked_in'=>'badge-checked_in'];
    $lbl = ['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','completed'=>'Hoàn thành','cancelled'=>'Đã hủy','checked_in'=>'Đang ở'];
    return '<span class="badge '.($map[$s]??'badge-pending').'">' . ($lbl[$s]??$s) . '</span>';
}
function badge_payout(string $s): string {
    $map = ['HOLDING'=>'badge-holding','READY'=>'badge-ready','FROZEN'=>'badge-frozen','PAID'=>'badge-paid'];
    $lbl = ['HOLDING'=>'Đang giữ','READY'=>'Sẵn sàng','FROZEN'=>'Đóng băng','PAID'=>'Đã giải ngân'];
    return '<span class="badge '.($map[$s]??'badge-holding').'">' . ($lbl[$s]??$s) . '</span>';
}
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= $msg ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <?php
    $tab_items = [
        'all'       => 'Tất cả',
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'checked_in'=> 'Đang ở',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];
    foreach ($tab_items as $val => $label):
    ?>
    <a href="?status=<?= $val ?>"
       style="padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
              background:<?= $filter_status===$val ? '#1a365d' : '#fff' ?>;
              color:<?= $filter_status===$val ? '#fff' : '#4a5568' ?>;
              border:1.5px solid <?= $filter_status===$val ? '#1a365d' : '#e2e8f0' ?>">
        <?= $label ?>
        <?php if ($val === 'pending' && $pending_bookings > 0): ?>
            <span style="background:#e53e3e;color:#fff;font-size:10px;padding:1px 5px;border-radius:10px;margin-left:4px"><?= $pending_bookings ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Danh sách đặt phòng</span>
        <span style="font-size:12.5px;color:#a0aec0"><?= count($bookings) ?> kết quả</span>
    </div>
    <div class="card-body">
        <?php if (empty($bookings)): ?>
        <div style="text-align:center;padding:60px;color:#a0aec0">Không có đặt phòng nào.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách</th>
                    <th>Phòng</th>
                    <th>Nhận/Trả phòng</th>
                    <th>Tổng tiền</th>
                    <th>Thanh toán</th>
                    <th>Trạng thái</th>
                    <th>Giải ngân</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $b):
                $is_highlight = $highlight_id && $b['id'] === $highlight_id;
            ?>
            <tr id="row-<?= $b['id'] ?>" style="<?= $is_highlight ? 'background:#fffbeb' : '' ?>">
                <td>
                    <code style="font-size:12px;background:#f0f4f8;padding:2px 7px;border-radius:5px">
                        <?= htmlspecialchars($b['order_code']) ?>
                    </code>
                    <div style="font-size:11px;color:#a0aec0;margin-top:2px"><?= date('d/m/Y', strtotime($b['created_at'])) ?></div>
                </td>
                <td>
                    <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($b['full_name']) ?></div>
                    <div style="font-size:11px;color:#a0aec0"><?= htmlspecialchars($b['email']) ?></div>
                    <?php if ($b['phone']): ?>
                    <div style="font-size:11px;color:#a0aec0"><?= htmlspecialchars($b['phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px">
                    <?= htmlspecialchars($b['room_name']) ?>
                    <?php if ($b['note']): ?>
                    <div style="font-size:11px;color:#a0aec0;margin-top:2px" title="<?= htmlspecialchars($b['note']) ?>">📝 <?= htmlspecialchars(mb_substr($b['note'], 0, 25)) ?><?= mb_strlen($b['note']) > 25 ? '...' : '' ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;white-space:nowrap;color:#4a5568">
                    📅 <?= date('d/m/Y', strtotime($b['check_in'])) ?><br>
                    🏁 <?= date('d/m/Y', strtotime($b['check_out'])) ?>
                </td>
                <td style="font-weight:700;color:#276749;white-space:nowrap">
                    <?= number_format($b['total_price'], 0, ',', '.') ?>đ
                </td>
                <td>
                    <?php
                    $pm_labels = [
                        'vnpay'  => ['VNPay',    '#ebf4ff','#2b6cb0'],
                        'payos'  => ['PayOS',    '#f0fff4','#276749'],
                        'bank'   => ['CK Ngân hàng','#faf5ff','#6b46c1'],
                        'hotel'  => ['Tại quầy', '#fffbeb','#92400e'],
                        'momo'   => ['MoMo',     '#fff5f5','#c53030'],
                        'card'   => ['Thẻ QT',   '#f0fff4','#276749'],
                    ];
                    $pm = $b['payment_method'] ?? '';
                    [$lbl,$bg,$fg] = $pm_labels[$pm] ?? [$pm,'#f0f4f8','#4a5568'];
                    echo "<span style=\"background:$bg;color:$fg;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px\">$lbl</span>";
                    ?>
                </td>
                <td><?= badge_status($b['status']) ?></td>
                <td><?= badge_payout($b['payout_status']) ?></td>
                <td style="white-space:nowrap">
                    <?php if ($b['status'] === 'pending'): ?>
                    <button class="btn btn-success btn-sm" onclick="openConfirm(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['order_code'])) ?>')">✓ Xác nhận</button>
                    <button class="btn btn-danger btn-sm" style="margin-top:4px" onclick="openReject(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['order_code'])) ?>')">✗ Từ chối</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:#a0aec0">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Confirm -->
<div id="modalConfirm" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <h3 style="font-size:17px;font-weight:800;color:#1a365d;margin-bottom:8px">✅ Xác nhận đặt phòng</h3>
        <p style="font-size:13.5px;color:#4a5568;margin-bottom:20px" id="confirmText">Bạn có chắc chắn muốn xác nhận?</p>
        <form method="POST" id="formConfirm">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" id="confirmId">
            <input type="hidden" name="booking_action" value="confirm">
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalConfirm')">Hủy</button>
                <button type="submit" class="btn btn-success">Xác nhận</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reject -->
<div id="modalReject" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <h3 style="font-size:17px;font-weight:800;color:#c53030;margin-bottom:8px">✗ Từ chối đặt phòng</h3>
        <p style="font-size:13.5px;color:#4a5568;margin-bottom:16px" id="rejectText">Đơn hàng:</p>
        <form method="POST" id="formReject">
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" id="rejectId">
            <input type="hidden" name="booking_action" value="reject">
            <div class="form-group">
                <label>Lý do từ chối (sẽ thông báo đến khách)</label>
                <textarea name="reject_reason" rows="3" placeholder="Ví dụ: Phòng đã kín trong ngày này..." style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13.5px"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalReject')">Hủy</button>
                <button type="submit" class="btn btn-danger">Từ chối</button>
            </div>
        </form>
    </div>
</div>

<script>
function openConfirm(id, code) {
    document.getElementById('confirmId').value = id;
    document.getElementById('confirmText').textContent = 'Xác nhận đặt phòng ' + code + '?';
    document.getElementById('modalConfirm').style.display = 'flex';
}
function openReject(id, code) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectText').textContent = 'Từ chối đặt phòng: ' + code;
    document.getElementById('modalReject').style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
// Scroll to highlighted row
<?php if ($highlight_id): ?>
var el = document.getElementById('row-<?= $highlight_id ?>');
if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
