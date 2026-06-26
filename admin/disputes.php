<?php
require_once 'admin_bootstrap.php'; // auth + DB + CSRF — phải là dòng đầu tiên

$page_title    = 'Khiếu nại & Tranh chấp';
$page_subtitle = 'Xử lý tranh chấp giữa User và Hotel';

// -- POST: cập nhật trạng thái dispute --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $dispute_id     = (int)($_POST['dispute_id'] ?? 0);
    $action         = $_POST['action'] ?? '';
    $admin_response = trim($_POST['admin_response'] ?? '');
    $admin_id       = (int)($_SESSION['admin_id'] ?? 0);
    $admin_name     = $conn->real_escape_string($_SESSION['admin_name'] ?? 'Admin');

    $status_map = [
        'in_progress' => 'IN_PROGRESS',
        'resolve'     => 'RESOLVED',
        'reject'      => 'REJECTED',
    ];

    if ($dispute_id && isset($status_map[$action])) {
        $new_status = $status_map[$action];
        $resp_esc   = $conn->real_escape_string($admin_response);
        $conn->query("
            UPDATE disputes
            SET status='$new_status', admin_response='$resp_esc',
                resolved_by_admin_id=$admin_id, updated_at=NOW()
            WHERE id=$dispute_id
        ");

        // Đóng băng giải ngân nếu admin tích chọn (dispute chấp nhận, khách thắng)
        if ($action === 'resolve' && !empty($_POST['freeze_payout'])) {
            $d = $conn->query("SELECT booking_id FROM disputes WHERE id=$dispute_id")->fetch_assoc();
            if ($d) {
                $bid = (int)$d['booking_id'];
                $conn->query("UPDATE bookings SET payout_status='FROZEN' WHERE id=$bid AND payout_status NOT IN ('PAID','FROZEN')");
                $conn->query("
                    INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
                    VALUES ($bid, 'ADMIN', $admin_id, '$admin_name', 'PAYOUT_FROZEN',
                        'Đóng băng giải ngân do khiếu nại #$dispute_id được chấp nhận')
                ");
            }
        }

        // Gỡ đóng băng nếu reject (dispute vô lý, hotel thắng)
        if ($action === 'reject') {
            $d = $conn->query("SELECT dp.booking_id, b.status AS booking_status FROM disputes dp JOIN bookings b ON b.id=dp.booking_id WHERE dp.id=$dispute_id")->fetch_assoc();
            if ($d) {
                $bid     = (int)$d['booking_id'];
                $bstatus = $d['booking_status'];
                // Booking đã hoàn thành → unfreeze về READY (chờ giải ngân)
                $conn->query("UPDATE bookings SET payout_status='READY'
                    WHERE id=$bid AND payout_status='FROZEN' AND status='completed'");
                // Booking đang hoạt động → unfreeze về HOLDING (chưa đến lúc giải ngân)
                $conn->query("UPDATE bookings SET payout_status='HOLDING'
                    WHERE id=$bid AND payout_status='FROZEN' AND status IN ('confirmed','checked_in')");
                $conn->query("
                    INSERT INTO booking_logs (booking_id, actor_type, actor_id, actor_name, action, description)
                    VALUES ($bid, 'ADMIN', $admin_id, '$admin_name', 'PAYOUT_UNFROZEN',
                        'Gỡ đóng băng giải ngân do khiếu nại #$dispute_id bị từ chối')
                ");
            }
        }

        header("Location: disputes.php?updated=1");
        exit();
    }
}

// -- Filters --
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type']   ?? '';
$valid_statuses = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'REJECTED'];
$valid_types    = ['WRONG_ROOM', 'INCIDENT', 'REFUND_REQUEST', 'OTHER'];

$where = "WHERE 1=1";
if ($filter_status && in_array($filter_status, $valid_statuses, true)) {
    $fs = $conn->real_escape_string($filter_status);
    $where .= " AND d.status = '$fs'";
}
if ($filter_type && in_array($filter_type, $valid_types, true)) {
    $ft = $conn->real_escape_string($filter_type);
    $where .= " AND d.type = '$ft'";
}

// -- Thống kê --
$stats = [];
foreach (['OPEN', 'IN_PROGRESS', 'RESOLVED', 'REJECTED'] as $s) {
    $stats[$s] = (int)$conn->query("SELECT COUNT(*) AS c FROM disputes WHERE status='$s'")->fetch_assoc()['c'];
}

// -- Danh sách disputes --
$disputes = $conn->query("
    SELECT d.*,
           b.order_code, b.full_name AS booking_name, b.email AS booking_email,
           b.total_price, b.status AS booking_status, b.check_in, b.check_out,
           h.name AS hotel_name,
           u.full_name AS user_name, u.email AS user_email
    FROM disputes d
    JOIN bookings b ON b.id = d.booking_id
    LEFT JOIN rooms  r ON r.id = b.room_id
    LEFT JOIN hotels h ON h.id = r.hotel_id
    LEFT JOIN users  u ON u.id = d.user_id
    $where
    ORDER BY
        CASE d.status WHEN 'OPEN' THEN 1 WHEN 'IN_PROGRESS' THEN 2 ELSE 3 END,
        d.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$type_labels = [
    'WRONG_ROOM'     => ['Phòng sai mô tả', '#744210', '#fffbeb'],
    'INCIDENT'       => ['Sự cố tại khách sạn', '#c53030', '#fff5f5'],
    'REFUND_REQUEST' => ['Yêu cầu hoàn tiền', '#1e73be', '#ebf8ff'],
    'OTHER'          => ['Khác', '#718096', '#f7fafc'],
];
$status_labels = [
    'OPEN'        => ['Mới', '#c53030', '#fff5f5'],
    'IN_PROGRESS' => ['Đang xử lý', '#b7791f', '#fffbeb'],
    'RESOLVED'    => ['Đã giải quyết', '#276749', '#f0fff4'],
    'REJECTED'    => ['Từ chối', '#718096', '#f7fafc'],
];

include "../includes/admin_header.php";
?>

<style>
.dp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.dp-stat{background:#fff;border-radius:14px;padding:20px 24px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.dp-stat-num{font-size:32px;font-weight:800;color:#1a202c;line-height:1}
.dp-stat-label{font-size:13px;color:#718096;margin-top:4px;font-weight:500}
.dp-stat.open    .dp-stat-num{color:#c53030}
.dp-stat.inprog  .dp-stat-num{color:#b7791f}
.dp-stat.resolved.dp-stat-num{color:#276749}
.dp-filters{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.dp-filters select,.dp-filters a{padding:9px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13.5px;background:#fff;color:#2d3748;text-decoration:none;cursor:pointer}
.dp-filters a.active{background:#1e73be;color:#fff;border-color:#1e73be}
.dp-table{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden}
.dp-table table{width:100%;border-collapse:collapse}
.dp-table th{background:#f7fafc;padding:12px 16px;text-align:left;font-size:12px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px}
.dp-table td{padding:14px 16px;border-top:1px solid #f0f4f8;vertical-align:top}
.dp-table tr:hover td{background:#fafbfc}
.type-badge,.status-badge-dp{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600}
.desc-cell{max-width:220px;font-size:13px;color:#4a5568;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.btn-view{padding:7px 14px;background:#1e73be;color:#fff;border-radius:8px;font-size:12.5px;font-weight:600;border:none;cursor:pointer}
.empty-state{text-align:center;padding:60px 20px;color:#a0aec0}

/* MODAL */
.dp-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
.dp-modal-overlay.open{display:flex}
.dp-modal{background:#fff;border-radius:20px;width:680px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.25)}
.dp-modal-head{padding:24px 28px 0;display:flex;justify-content:space-between;align-items:start}
.dp-modal-title{font-size:18px;font-weight:800;color:#1a202c}
.dp-modal-close{background:none;border:none;font-size:22px;color:#a0aec0;cursor:pointer;line-height:1}
.dp-modal-body{padding:20px 28px 28px}
.dp-section{background:#f7fafc;border-radius:12px;padding:16px;margin-bottom:16px}
.dp-section-title{font-size:12px;font-weight:700;color:#a0aec0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
.dp-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dp-info-item label{display:block;font-size:11.5px;color:#a0aec0;font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px}
.dp-info-item span{font-size:13.5px;font-weight:600;color:#2d3748}
.dp-desc-box{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:13.5px;color:#4a5568;line-height:1.6;margin-top:8px;white-space:pre-wrap}
.dp-response-area{width:100%;padding:12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical;min-height:90px;box-sizing:border-box}
.dp-response-area:focus{outline:none;border-color:#1e73be;box-shadow:0 0 0 3px rgba(30,115,190,.12)}
.dp-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.btn-inprog{padding:10px 20px;background:#fffbeb;color:#b7791f;border:1.5px solid #f6e05e;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer}
.btn-resolve{padding:10px 20px;background:linear-gradient(135deg,#276749,#38a169);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer}
.btn-reject{padding:10px 20px;background:#fff5f5;color:#c53030;border:1.5px solid #feb2b2;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer}
.freeze-check{display:flex;align-items:center;gap:8px;font-size:13px;color:#c53030;font-weight:600;margin-top:10px}
</style>

<div class="page-header">
    <h2>⚖️ Khiếu nại & Tranh chấp</h2>
</div>

<?php if (isset($_GET['updated'])): ?>
<div style="padding:12px 16px;background:#f0fff4;border:1px solid #9ae6b4;border-radius:10px;color:#276749;font-weight:600;font-size:13.5px;margin-bottom:20px">
    ✅ Đã cập nhật trạng thái khiếu nại thành công.
</div>
<?php endif; ?>

<!-- Thống kê -->
<div class="dp-stats">
    <div class="dp-stat open">
        <div class="dp-stat-num"><?= $stats['OPEN'] ?></div>
        <div class="dp-stat-label">Chờ xử lý</div>
    </div>
    <div class="dp-stat inprog">
        <div class="dp-stat-num"><?= $stats['IN_PROGRESS'] ?></div>
        <div class="dp-stat-label">Đang xử lý</div>
    </div>
    <div class="dp-stat resolved">
        <div class="dp-stat-num"><?= $stats['RESOLVED'] ?></div>
        <div class="dp-stat-label">Đã giải quyết</div>
    </div>
    <div class="dp-stat">
        <div class="dp-stat-num"><?= $stats['REJECTED'] ?></div>
        <div class="dp-stat-label">Từ chối</div>
    </div>
</div>

<!-- Bộ lọc -->
<div class="dp-filters">
    <a href="disputes.php" class="<?= !$filter_status && !$filter_type ? 'active' : '' ?>">Tất cả</a>
    <a href="?status=OPEN" class="<?= $filter_status==='OPEN' ? 'active' : '' ?>">🔴 Chờ xử lý</a>
    <a href="?status=IN_PROGRESS" class="<?= $filter_status==='IN_PROGRESS' ? 'active' : '' ?>">🟡 Đang xử lý</a>
    <a href="?status=RESOLVED" class="<?= $filter_status==='RESOLVED' ? 'active' : '' ?>">🟢 Đã giải quyết</a>
    <a href="?status=REJECTED" class="<?= $filter_status==='REJECTED' ? 'active' : '' ?>">⚫ Từ chối</a>
    <select onchange="location='?type='+this.value+(<?= json_encode($filter_status) ?>?'&status=<?= $filter_status ?>':'')">
        <option value="">-- Loại khiếu nại --</option>
        <?php foreach ($type_labels as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filter_type===$k?'selected':'' ?>><?= $v[0] ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Bảng danh sách -->
<div class="dp-table">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Booking</th>
                <th>Người khiếu nại</th>
                <th>Loại</th>
                <th>Nội dung</th>
                <th>Trạng thái</th>
                <th>Ngày tạo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($disputes)): ?>
            <tr><td colspan="8"><div class="empty-state">Không có khiếu nại nào.</div></td></tr>
        <?php else: foreach ($disputes as $d):
            $tl  = $type_labels[$d['type']]   ?? ['Khác',   '#718096', '#f7fafc'];
            $sl  = $status_labels[$d['status']] ?? ['?',     '#718096', '#f7fafc'];
        ?>
            <tr>
                <td style="color:#a0aec0;font-size:12px">#<?= $d['id'] ?></td>
                <td>
                    <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($d['order_code']) ?></div>
                    <div style="font-size:12px;color:#718096"><?= htmlspecialchars($d['hotel_name'] ?? '—') ?></div>
                </td>
                <td>
                    <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($d['user_name'] ?? $d['booking_name']) ?></div>
                    <div style="font-size:12px;color:#718096"><?= htmlspecialchars($d['user_email'] ?? $d['guest_email'] ?? $d['booking_email']) ?></div>
                </td>
                <td>
                    <span class="type-badge" style="color:<?= $tl[1] ?>;background:<?= $tl[2] ?>">
                        <?= $tl[0] ?>
                    </span>
                </td>
                <td class="desc-cell"><?= htmlspecialchars($d['description']) ?></td>
                <td>
                    <span class="status-badge-dp" style="color:<?= $sl[1] ?>;background:<?= $sl[2] ?>">
                        <?= $sl[0] ?>
                    </span>
                </td>
                <td style="font-size:12px;color:#718096"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                <td>
                    <button class="btn-view" onclick='openModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)'>Xem</button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal chi tiết -->
<div class="dp-modal-overlay" id="dpModalOverlay">
    <div class="dp-modal">
        <div class="dp-modal-head">
            <div>
                <div class="dp-modal-title" id="mTitle">Chi tiết khiếu nại</div>
                <div id="mSubtitle" style="font-size:13px;color:#718096;margin-top:2px"></div>
            </div>
            <button class="dp-modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="dp-modal-body">

            <!-- Thông tin booking -->
            <div class="dp-section">
                <div class="dp-section-title">Thông tin đặt phòng</div>
                <div class="dp-info-grid">
                    <div class="dp-info-item"><label>Mã đơn</label><span id="mOrderCode">—</span></div>
                    <div class="dp-info-item"><label>Khách sạn</label><span id="mHotel">—</span></div>
                    <div class="dp-info-item"><label>Khách hàng</label><span id="mGuest">—</span></div>
                    <div class="dp-info-item"><label>Email</label><span id="mEmail">—</span></div>
                    <div class="dp-info-item"><label>Check-in</label><span id="mCheckin">—</span></div>
                    <div class="dp-info-item"><label>Check-out</label><span id="mCheckout">—</span></div>
                    <div class="dp-info-item"><label>Tổng tiền</label><span id="mTotal">—</span></div>
                    <div class="dp-info-item"><label>Trạng thái booking</label><span id="mBookingStatus">—</span></div>
                </div>
            </div>

            <!-- Nội dung khiếu nại -->
            <div class="dp-section">
                <div class="dp-section-title">Nội dung khiếu nại</div>
                <div class="dp-info-grid" style="margin-bottom:10px">
                    <div class="dp-info-item"><label>Loại</label><span id="mType">—</span></div>
                    <div class="dp-info-item"><label>Trạng thái</label><span id="mStatus">—</span></div>
                </div>
                <div class="dp-desc-box" id="mDesc">—</div>
            </div>

            <!-- Phản hồi admin -->
            <div id="mExistingResponse" style="display:none" class="dp-section">
                <div class="dp-section-title">Phản hồi trước đó</div>
                <div class="dp-desc-box" id="mExistingResponseText">—</div>
            </div>

            <!-- Form xử lý -->
            <form method="POST" id="dpForm">
                <?= csrf_field() ?>
                <input type="hidden" name="dispute_id" id="mDispId">
                <input type="hidden" name="action" id="mAction">

                <div style="margin-bottom:12px">
                    <label style="font-size:13px;font-weight:600;color:#4a5568;display:block;margin-bottom:6px">
                        Phản hồi của Admin
                    </label>
                    <textarea class="dp-response-area" name="admin_response" id="mResponseInput"
                              placeholder="Ghi rõ phán quyết hoặc yêu cầu thêm thông tin..."></textarea>
                </div>

                <div class="freeze-check" id="mFreezeWrap" style="display:none">
                    <input type="checkbox" name="freeze_payout" id="freezeCheck" value="1">
                    <label for="freezeCheck">Đóng băng giải ngân cho hotel đến khi tranh chấp kết thúc</label>
                </div>

                <div class="dp-actions" id="mActions"></div>
            </form>
        </div>
    </div>
</div>

<script>
var TYPE_LABELS   = <?= json_encode($type_labels) ?>;
var STATUS_LABELS = <?= json_encode($status_labels) ?>;

function openModal(d) {
    document.getElementById('mTitle').textContent    = 'Khiếu nại #' + d.id;
    document.getElementById('mSubtitle').textContent = 'Tạo lúc ' + d.created_at;
    document.getElementById('mOrderCode').textContent  = d.order_code   || '—';
    document.getElementById('mHotel').textContent      = d.hotel_name   || '—';
    document.getElementById('mGuest').textContent      = d.user_name || d.booking_name || '—';
    document.getElementById('mEmail').textContent      = d.user_email || d.guest_email || d.booking_email || '—';
    document.getElementById('mCheckin').textContent    = d.check_in ? formatDate(d.check_in)  : '—';
    document.getElementById('mCheckout').textContent   = d.check_out ? formatDate(d.check_out) : '—';
    document.getElementById('mTotal').textContent      = d.total_price ? parseInt(d.total_price).toLocaleString('vi') + ' VNĐ' : '—';
    document.getElementById('mBookingStatus').textContent = d.booking_status || '—';
    document.getElementById('mType').textContent   = TYPE_LABELS[d.type]     ? TYPE_LABELS[d.type][0]   : d.type;
    document.getElementById('mStatus').textContent = STATUS_LABELS[d.status] ? STATUS_LABELS[d.status][0] : d.status;
    document.getElementById('mDesc').textContent   = d.description || '—';
    document.getElementById('mDispId').value       = d.id;
    document.getElementById('mResponseInput').value = '';

    // Phản hồi cũ
    if (d.admin_response) {
        document.getElementById('mExistingResponse').style.display = 'block';
        document.getElementById('mExistingResponseText').textContent = d.admin_response;
    } else {
        document.getElementById('mExistingResponse').style.display = 'none';
    }

    // Freeze payout chỉ hiện khi booking status=completed hoặc confirmed
    var canFreeze = (d.booking_status === 'completed' || d.booking_status === 'confirmed');
    document.getElementById('mFreezeWrap').style.display = canFreeze ? 'flex' : 'none';

    // Nút hành động
    var actions = document.getElementById('mActions');
    actions.innerHTML = '';
    if (d.status === 'OPEN' || d.status === 'IN_PROGRESS') {
        actions.innerHTML += '<button type="button" class="btn-inprog" onclick="doAction(\'in_progress\')">📋 Đang xử lý</button>';
        actions.innerHTML += '<button type="button" class="btn-resolve" onclick="doAction(\'resolve\')">✅ Giải quyết xong</button>';
        actions.innerHTML += '<button type="button" class="btn-reject"  onclick="doAction(\'reject\')">❌ Từ chối</button>';
    } else {
        actions.innerHTML = '<div style="font-size:13.5px;color:#a0aec0;padding:10px 0">Khiếu nại này đã được đóng.</div>';
    }

    document.getElementById('dpModalOverlay').classList.add('open');
}

function closeModal() {
    document.getElementById('dpModalOverlay').classList.remove('open');
    document.getElementById('freezeCheck').checked = false;
}

function doAction(action) {
    document.getElementById('mAction').value = action;
    document.getElementById('dpForm').submit();
}

function formatDate(s) {
    if (!s) return '—';
    var parts = s.split('-');
    if (parts.length === 3) return parts[2]+'/'+parts[1]+'/'+parts[0];
    return s;
}

document.getElementById('dpModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include "../includes/admin_footer.php"; ?>
