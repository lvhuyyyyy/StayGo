<?php
include "../config/database.php";

// ---------- Toggle status ----------
if (isset($_GET['toggle_status'])) {
    $tid = (int)$_GET['toggle_status'];
    $cur = $conn->query("SELECT status, role FROM users WHERE id=$tid")->fetch_assoc();
    if ($cur && $cur['role'] !== 'admin') {
        $new_status = ($cur['status'] === 'active') ? 'inactive' : 'active';
        $conn->query("UPDATE users SET status='$new_status' WHERE id=$tid");
    }
    // Giữ lại query string khi redirect
    $qs = http_build_query(array_filter([
        'search'        => $_GET['search']        ?? '',
        'role'          => $_GET['role']           ?? '',
        'status_filter' => $_GET['status_filter']  ?? '',
        'p'             => $_GET['p']              ?? '',
    ]));
    header("Location: users.php" . ($qs ? "?$qs" : ''));
    exit;
}

// ---------- Toggle is_banned ----------
if (isset($_GET['toggle_ban'])) {
    $tid = (int)$_GET['toggle_ban'];
    $cur = $conn->query("SELECT is_banned, role FROM users WHERE id=$tid")->fetch_assoc();
    if ($cur && $cur['role'] !== 'admin') {
        $new_ban = $cur['is_banned'] ? 0 : 1;
        $conn->query("UPDATE users SET is_banned=$new_ban WHERE id=$tid");
    }
    $qs = http_build_query(array_filter([
        'search'        => $_GET['search']        ?? '',
        'role'          => $_GET['role']           ?? '',
        'status_filter' => $_GET['status_filter']  ?? '',
        'p'             => $_GET['p']              ?? '',
    ]));
    header("Location: users.php" . ($qs ? "?$qs" : ''));
    exit;
}

$page_title    = 'Quản lý User';
$page_subtitle = 'Danh sách tài khoản hệ thống';

include "../includes/admin_header.php";

// ---------- Tham số ----------
$search      = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? '';
if (!in_array($filter_role, ['', 'admin', 'user'])) $filter_role = '';

$per_page    = 15;
$page          = max(1, (int)($_GET['p'] ?? 1));
$offset        = ($page - 1) * $per_page;
$filter_status = $_GET['status_filter'] ?? '';
if (!in_array($filter_status, ['', 'active', 'inactive', 'banned'])) $filter_status = '';

// ---------- WHERE ----------
$where_parts = [];
if ($search) {
    $sw = $conn->real_escape_string($search);
    $where_parts[] = "(full_name LIKE '%$sw%' OR email LIKE '%$sw%' OR phone LIKE '%$sw%')";
}
if ($filter_role) {
    $where_parts[] = "role = '" . $conn->real_escape_string($filter_role) . "'";
}
if ($filter_status === 'active')   $where_parts[] = "status='active' AND is_banned=0";
if ($filter_status === 'inactive') $where_parts[] = "status='inactive'";
if ($filter_status === 'banned')   $where_parts[] = "is_banned=1";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ---------- Đếm tổng ----------
$total_rows = $conn->query("SELECT COUNT(*) as c FROM users $where")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$result = $conn->query("SELECT * FROM users $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");

// Đếm theo role cho badge
$cnt_all      = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$cnt_admin    = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$cnt_user     = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='user'")->fetch_assoc()['c'];
$cnt_active   = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active' AND is_banned=0")->fetch_assoc()['c'];
$cnt_inactive = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='inactive'")->fetch_assoc()['c'];
$cnt_banned   = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_banned=1")->fetch_assoc()['c'];

// Helper: giữ nguyên query string khi phân trang
function users_qs($overrides = []) {
    $base = [
        'search'        => $_GET['search']        ?? '',
        'role'          => $_GET['role']           ?? '',
        'status_filter' => $_GET['status_filter']  ?? '',
    ];
    $merged = array_merge($base, $overrides);
    $filtered = [];
    foreach ($merged as $k => $v) { if ($v !== '') $filtered[$k] = $v; }
    return http_build_query($filtered);
}
?>

<!-- ---------- HEADER ---------- -->
<div class="page-header">
    <h2>👥 Danh sách Users</h2>
    <a href="edit_user.php" class="btn btn-add" style="margin-bottom:0">+ Thêm user</a>
</div>

<!-- Thông báo -->
<?php if (isset($_GET['deleted'])): ?>
    <div id="notify-bar" style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:10px 16px;border-radius:8px;margin-bottom:14px;font-weight:600">
        Đã xóa tài khoản <strong><?= htmlspecialchars(urldecode($_GET['name'] ?? '')) ?></strong> thành công!
    </div>
    <script>setTimeout(() => { const el = document.getElementById('notify-bar'); if(el) el.style.animation = 'fadeOut .5s forwards'; setTimeout(() => el?.remove(), 500); }, 3000);</script>
<?php elseif (isset($_GET['success'])): ?>
    <div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:10px 16px;border-radius:8px;margin-bottom:14px">
        <?php
        $success_msgs = ['added' => 'Đã thêm user thành công!', 'updated' => 'Đã cập nhật user thành công!'];
        echo $success_msgs[$_GET['success']] ?? 'Thao tác thành công!';
        ?>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <div style="background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:10px 16px;border-radius:8px;margin-bottom:14px">
        <?php
        $error_msgs = [
            'invalid'             => 'ID không hợp lệ.',
            'invalid_id'          => 'ID không hợp lệ.',
            'not_found'           => 'Không tìm thấy user.',
            'notfound'            => 'Không tìm thấy user.',
            'cannot_delete_admin' => 'Không thể xóa tài khoản Admin!',
            'failed'              => 'Xóa thất bại, vui lòng thử lại.',
        ];
        echo $error_msgs[$_GET['error']] ?? 'Có lỗi xảy ra.';
        ?>
    </div>
<?php endif; ?>

<!-- ---------- FILTER TABS (Role) ---------- -->
<div class="filter-tabs" style="margin-bottom:10px">
    <?php
    $tabs = ['' => "👥 Tất cả ($cnt_all)", 'user' => "🙋 User ($cnt_user)", 'admin' => "🛡️ Admin ($cnt_admin)"];
    foreach ($tabs as $val => $label):
        $active = ($filter_role === $val) ? 'active' : '';
        $href   = 'users.php?' . users_qs(['role' => $val, 'p' => '']);
    ?>
    <a href="<?= $href ?>" class="filter-tab <?= $active ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- ---------- FILTER TABS (Status) ---------- -->
<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <?php
    $status_tabs = [
        'active'   => ['label' => "✅ Hoạt động ($cnt_active)",  'color' => '#276749', 'bg' => '#f0fff4', 'abg' => '#276749'],
        'inactive' => ['label' => "⏸ Tạm dừng ($cnt_inactive)", 'color' => '#b7791f', 'bg' => '#fffbeb', 'abg' => '#b7791f'],
        'banned'   => ['label' => "🚫 Bị cấm ($cnt_banned)",    'color' => '#c53030', 'bg' => '#fff5f5', 'abg' => '#c53030'],
    ];
    foreach ($status_tabs as $val => $cfg):
        $is_active = ($filter_status === $val);
        $href = 'users.php?' . users_qs(['status_filter' => $val, 'p' => '']);
        $style = $is_active
            ? "padding:6px 16px;border-radius:20px;font-size:12.5px;font-weight:700;text-decoration:none;border:2px solid {$cfg['abg']};color:#fff;background:{$cfg['abg']}"
            : "padding:6px 16px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;border:1.5px solid #e2e8f0;color:{$cfg['color']};background:{$cfg['bg']}";
    ?>
    <a href="<?= $href ?>" style="<?= $style ?>"><?= $cfg['label'] ?></a>
    <?php endforeach; ?>
</div>

<!-- ---------- SEARCH ---------- -->
<form method="GET" action="">
    <?php if ($filter_role): ?><input type="hidden" name="role" value="<?= htmlspecialchars($filter_role) ?>"><?php endif; ?>
    <div class="search-bar">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
            <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search"
            placeholder="Tìm theo tên, email, số điện thoại..."
            value="<?= htmlspecialchars($search) ?>">
        <?php if ($search): ?>
            <span class="search-result-info">Tìm thấy <strong><?= $total_rows ?></strong> user</span>
            <a href="users.php<?= $filter_role ? "?role=$filter_role" : '' ?>" class="btn-reset">✕ Xóa</a>
        <?php endif; ?>
        <button type="submit" class="btn-search">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
            </svg>
            Tìm kiếm
        </button>
    </div>
</form>

<!-- ---------- BẢNG DANH SÁCH ---------- -->
<div class="section-card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Họ tên</th>
                <th>Email</th>
                <th>Điện thoại</th>
                <th>Role</th>
                <th>Ngày đăng ký</th>
                <th>Hành động</th>
                <th>Trạng thái hoạt động</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="8" style="text-align:center;color:#a0aec0;padding:30px">
                    <?= $search ? 'Không tìm thấy user nào với từ khóa "<strong>' . htmlspecialchars($search) . '</strong>"' : 'Chưa có user nào' ?>
                </td>
            </tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td class="td-order"><?= $row['id'] ?></td>
                <td class="td-name"><?= htmlspecialchars($row['full_name']) ?></td>
                <td style="color:#718096"><?= htmlspecialchars($row['email']) ?></td>
                <td style="color:#718096;font-size:12.5px"><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                <td>
                    <?php if ($row['role'] === 'admin'): ?>
                        <span class="status-badge" style="color:#1e73be;background:#ebf8ff">🛡️ Admin</span>
                    <?php else: ?>
                        <span class="status-badge" style="color:#276749;background:#f0fff4">🙋 User</span>
                    <?php endif; ?>
                </td>
                <td style="color:#a0aec0;font-size:12px">
                    <?= !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '—' ?>
                </td>
                <?php
                $current_admin_id = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
                $is_banned  = !empty($row['is_banned']);
                $is_active  = ($row['status'] ?? 'active') === 'active';
                $toggle_qs  = users_qs(['p' => $page]);
                $can_edit   = ($row['role'] !== 'admin' || $row['id'] === $current_admin_id);
                $can_toggle = ($row['role'] !== 'admin' && $row['id'] !== $current_admin_id);
                ?>
                <td style="display:flex;gap:6px;align-items:center">
                    <a href="edit_user.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-edit">Sửa</a>
                    <?php if ($can_toggle): ?>
                    <a href="javascript:void(0)"
                       onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>')"
                       class="btn-delete">Xóa</a>
                    <?php else: ?>
                    <span style="font-size:12px;color:#a0aec0;font-style:italic">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_banned): ?>
                        <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start">
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12.5px;font-weight:700;color:#c53030;background:#fff5f5;border:1.5px solid #feb2b2">
                                🚫 Bị cấm
                            </span>
                            <?php if ($can_toggle): ?>
                            <a href="users.php?toggle_ban=<?= $row['id'] ?>&<?= $toggle_qs ?>"
                               style="font-size:11px;color:#276749;text-decoration:none;font-weight:600;padding:3px 10px;border-radius:6px;background:#f0fff4;border:1px solid #9ae6b4">
                                ✅ Bỏ cấm
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($is_active): ?>
                        <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start">
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12.5px;font-weight:700;color:#276749;background:#f0fff4;border:1.5px solid #9ae6b4">
                                ✅ Hoạt động
                            </span>
                            <?php if ($can_toggle): ?>
                            <div style="display:flex;gap:5px">
                                <a href="users.php?toggle_status=<?= $row['id'] ?>&<?= $toggle_qs ?>"
                                   style="font-size:11px;color:#b7791f;text-decoration:none;font-weight:600;padding:3px 10px;border-radius:6px;background:#fffbeb;border:1px solid #f6e05e">
                                    ⏸ Tạm dừng
                                </a>
                                <a href="users.php?toggle_ban=<?= $row['id'] ?>&<?= $toggle_qs ?>"
                                   style="font-size:11px;color:#c53030;text-decoration:none;font-weight:600;padding:3px 10px;border-radius:6px;background:#fff5f5;border:1px solid #feb2b2">
                                    🚫 Cấm
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start">
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12.5px;font-weight:700;color:#b7791f;background:#fffbeb;border:1.5px solid #f6e05e">
                                ⏸ Tạm dừng
                            </span>
                            <?php if ($can_toggle): ?>
                            <div style="display:flex;gap:5px">
                                <a href="users.php?toggle_status=<?= $row['id'] ?>&<?= $toggle_qs ?>"
                                   style="font-size:11px;color:#276749;text-decoration:none;font-weight:600;padding:3px 10px;border-radius:6px;background:#f0fff4;border:1px solid #9ae6b4">
                                    ▶ Kích hoạt
                                </a>
                                <a href="users.php?toggle_ban=<?= $row['id'] ?>&<?= $toggle_qs ?>"
                                   style="font-size:11px;color:#c53030;text-decoration:none;font-weight:600;padding:3px 10px;border-radius:6px;background:#fff5f5;border:1px solid #feb2b2">
                                    🚫 Cấm
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ---------- PHÂN TRANG ---------- -->
<?php if ($total_pages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap">
    <?php if ($page > 1): ?>
        <a href="users.php?<?= users_qs(['p' => $page - 1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">‹ Trước</a>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);
    if ($start > 1): ?>
        <a href="users.php?<?= users_qs(['p' => 1]) ?>"
           style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">1</a>
        <?php if ($start > 2): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="users.php?<?= users_qs(['p' => $i]) ?>"
           style="padding:7px 12px;border-radius:8px;border:1.5px solid <?= $i == $page ? '#1e73be' : '#e2e8f0' ?>;
                  color:<?= $i == $page ? '#fff' : '#4a5568' ?>;background:<?= $i == $page ? '#1e73be' : '#fff' ?>;
                  text-decoration:none;font-size:13px;font-weight:<?= $i == $page ? '700' : '400' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
        <a href="users.php?<?= users_qs(['p' => $total_pages]) ?>"
           style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff"><?= $total_pages ?></a>
    <?php endif; ?>

    <?php if ($page < $total_pages): ?>
        <a href="users.php?<?= users_qs(['p' => $page + 1]) ?>"
           style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">Tiếp ›</a>
    <?php endif; ?>

    <span style="font-size:12px;color:#a0aec0;margin-left:8px">
        Trang <?= $page ?>/<?= $total_pages ?> · <?= $total_rows ?> user
    </span>
</div>
<?php endif; ?>

<!-- ---------- MODAL XÁC NHẬN XÓA ---------- -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:32px 28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);text-align:center;animation:popIn .2s ease">
        <div style="width:68px;height:68px;background:#fff5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px">🗑️</div>
        <h3 style="font-size:18px;font-weight:800;color:#1a202c;margin:0 0 6px">Xóa tài khoản?</h3>
        <p style="font-size:13px;color:#718096;margin:0 0 4px">Bạn sắp xóa tài khoản:</p>
        <p style="font-size:15px;font-weight:800;color:#1a202c;margin:0 0 2px" id="delete-username"></p>
        <p style="font-size:12px;color:#a0aec0;margin:0 0 16px" id="delete-email"></p>
        <div style="background:#fff5f5;border:1.5px solid #fed7d7;border-radius:10px;padding:10px 14px;margin-bottom:22px;font-size:12.5px;color:#c53030;font-weight:600;line-height:1.6">
            Hành động này sẽ xóa toàn bộ booking, đánh giá của user.<br>
            <strong>Không thể hoàn tác!</strong>
        </div>
        <div style="display:flex;gap:10px">
            <button onclick="document.getElementById('deleteModal').style.display='none'"
                style="flex:1;padding:12px;border-radius:10px;border:1.5px solid #e2e8f0;background:#fff;color:#718096;font-size:14px;font-weight:600;cursor:pointer"
                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                Hủy
            </button>
            <button type="button" id="delete-confirm-btn"
                onclick="document.getElementById('deleteUserForm').submit()"
                style="flex:1;padding:12px;border-radius:10px;background:#e53e3e;color:#fff;font-size:14px;font-weight:700;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px"
                onmouseover="this.style.background='#c53030'" onmouseout="this.style.background='#e53e3e'">
                Xác nhận xóa
            </button>
        </div>
    </div>
</div>

<form id="deleteUserForm" method="POST" action="delete_user.php" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" id="delete-user-id" name="id" value="">
</form>

<script>
function confirmDelete(id, name, email) {
    document.getElementById('delete-user-id').value = id;
    document.getElementById('delete-username').textContent = name;
    document.getElementById('delete-email').textContent = email;
    document.getElementById('deleteModal').style.display = 'flex';
}
</script>

<?php include "../includes/admin_footer.php"; ?>
