<?php
require_once 'admin_bootstrap.php'; // auth + DB + CSRF — phải là dòng đầu tiên

// -- Xử lý duyệt / từ chối TRƯỚC KHI include header --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); // chặn CSRF ngay lập tức

    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && in_array($action, ['approve', 'reject'], true)) {
        // Fix #9: lấy thêm status + refund_amount để validate trước khi xử lý
        $stmt = $conn->prepare("
            SELECT id, status, refund_requested, refund_amount, total_price, payment_flow
            FROM bookings WHERE id = ? AND refund_requested = 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if ($booking) {
            $admin_id   = (int)($_SESSION['admin_id'] ?? 0);
            $admin_name = $conn->real_escape_string($_SESSION['admin_name'] ?? 'Admin');

            if ($action === 'approve') {
                // Fix #9a: không approve khi booking đang checked_in (khách đang ở)
                if ($booking['status'] === 'checked_in') {
                    header("Location: refund_requests.php?error=checked_in_cannot_refund");
                    exit;
                }

                $refund_fmt = number_format((float)$booking['refund_amount'], 0, ',', '.');

                $conn->begin_transaction();
                // Đánh dấu refund_requested = 2 (approved) + set cancelled + freeze payout
                $conn->query("
                    UPDATE bookings
                    SET status = 'cancelled', refund_requested = 2, payout_status = 'FROZEN'
                    WHERE id = $id AND refund_requested = 1
                ");
                $conn->query("UPDATE payments SET payment_status = 'refunded' WHERE booking_id = $id");
                // Fix #9b: ghi booking_log
                $conn->query("
                    INSERT INTO booking_logs
                        (booking_id, actor_type, actor_id, actor_name, action, description)
                    VALUES ($id, 'ADMIN', $admin_id, '$admin_name',
                            'REFUND_APPROVED',
                            'Duyệt hoàn tiền {$refund_fmt}đ — booking chuyển cancelled, payout FROZEN')
                ");
                $conn->commit();

                log_activity($conn, 'approve_refund', 'booking', $id, "Duyệt hoàn tiền đơn #$id");
                header("Location: refund_requests.php?success=approved");

            } elseif ($action === 'reject') {
                $conn->begin_transaction();
                $conn->query("UPDATE bookings SET refund_requested = 3 WHERE id = $id AND refund_requested = 1");
                // Fix #9b: ghi booking_log
                $conn->query("
                    INSERT INTO booking_logs
                        (booking_id, actor_type, actor_id, actor_name, action, description)
                    VALUES ($id, 'ADMIN', $admin_id, '$admin_name',
                            'REFUND_REJECTED', 'Từ chối yêu cầu hoàn tiền')
                ");
                $conn->commit();

                log_activity($conn, 'reject_refund', 'booking', $id, "Từ chối hoàn tiền đơn #$id");
                header("Location: refund_requests.php?success=rejected");
            }
            exit;
        }
    }
}

$page_title    = 'Yêu cầu hoàn tiền';
$page_subtitle = 'Danh sách yêu cầu hoàn tiền từ khách hàng';

include "../includes/admin_header.php";

// Lấy danh sách yêu cầu
$filter = isset($_GET['filter']) ? (int)$_GET['filter'] : 1;
$result = $conn->query("
    SELECT b.*,
        h.name AS hotel_name,
        r.room_name
    FROM bookings b
    LEFT JOIN rooms  r ON b.room_id  = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.refund_requested = $filter
    ORDER BY b.refund_requested_at DESC
");

$count_pending  = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE refund_requested = 1")->fetch_assoc()['c'];
$count_approved = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE refund_requested = 2")->fetch_assoc()['c'];
$count_rejected = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE refund_requested = 3")->fetch_assoc()['c'];
?>

<div class="page-header">
    <h2>💰 Yêu cầu hoàn tiền</h2>
</div>

<?php if(isset($_GET['success'])): ?>
    <?php if($_GET['success'] === 'approved'): ?>
        <div style="padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#276749;background:#f0fff4;border:1px solid #9ae6b4">
            ✅ Đã duyệt hoàn tiền thành công! Booking đã bị huỷ, payout đã bị đóng băng.
        </div>
    <?php elseif($_GET['success'] === 'rejected'): ?>
        <div style="padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#c53030;background:#fff5f5;border:1px solid #feb2b2">
            ❌ Đã từ chối yêu cầu hoàn tiền.
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php if(isset($_GET['error']) && $_GET['error'] === 'checked_in_cannot_refund'): ?>
    <div style="padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#b7791f;background:#fffbeb;border:1px solid #f6ad55">
        ⚠️ Không thể duyệt hoàn tiền: khách đang check-in tại khách sạn. Vui lòng xử lý qua trang Disputes.
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="refund-tabs">
    <a href="?filter=1" class="refund-tab <?= $filter==1?'active':'' ?>">
        ⏳ Chờ xử lý <span class="cnt"><?= $count_pending ?></span>
    </a>
    <a href="?filter=2" class="refund-tab <?= $filter==2?'active':'' ?>">
        ✅ Đã duyệt <span class="cnt"><?= $count_approved ?></span>
    </a>
    <a href="?filter=3" class="refund-tab <?= $filter==3?'active':'' ?>">
        ❌ Đã từ chối <span class="cnt"><?= $count_rejected ?></span>
    </a>
</div>

<?php if($result->num_rows === 0): ?>
    <div class="empty-state">
        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
        </svg>
        <p>Không có yêu cầu hoàn tiền nào</p>
    </div>
<?php else: ?>
    <?php while($row = $result->fetch_assoc()):
        $card_class        = $filter==2 ? 'approved' : ($filter==3 ? 'rejected' : '');
        $refund_amount_fmt = number_format($row['refund_amount'] ?? 0, 0, ',', '.');
        $total_fmt         = number_format($row['total_price'] ?? 0, 0, ',', '.');
        $is_admin_cancel   = $row['refund_amount'] >= $row['total_price'] * 0.99;
        $fee_fmt           = $is_admin_cancel ? '0' : number_format($row['total_price'] * 0.2, 0, ',', '.');
    ?>
    <div class="refund-card <?= $card_class ?>">

        <!-- Head -->
        <div class="refund-card-head">
            <div>
                <div class="refund-order"># <?= htmlspecialchars($row['order_code']) ?></div>
                <div class="refund-date">
                    🕐 Yêu cầu lúc: <?= $row['refund_requested_at'] ? date('d/m/Y H:i', strtotime($row['refund_requested_at'])) : '—' ?>
                </div>
                <div style="margin-top:4px;font-size:12px;font-weight:600;color:<?= $is_admin_cancel ? '#c53030' : '#b7791f' ?>">
                    <?= $is_admin_cancel ? '⚙️ Admin chủ động hủy — hoàn 100%' : '👤 Khách yêu cầu hủy — hoàn 80%' ?>
                </div>
            </div>
            <?php if($filter == 1): ?>
                <!-- POST forms để tránh GET-based CSRF -->
                <form id="form_approve_<?= $row['id'] ?>" method="POST" style="display:none">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                </form>
                <form id="form_reject_<?= $row['id'] ?>" method="POST" style="display:none">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                </form>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn-approve"
                        onclick="adminConfirmPost('Duyệt hoàn tiền <?= $refund_amount_fmt ?>đ cho đơn này?', 'form_approve_<?= $row['id'] ?>', '💵')">
                        ✅ Duyệt hoàn tiền
                    </button>
                    <button type="button" class="btn btn-deny"
                        onclick="adminConfirmPost('Từ chối yêu cầu hoàn tiền này?', 'form_reject_<?= $row['id'] ?>', '❌')">
                        ❌ Từ chối
                    </button>
                </div>
            <?php elseif($filter == 2): ?>
                <span class="status-badge" style="color:#276749;background:#f0fff4;border:1px solid #9ae6b4">✅ Đã duyệt</span>
            <?php else: ?>
                <span class="status-badge" style="color:#c53030;background:#fff5f5;border:1px solid #fed7d7">❌ Đã từ chối</span>
            <?php endif; ?>
        </div>

        <!-- Info grid -->
        <div class="refund-grid">
            <div class="refund-item">
                <label>Khách hàng</label>
                <span><?= htmlspecialchars($row['full_name']) ?></span>
            </div>
            <div class="refund-item">
                <label>Khách sạn</label>
                <span><?= htmlspecialchars($row['hotel_name'] ?? '—') ?></span>
            </div>
            <div class="refund-item">
                <label>Loại phòng</label>
                <span><?= htmlspecialchars($row['room_name'] ?? '—') ?></span>
            </div>
            <div class="refund-item">
                <label>Ngày nhận phòng</label>
                <span><?= $row['check_in'] ? date('d/m/Y', strtotime($row['check_in'])) : '—' ?></span>
            </div>
        </div>

        <!-- Amount box -->
        <div class="refund-amount-box">
            <div class="ra-col">
                <div class="ra-label">🧾 Tổng đơn</div>
                <div class="ra-val"><?= $total_fmt ?>đ</div>
            </div>
            <div class="ra-arrow">→</div>
            <div class="ra-col">
                <div class="ra-label"><?= $is_admin_cancel ? '✂️ Phí huỷ' : '✂️ Phí huỷ (20%)' ?></div>
                <div class="ra-val fee"><?= $is_admin_cancel ? 'Miễn phí' : '-'.$fee_fmt.'đ' ?></div>
            </div>
            <div class="ra-arrow">→</div>
            <div class="ra-col">
                <div class="ra-label">💵 Hoàn lại khách</div>
                <div class="ra-val refund"><?= $refund_amount_fmt ?>đ</div>
            </div>
        </div>

    </div>
    <?php endwhile; ?>
<?php endif; ?>

<?php include "../includes/admin_footer.php"; ?>
