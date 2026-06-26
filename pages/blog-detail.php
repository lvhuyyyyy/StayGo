<?php
// --------------------------------------------------------
//  blog-detail.php  —  Đặt tại: pages/blog-detail.php
//  URL: /pages/blog-detail.php?id=1
// --------------------------------------------------------

require_once __DIR__ . '/../config/database.php';

// Lấy ID từ URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// -- Lấy bài viết hiện tại từ DB --
$stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

// Nếu không tìm thấy → redirect
if (!$current) {
    header('Location: /index.php');
    exit;
}

// Format date & tags
$current['date'] = date('d/m/Y', strtotime($current['created_at']));
$current['tags'] = array_filter(array_map('trim', explode(',', $current['tags'] ?? '')));

// -- Bài viết liên quan --
$stmt2 = $conn->prepare("
    SELECT id, title, category, summary, thumb,
        DATE_FORMAT(created_at, '%d/%m/%Y') AS date, read_time
    FROM   blog_posts
    WHERE  is_active = 1 AND id != ?
    ORDER  BY
        (category = ?) DESC,
        created_at DESC
    LIMIT 3
");
$stmt2->bind_param("is", $id, $current['category']);
$stmt2->execute();
$related = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// -- Bài trước / bài sau --
$prev_row = $conn->query("
    SELECT id, title FROM blog_posts
    WHERE  is_active = 1 AND id < $id
    ORDER  BY id DESC LIMIT 1
")->fetch_assoc();

$next_row = $conn->query("
    SELECT id, title FROM blog_posts
    WHERE  is_active = 1 AND id > $id
    ORDER  BY id ASC LIMIT 1
")->fetch_assoc();

// -- Danh mục --
$cats_result = $conn->query("
    SELECT category, COUNT(*) AS cnt
    FROM   blog_posts
    WHERE  is_active = 1
    GROUP  BY category
    ORDER  BY cnt DESC
");
$categories = $cats_result->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<!-- HERO -->
<div class="bd-hero">
    <img src="/assets/images/<?= htmlspecialchars($current['img'] ?: $current['thumb']) ?>"
        alt="<?= htmlspecialchars($current['title']) ?>">
    <div class="bd-hero-overlay">
        <div class="bd-container">
            <span class="bd-cat-badge"><?= htmlspecialchars($current['category']) ?></span>
            <h1 class="bd-hero-title"><?= htmlspecialchars($current['title']) ?></h1>
            <div class="bd-hero-meta">
                <span><?= htmlspecialchars($current['date']) ?></span>
                <span><?= htmlspecialchars($current['author']) ?></span>
                <span><?= htmlspecialchars($current['read_time']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- NỘI DUNG -->
<div class="bd-layout">
    <div class="bd-container">
        <div class="bd-grid">

            <main class="bd-main">

                <!-- Tóm tắt -->
                <div class="bd-summary-box">
                    <p><?= htmlspecialchars($current['summary']) ?></p>
                </div>

                <!-- Nội dung -->
                <div class="bd-content">
                    <?= $current['content'] ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($current['tags'])): ?>
                <div class="bd-tags">
                    <span class="bd-tags-label">Tags:</span>
                    <?php foreach ($current['tags'] as $tag): ?>
                    <a href="/pages/blog-list.php?category=<?= urlencode($tag) ?>" class="bd-tag">
                        <?= htmlspecialchars($tag) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Chia sẻ -->
                <div class="bd-share">
                    <span class="bd-share-label">Chia sẻ:</span>
                    <button onclick="copyLink()" class="bd-share-btn bd-share-copy">
                        Sao chép link
                    </button>
                </div>

                <!-- Điều hướng -->
                <?php if ($prev_row || $next_row): ?>
                <div class="bd-nav">
                    <?php if ($prev_row): ?>
                    <a href="/pages/blog-detail.php?id=<?= $prev_row['id'] ?>"
                    class="bd-nav-btn bd-nav-prev">
                        <div>
                            <span>Bài trước</span>
                            <strong><?= htmlspecialchars(mb_substr($prev_row['title'], 0, 50)) ?>...</strong>
                        </div>
                    </a>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>

                    <?php if ($next_row): ?>
                    <a href="/pages/blog-detail.php?id=<?= $next_row['id'] ?>"
                    class="bd-nav-btn bd-nav-next">
                        <div>
                            <span>Bài tiếp theo</span>
                            <strong><?= htmlspecialchars(mb_substr($next_row['title'], 0, 50)) ?>...</strong>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>

            <!-- Sidebar -->
            <aside class="bd-sidebar">

                <!-- Tác giả -->
                <div class="bd-widget">
                    <div class="bd-author-card">
                        <div class="bd-author-avatar">
                            <?= mb_strtoupper(mb_substr($current['author'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="bd-author-role">Tác giả</p>
                            <p class="bd-author-name"><?= htmlspecialchars($current['author']) ?></p>
                        </div>
                    </div>
                    <p class="bd-author-desc">
                        Chuyên viên tư vấn du lịch với nhiều năm kinh nghiệm khám phá các vùng đất Kon Tum, Măng Đen và Quảng Ngãi.
                    </p>
                </div>

                <!-- Bài liên quan -->
                <?php if (!empty($related)): ?>
                <div class="bd-widget">
                    <h4 class="bd-widget-title">Bài viết liên quan</h4>
                    <?php foreach ($related as $r): ?>
                    <a href="/pages/blog-detail.php?id=<?= $r['id'] ?>"
                    class="bd-related-item">
                        <img src="/assets/images/<?= htmlspecialchars($r['thumb']) ?>" loading="lazy">
                        <div>
                            <span><?= htmlspecialchars($r['category']) ?></span>
                            <p><?= htmlspecialchars(mb_substr($r['title'], 0, 55)) ?>...</p>
                            <span><?= htmlspecialchars($r['date']) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Danh mục -->
                <div class="bd-widget">
                    <h4 class="bd-widget-title">Danh mục</h4>
                    <?php foreach ($categories as $cat): ?>
                    <a href="/pages/blog-list.php?category=<?= urlencode($cat['category']) ?>"
                    class="bd-cat-item">
                        <span><?= htmlspecialchars($cat['category']) ?></span>
                        <span><?= $cat['cnt'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

            </aside>

        </div>
    </div>
</div>

<!-- BÀI KHÁC -->
<?php if (!empty($related)): ?>
<section class="bd-more-section">
    <div class="bd-container">
        <h3 class="bd-more-title">Có thể bạn cũng thích</h3>
        <div class="bd-more-grid">
            <?php foreach ($related as $r): ?>
            <a href="/pages/blog-detail.php?id=<?= $r['id'] ?>"
            class="bd-more-card">
                <div class="bd-more-img">
                    <img src="/assets/images/<?= htmlspecialchars($r['thumb']) ?>" loading="lazy">
                    <span><?= htmlspecialchars($r['category']) ?></span>
                </div>
                <div class="bd-more-body">
                    <p><?= htmlspecialchars($r['date']) ?> • <?= htmlspecialchars($r['read_time']) ?></p>
                    <h4><?= htmlspecialchars($r['title']) ?></h4>
                    <p><?= htmlspecialchars(mb_substr($r['summary'], 0, 90)) ?>...</p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:36px;">
            <a href="/pages/blog-list.php"
            class="bd-back-btn">← Quay lại danh sách bài viết</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>