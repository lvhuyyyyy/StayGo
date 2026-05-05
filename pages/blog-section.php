<?php
//  blog-section.php  —  đặt tại: pages/blog-section.php
//  Cách dùng trong home.php: <?php include 'pages/blog-section.php'; ?>

<?php
if (!isset($blogs) || empty($blogs)) {
    $blogDataFile = __DIR__ . '/blog-data.php';
    if (file_exists($blogDataFile)) {
        include $blogDataFile;
    }
}

$featured_blogs = isset($blogs) ? array_slice($blogs, 0, 3) : [];
?>

<section class="hs-blog">
    <div class="container">  <!-- dùng chung .container với các section khác -->

        <div class="hs-blog-header">
            <div>
                <h2 class="hs-blog-title">Danh sách bài viết</h2>
                <p class="hs-blog-sub">
                    Khám phá những câu chuyện du lịch hấp dẫn về Kon Tum, Măng Đen &amp; Quảng Ngãi
                </p>
            </div>
            <a href="/tour_khach_san_project/pages/blog-list.php" class="hs-blog-viewall">
                Xem tất cả
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
            </a>
        </div>

        <div class="hs-blog-grid">
            <?php foreach ($featured_blogs as $blog): ?>
            <article class="hs-blog-card">

                <a href="/tour_khach_san_project/pages/blog-detail.php?id=<?= $blog['id'] ?>" class="hs-blog-img-link">
                    <div class="hs-blog-img">
                        <img src="<?= htmlspecialchars($blog['thumb']) ?>"
                            alt="<?= htmlspecialchars($blog['title']) ?>" loading="lazy">
                        <span class="hs-blog-cat"><?= htmlspecialchars($blog['category']) ?></span>
                    </div>
                </a>

                <div class="hs-blog-body">
                    <div class="hs-blog-meta">
                        <span>
                            <?= htmlspecialchars($blog['date']) ?>
                        </span>
                        <span>
                            <?= htmlspecialchars($blog['author']) ?>
                        </span>
                    </div>

                    <h3 class="hs-blog-post-title">
                        <a href="/tour_khach_san_project/pages/blog-detail.php?id=<?= $blog['id'] ?>">
                            <?= htmlspecialchars($blog['title']) ?>
                        </a>
                    </h3>

                    <p class="hs-blog-summary">
                        <?= htmlspecialchars(mb_substr($blog['summary'], 0, 100)) ?>...
                    </p>

                    <a href="/tour_khach_san_project/pages/blog-detail.php?id=<?= $blog['id'] ?>"
                    class="hs-blog-btn">XEM THÊM</a>
                </div>

            </article>
            <?php endforeach; ?>
        </div>

    </div>
</section>