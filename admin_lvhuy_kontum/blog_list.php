<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

// Xử lý xóa
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id=?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    header('Location: blog_list.php?msg=deleted');
    exit;
}

// Xử lý toggle is_active
if (isset($_GET['toggle'])) {
    $tog_id = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE blog_posts SET is_active = 1 - is_active WHERE id=?");
    $stmt->bind_param("i", $tog_id);
    $stmt->execute();
    header('Location: blog_list.php?' . http_build_query(array_filter([
        'search'   => $_GET['search']   ?? '',
        'category' => $_GET['category'] ?? '',
        'p'        => $_GET['p']        ?? '',
    ])));
    exit;
}

// ---------- Tham số ----------
$search   = trim($_GET['search']   ?? '');
$cat      = trim($_GET['category'] ?? '');
$per_page = 12;
$page     = max(1, (int)($_GET['p'] ?? 1));

// Lấy danh sách category từ DB
$cats_raw = $conn->query("SELECT DISTINCT category FROM blog_posts WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);
$cats     = array_column($cats_raw, 'category');

// WHERE
$where_parts = [];
if ($search) {
    $sw = $conn->real_escape_string($search);
    $where_parts[] = "(title LIKE '%$sw%' OR author LIKE '%$sw%')";
}
if ($cat) {
    $where_parts[] = "category = '" . $conn->real_escape_string($cat) . "'";
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$total_rows  = $conn->query("SELECT COUNT(*) as c FROM blog_posts $where")->fetch_assoc()['c'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$blogs = $conn->query("SELECT id, title, category, author, is_active, created_at FROM blog_posts $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

// Đếm theo category
$cat_counts = [];
$cr = $conn->query("SELECT category, COUNT(*) as c FROM blog_posts GROUP BY category");
while ($r = $cr->fetch_assoc()) $cat_counts[$r['category']] = $r['c'];

function blog_qs($overrides = []) {
    $base    = ['search' => $_GET['search'] ?? '', 'category' => $_GET['category'] ?? ''];
    $merged  = array_merge($base, $overrides);
    $filtered = [];
    foreach ($merged as $k => $v) { if ($v !== '') $filtered[$k] = $v; }
    return http_build_query($filtered);
}

$page_title = 'Quản Lý Bài Viết';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="content">
    <div class="page-header">
        <div>
            <h2>📝 Quản Lý Bài Viết</h2>
            <p style="font-size:13px;color:#a0aec0;margin-top:3px">Thêm, sửa, xóa bài viết blog du lịch</p>
        </div>
        <a href="blog_form.php" class="btn btn-add">+ Thêm bài viết</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div style="padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:600;
        background:<?= $_GET['msg']==='deleted'?'#fee2e2':'#d1fae5' ?>;
        color:<?= $_GET['msg']==='deleted'?'#991b1b':'#065f46' ?>;
        border:1px solid <?= $_GET['msg']==='deleted'?'#fca5a5':'#6ee7b7' ?>">
        <?php $msgs = ['deleted'=>'🗑️ Đã xóa bài viết.','saved'=>'✅ Đã lưu bài viết.','updated'=>'✅ Đã cập nhật.'];
        echo $msgs[$_GET['msg']] ?? ''; ?>
    </div>
    <?php endif; ?>

    <!-- Filter tabs theo category -->
    <div class="filter-tabs" style="margin-bottom:12px">
        <?php
        $total_all = $conn->query("SELECT COUNT(*) as c FROM blog_posts")->fetch_assoc()['c'];
        $tab_active = ($cat === '') ? 'active' : '';
        ?>
        <a href="blog_list.php?<?= blog_qs(['category' => '', 'p' => '']) ?>" class="filter-tab <?= $tab_active ?>">
            📝 Tất cả (<?= $total_all ?>)
        </a>
        <?php foreach ($cats as $c_val):
            $active = ($cat === $c_val) ? 'active' : '';
            $cnt    = $cat_counts[$c_val] ?? 0;
        ?>
        <a href="blog_list.php?<?= blog_qs(['category' => $c_val, 'p' => '']) ?>" class="filter-tab <?= $active ?>">
            <?= htmlspecialchars($c_val) ?> (<?= $cnt ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" action="">
        <?php if ($cat): ?><input type="hidden" name="category" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
        <div class="search-bar" style="margin-bottom:16px">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2" style="flex-shrink:0">
                <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" name="search"
                placeholder="Tìm theo tiêu đề hoặc tác giả..."
                value="<?= htmlspecialchars($search) ?>">
            <?php if ($search): ?>
                <span class="search-result-info">Tìm thấy <strong><?= $total_rows ?></strong> bài</span>
                <a href="blog_list.php<?= $cat ? '?category=' . urlencode($cat) : '' ?>" class="btn-reset">✕ Xóa</a>
            <?php endif; ?>
            <button type="submit" class="btn-search">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                </svg>
                Tìm kiếm
            </button>
        </div>
    </form>

    <div class="section-card">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">ID</th>
                    <th>Tiêu đề</th>
                    <th style="width:120px">Danh mục</th>
                    <th style="width:150px">Tác giả</th>
                    <th style="width:110px">Ngày tạo</th>
                    <th style="width:90px;text-align:center">Hiển thị</th>
                    <th style="width:190px">Hành động</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($blogs->num_rows === 0): ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:#a0aec0;padding:40px">
                        <?= $search ? 'Không tìm thấy bài viết nào với "<strong>' . htmlspecialchars($search) . '</strong>"' : 'Chưa có bài viết nào.' ?>
                    </td>
                </tr>
            <?php else: ?>
            <?php while ($row = $blogs->fetch_assoc()):
                $cat_colors = [
                    'Kon Tum'    => ['#eff6ff', '#1d4ed8'],
                    'Mang Đen'   => ['#f0fdf4', '#15803d'],
                    'Quảng Ngãi' => ['#fff7ed', '#c2410c'],
                ];
                $cc = $cat_colors[$row['category']] ?? ['#f1f5f9', '#64748b'];
                $title_hl = htmlspecialchars($row['title']);
                if ($search) {
                    $kw = preg_quote(htmlspecialchars($search), '/');
                    $title_hl = preg_replace('/(' . $kw . ')/i', '<span class="search-highlight">$1</span>', $title_hl);
                }
            ?>
                <tr>
                    <td style="color:#a0aec0;font-size:12px">#<?= $row['id'] ?></td>
                    <td>
                        <span style="font-weight:600;color:#1a202c;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= $title_hl ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge" style="background:<?= $cc[0] ?>;color:<?= $cc[1] ?>">
                            <?= htmlspecialchars($row['category']) ?>
                        </span>
                    </td>
                    <td style="color:#718096;font-size:13px"><?= htmlspecialchars($row['author']) ?></td>
                    <td style="color:#a0aec0;font-size:12.5px"><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                    <td style="text-align:center">
                        <a href="blog_list.php?toggle=<?= $row['id'] ?>&<?= blog_qs() ?>"
                           class="status-badge" style="text-decoration:none;cursor:pointer;background:<?= $row['is_active'] ? '#d1fae5' : '#fee2e2' ?>;color:<?= $row['is_active'] ? '#065f46' : '#991b1b' ?>"
                           title="Nhấn để <?= $row['is_active'] ? 'ẩn' : 'hiện' ?>">
                            <?= $row['is_active'] ? '✅ Hiện' : '🙈 Ẩn' ?>
                        </a>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            <a href="/pages/blog-detail.php?id=<?= $row['id'] ?>"
                               target="_blank" class="btn btn-edit" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd;">👁️</a>
                            <a href="blog_form.php?id=<?= $row['id'] ?>" class="btn btn-edit">✏️ Sửa</a>
                            <a href="blog_list.php?delete=<?= $row['id'] ?>&<?= blog_qs(['p' => $page]) ?>"
                               class="btn btn-delete"
                               onclick="return confirm('Xóa bài viết này?')">🗑️ Xóa</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap">
        <?php if ($page > 1): ?>
            <a href="blog_list.php?<?= blog_qs(['p' => $page - 1]) ?>"
               style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">‹ Trước</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
        if ($start > 1): ?>
            <a href="blog_list.php?<?= blog_qs(['p' => 1]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">1</a>
            <?php if ($start > 2): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="blog_list.php?<?= blog_qs(['p' => $i]) ?>"
               style="padding:7px 12px;border-radius:8px;border:1.5px solid <?= $i == $page ? '#1e73be' : '#e2e8f0' ?>;color:<?= $i == $page ? '#fff' : '#4a5568' ?>;background:<?= $i == $page ? '#1e73be' : '#fff' ?>;text-decoration:none;font-size:13px;font-weight:<?= $i == $page ? '700' : '400' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
        <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span style="color:#a0aec0;padding:0 4px">…</span><?php endif; ?>
            <a href="blog_list.php?<?= blog_qs(['p' => $total_pages]) ?>" style="padding:7px 12px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff"><?= $total_pages ?></a>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
            <a href="blog_list.php?<?= blog_qs(['p' => $page + 1]) ?>"
               style="padding:7px 14px;border-radius:8px;border:1.5px solid #e2e8f0;color:#4a5568;text-decoration:none;font-size:13px;background:#fff">Tiếp ›</a>
        <?php endif; ?>
        <span style="font-size:12px;color:#a0aec0;margin-left:8px">Trang <?= $page ?>/<?= $total_pages ?> · <?= $total_rows ?> bài viết</span>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
