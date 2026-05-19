<?php
include "../config/database.php";

// -- AJAX action handler --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['rv_action'])) {
    header('Content-Type: application/json');
    $rv_id  = (int)($_POST['rv_id'] ?? 0);
    $action = $_POST['rv_action'];
    if ($rv_id && in_array($action, ['hide', 'show', 'delete'], true)) {
        if ($action === 'hide')   $conn->query("UPDATE reviews SET is_active=0 WHERE id=$rv_id");
        elseif ($action === 'show')   $conn->query("UPDATE reviews SET is_active=1 WHERE id=$rv_id");
        elseif ($action === 'delete') $conn->query("DELETE FROM reviews WHERE id=$rv_id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

$page_title    = 'Quản Lý Đánh Giá';
$page_subtitle = 'Kiểm duyệt & quản lý đánh giá của khách hàng';

// -- Filters --
$filter_hotel      = isset($_GET['hotel_id']) ? (int)$_GET['hotel_id']    : 0;
$filter_star       = isset($_GET['star'])     ? (int)$_GET['star']        : 0;
$filter_visibility = $_GET['vis'] ?? 'active'; // active | hidden | all

$hotels_list = $conn->query("SELECT id, name FROM hotels WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$where = "WHERE 1=1";
if ($filter_visibility === 'active') $where .= " AND r.is_active = 1";
elseif ($filter_visibility === 'hidden') $where .= " AND r.is_active = 0";
if ($filter_hotel) $where .= " AND r.hotel_id = $filter_hotel";
if ($filter_star)  $where .= " AND r.rating = $filter_star";

$reviews = $conn->query("
    SELECT r.id, r.rating, r.comment, r.created_at, r.is_active,
        u.full_name, u.email,
        h.name AS hotel_name, h.id AS hotel_id,
        b.order_code
    FROM   reviews  r
    JOIN   users    u ON u.id = r.user_id
    JOIN   hotels   h ON h.id = r.hotel_id
    JOIN   bookings b ON b.id = r.booking_id
    $where
    ORDER  BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// -- Thống kê --
$total_active = (int)$conn->query("SELECT COUNT(*) AS c FROM reviews WHERE is_active=1")->fetch_assoc()['c'];
$total_hidden = (int)$conn->query("SELECT COUNT(*) AS c FROM reviews WHERE is_active=0")->fetch_assoc()['c'];
$avg_r        = (float)$conn->query("SELECT ROUND(AVG(rating),1) AS a FROM reviews WHERE is_active=1")->fetch_assoc()['a'];

include "../includes/admin_header.php";
?>

<style>
.rv-admin-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:14px;margin-bottom:22px}
.rv-stat-card{background:#fff;border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.06);text-align:center}
.rv-stat-card.accent{background:linear-gradient(135deg,#1e73be,#2d9cdb);color:#fff}
.rv-stat-card.hidden-card{background:linear-gradient(135deg,#744210,#b7791f);color:#fff}
.rv-stat-num{font-size:28px;font-weight:800;line-height:1}
.rv-stat-label{font-size:12px;margin-top:4px;opacity:.85;font-weight:500}
.rv-filter-bar{background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.rv-filter-select,.rv-vis-btn{padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;text-decoration:none;color:#2d3748;white-space:nowrap}
.rv-vis-btn.active{background:#1e73be;color:#fff;border-color:#1e73be}
.rv-vis-btn.hidden-btn.active{background:#744210;color:#fff;border-color:#744210}
.rv-table-wrap{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden}
.rv-table-wrap table{width:100%;border-collapse:collapse}
.rv-table-wrap th{background:#f7fafc;padding:11px 14px;text-align:left;font-size:12px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.4px}
.rv-table-wrap td{padding:13px 14px;border-top:1px solid #f0f4f8;vertical-align:middle}
.rv-table-wrap tr.hidden-row td{opacity:.55;background:#fffbeb}
.rv-user-cell{display:flex;align-items:center;gap:10px}
.rv-mini-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1e73be,#38a169);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.td-name{font-weight:600;font-size:13px;color:#2d3748}
.rv-hotel-link{color:#1e73be;font-weight:600;font-size:13px;text-decoration:none}
.rv-hotel-link:hover{text-decoration:underline}
.rv-order-code{font-family:monospace;font-size:12px;background:#f0f4f8;padding:3px 8px;border-radius:6px;color:#4a5568}
.rv-stars-cell{display:flex;align-items:center;gap:2px}
.rv-s{fill:#e2e8f0}.rv-s.on{fill:#f59e0b}
.verified-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;background:#f0fff4;color:#276749;border-radius:10px;font-size:10.5px;font-weight:700;margin-top:3px}
.rv-hidden-badge{display:inline-block;padding:2px 7px;background:#fffbeb;color:#b7791f;border-radius:10px;font-size:11px;font-weight:700}
.btn-rv-hide{padding:6px 12px;background:#fffbeb;color:#b7791f;border:1.5px solid #fbd38d;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer}
.btn-rv-show{padding:6px 12px;background:#f0fff4;color:#276749;border:1.5px solid #9ae6b4;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer}
.btn-rv-del{padding:6px 12px;background:#fff5f5;color:#c53030;border:1.5px solid #feb2b2;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;margin-left:4px}
.rv-toast{position:fixed;bottom:28px;right:28px;padding:12px 22px;background:#276749;color:#fff;border-radius:12px;font-size:13.5px;font-weight:700;box-shadow:0 4px 20px rgba(0,0,0,.2);display:none;z-index:9999}
</style>

<div class="page-header">
    <h2>⭐ Quản lý đánh giá</h2>
</div>

<!-- Thống kê -->
<div class="rv-admin-stats">
    <div class="rv-stat-card accent">
        <div class="rv-stat-num"><?= $total_active ?></div>
        <div class="rv-stat-label">Đang hiển thị</div>
    </div>
    <div class="rv-stat-card hidden-card">
        <div class="rv-stat-num"><?= $total_hidden ?></div>
        <div class="rv-stat-label">Đã ẩn</div>
    </div>
    <div class="rv-stat-card">
        <div class="rv-stat-num"><?= number_format($avg_r, 1) ?> ⭐</div>
        <div class="rv-stat-label">Điểm TB</div>
    </div>
    <?php for ($s = 5; $s >= 1; $s--):
        $cnt = (int)$conn->query("SELECT COUNT(*) AS c FROM reviews WHERE rating=$s")->fetch_assoc()['c'];
    ?>
    <div class="rv-stat-card">
        <div class="rv-stat-num"><?= $cnt ?></div>
        <div class="rv-stat-label"><?= $s ?> sao</div>
    </div>
    <?php endfor; ?>
</div>

<!-- Bộ lọc -->
<div class="rv-filter-bar">
    <?php
    $vis_qs = $filter_hotel ? "&hotel_id=$filter_hotel" : '';
    $vis_qs .= $filter_star ? "&star=$filter_star" : '';
    ?>
    <a href="?vis=active<?= $vis_qs ?>"
       class="rv-vis-btn <?= $filter_visibility==='active'?'active':'' ?>">Đang hiển thị</a>
    <a href="?vis=hidden<?= $vis_qs ?>"
       class="rv-vis-btn hidden-btn <?= $filter_visibility==='hidden'?'active':'' ?>">Đã ẩn</a>
    <a href="?vis=all<?= $vis_qs ?>"
       class="rv-vis-btn <?= $filter_visibility==='all'?'active':'' ?>">Tất cả</a>

    <select class="rv-filter-select" onchange="applyFilter('hotel_id',this.value)">
        <option value="">Tất cả khách sạn</option>
        <?php foreach ($hotels_list as $h): ?>
        <option value="<?= $h['id'] ?>" <?= $filter_hotel==$h['id']?'selected':'' ?>>
            <?= htmlspecialchars($h['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select class="rv-filter-select" onchange="applyFilter('star',this.value)">
        <option value="">Tất cả số sao</option>
        <?php for ($s=5; $s>=1; $s--): ?>
        <option value="<?= $s ?>" <?= $filter_star==$s?'selected':'' ?>><?= $s ?> sao</option>
        <?php endfor; ?>
    </select>

    <span style="font-size:12.5px;color:#a0aec0;margin-left:auto">
        <?= count($reviews) ?> đánh giá
    </span>
</div>

<!-- Bảng đánh giá -->
<div class="rv-table-wrap">
    <?php if (empty($reviews)): ?>
    <div style="text-align:center;padding:60px;color:#a0aec0">Không có đánh giá nào.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Khách hàng</th>
                <th>Khách sạn</th>
                <th>Booking</th>
                <th>Đánh giá</th>
                <th>Nhận xét</th>
                <th>Thời gian</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reviews as $i => $rv): $is_hidden = !(bool)$rv['is_active']; ?>
        <tr id="rv-row-<?= $rv['id'] ?>" class="<?= $is_hidden ? 'hidden-row' : '' ?>">
            <td style="color:#a0aec0;font-size:12px"><?= $i+1 ?></td>
            <td>
                <div class="rv-user-cell">
                    <div class="rv-mini-avatar">
                        <?= mb_strtoupper(mb_substr($rv['full_name'] ?: $rv['email'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="td-name"><?= htmlspecialchars($rv['full_name'] ?: 'Ẩn danh') ?></div>
                        <div style="font-size:11px;color:#a0aec0"><?= htmlspecialchars($rv['email']) ?></div>
                        <div class="verified-badge">✓ Verified Stay</div>
                    </div>
                </div>
            </td>
            <td>
                <a href="/pages/hotel_detail.php?id=<?= $rv['hotel_id'] ?>" target="_blank" class="rv-hotel-link">
                    <?= htmlspecialchars($rv['hotel_name']) ?>
                </a>
            </td>
            <td><span class="rv-order-code"><?= htmlspecialchars($rv['order_code'] ?? '#'.$rv['id']) ?></span></td>
            <td>
                <div class="rv-stars-cell">
                    <?php for ($i2=1; $i2<=5; $i2++): ?>
                    <svg class="rv-s <?= $i2<=$rv['rating']?'on':'' ?>" viewBox="0 0 24 24" width="14" height="14">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                    <?php endfor; ?>
                    <strong style="font-size:12px;color:#2d3748;margin-left:4px"><?= $rv['rating'] ?></strong>
                </div>
                <?php if ($is_hidden): ?>
                <div class="rv-hidden-badge">Đã ẩn</div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-size:12.5px;color:#4a5568;max-width:200px;line-height:1.5"
                     title="<?= htmlspecialchars($rv['comment']) ?>">
                    <?= htmlspecialchars(mb_substr($rv['comment'], 0, 65)) ?><?= mb_strlen($rv['comment'])>65?'...':'' ?>
                </div>
            </td>
            <td style="font-size:12px;color:#718096;white-space:nowrap">
                <?= date('d/m/Y H:i', strtotime($rv['created_at'])) ?>
            </td>
            <td style="white-space:nowrap">
                <?php if ($is_hidden): ?>
                <button class="btn-rv-show" onclick="rvAction(<?= $rv['id'] ?>,'show',this)">Hiện lại</button>
                <?php else: ?>
                <button class="btn-rv-hide" onclick="rvAction(<?= $rv['id'] ?>,'hide',this)">Ẩn</button>
                <?php endif; ?>
                <button class="btn-rv-del" onclick="rvAction(<?= $rv['id'] ?>,'delete',this)">Xoá</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="rv-toast" id="rvToast"></div>

<script>
function applyFilter(key, val) {
    var params = new URLSearchParams(window.location.search);
    if (val) params.set(key, val); else params.delete(key);
    window.location = '?' + params.toString();
}

function _rvActionExec(id, action, btn) {
    var label = action === 'delete' ? 'Xoá' : (action === 'hide' ? 'Ẩn' : 'Hiện');
    var fd = new FormData();
    fd.append('rv_action', action);
    fd.append('rv_id', id);

    fetch('reviews.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (action === 'delete') {
                    var row = document.getElementById('rv-row-' + id);
                    if (row) row.remove();
                    showToast('Đã xoá đánh giá.');
                } else {
                    showToast(label + ' đánh giá thành công. Đang tải lại...');
                    setTimeout(() => location.reload(), 800);
                }
            }
        })
        .catch(() => showToast('Có lỗi, vui lòng thử lại.'));
}

function rvAction(id, action, btn) {
    if (action === 'delete') {
        adminConfirmCallback('Xoá vĩnh viễn đánh giá này? Không thể khôi phục.', function() {
            _rvActionExec(id, action, btn);
        }, '🗑️');
        return;
    }
    _rvActionExec(id, action, btn);
}

function showToast(msg) {
    var t = document.getElementById('rvToast');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 2500);
}
</script>

<?php include "../includes/admin_footer.php"; ?>
