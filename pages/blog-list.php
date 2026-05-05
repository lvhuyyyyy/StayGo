<?php
// --------------------------------------------------------
//  blog-list.php  —  Trang danh sách tất cả bài viết
//  Đặt tại: pages/blog-list.php
//  URL: /tour_khach_san_project/pages/blog-list.php
// --------------------------------------------------------

$page_title = 'Bài viết du lịch - StayGo';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';  // Lấy $conn

// -- Lấy category từ URL --
$filter_cat = isset($_GET['category']) ? trim($_GET['category']) : '';

// -- Lấy tất cả danh mục để hiển thị tab --
$cats_result = $conn->query("
    SELECT category, COUNT(*) AS cnt
    FROM   blog_posts
    WHERE  is_active = 1
    GROUP  BY category
    ORDER  BY cnt DESC
");
$categories = $cats_result ? $cats_result->fetch_all(MYSQLI_ASSOC) : [];

// -- Truy vấn bài viết (có lọc nếu có category) --
if ($filter_cat) {
    $stmt = $conn->prepare("
        SELECT id, title, category, summary, thumb, author, read_time,
            DATE_FORMAT(created_at, '%d/%m/%Y') AS date
        FROM   blog_posts
        WHERE  is_active = 1 AND category = ?
        ORDER  BY created_at DESC
    ");
    $stmt->bind_param("s", $filter_cat);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT id, title, category, summary, thumb, author, read_time,
            DATE_FORMAT(created_at, '%d/%m/%Y') AS date
        FROM   blog_posts
        WHERE  is_active = 1
        ORDER  BY created_at DESC
    ");
}
$blogs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!-- -- Hero banner -- -->
<div class="bl-hero">
    <div class="bl-container">
        <p class="bl-hero-label">KHÁM PHÁ & TRẢI NGHIỆM</p>
        <h1 class="bl-hero-title">Danh sách BLOG</h1>
        <p class="bl-hero-sub">
            Những câu chuyện du lịch hấp dẫn về Kon Tum, Măng Đen &amp; Quảng Ngãi
        </p>
    </div>
</div>

<!-- -- Tab lọc danh mục -- -->
<div class="bl-filter-bar">
    <div class="bl-container">
        <div class="bl-tabs">
            <a href="/tour_khach_san_project/pages/blog-list.php"
            class="bl-tab <?= $filter_cat === '' ? 'active' : '' ?>">
                Tất cả
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="/tour_khach_san_project/pages/blog-list.php?category=<?= urlencode($cat['category']) ?>"
            class="bl-tab <?= $filter_cat === $cat['category'] ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['category']) ?>
                <span class="bl-tab-count"><?= $cat['cnt'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- -- Danh sách bài viết -- -->
<section class="bl-section">
    <div class="bl-container">

        <!-- Hiển thị đang lọc theo danh mục nào -->
        <?php if ($filter_cat): ?>
        <div class="bl-filter-notice">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M3 7h18M6 12h12M10 17h4"/>
            </svg>
            Đang xem: <strong><?= htmlspecialchars($filter_cat) ?></strong>
            &nbsp;•&nbsp; <?= count($blogs) ?> bài viết
            <a href="/tour_khach_san_project/pages/blog-list.php" class="bl-filter-clear">✕ Xóa bộ lọc</a>
        </div>
        <?php endif; ?>

        <?php if (empty($blogs)): ?>
        <div class="bl-empty">
            <p>Chưa có bài viết nào trong danh mục này.</p>
            <a href="/tour_khach_san_project/pages/blog-list.php" class="bl-btn" style="margin-top:12px">
                Xem tất cả bài viết
            </a>
        </div>
        <?php else: ?>
        <div class="bl-list">
            <?php foreach ($blogs as $blog): ?>
            <article class="bl-card">

                <!-- Ảnh -->
                <a href="/tour_khach_san_project/pages/blog-detail.php?id=<?= $blog['id'] ?>"
                class="bl-img-link">
                    <div class="bl-img-wrap">
                        <img src="<?= htmlspecialchars($blog['thumb']) ?>"
                            alt="<?= htmlspecialchars($blog['title']) ?>"
                            loading="lazy">
                        <div class="bl-img-overlay">
                            <span class="bl-img-meta">
                                <?= htmlspecialchars($blog['date']) ?>
                            </span>
                            <span class="bl-img-meta">
                                Đăng bởi: <?= htmlspecialchars($blog['author']) ?>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- Nội dung -->
                <div class="bl-body">
                    <span class="bl-cat"><?= htmlspecialchars($blog['category']) ?></span>
                    <h2 class="bl-title">
                        <a href="/tour_khach_san_project/pages/blog-detail.php?id=<?= $blog['id'] ?>">
                            <?= htmlspecialchars($blog['title']) ?>
                        </a>
                    </h2>
                    <p class="bl-summary"><?= htmlspecialchars($blog['summary']) ?></p>
                    <div class="bl-footer">
                        <span class="bl-read-time">
                            <?= htmlspecialchars($blog['read_time']) ?>
                        </span>
                        <a href="/tour_khach_san_project/pages/blog-detail.php?id=<?= $blog['id'] ?>"
                        class="bl-btn">
                            Xem thêm
                        </a>
                    </div>
                </div>

            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>