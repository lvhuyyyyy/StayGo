<?php
//  admin/blog_form.php  —  Thêm / Sửa bài viết + Xử lý upload ảnh
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

// -- Xử lý AJAX Upload ảnh --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    header('Content-Type: application/json');

    $file    = $_FILES['image'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['error' => 'Chỉ chấp nhận jpg, jpeg, png, webp, gif']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'File quá lớn (max 5MB)']);
        exit;
    }

    $upload_dir = __DIR__ . '/../assets/images/blog/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = 'blog_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['url' => '/assets/images/blog/' . $filename]);
    } else {
        echo json_encode(['error' => 'Lưu file thất bại, kiểm tra quyền thư mục']);
    }
    exit;
}
// --------------------------------------------------------------------------

$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = $id ? 'edit' : 'add';

// -- Lấy dữ liệu khi sửa --
$post = [
    'title'     => '',
    'category'  => 'Kon Tum',
    'summary'   => '',
    'content'   => '',
    'thumb'     => '',
    'img'       => '',
    'author'    => 'Admin',
    'tags'      => '',
    'read_time' => '5 phút đọc',
    'is_active' => 1,
];

if ($mode === 'edit') {
    $stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        header('Location: blog_list.php');
        exit;
    }
    $post = $row;
}

// -- Xử lý POST form --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title     = trim($_POST['title']     ?? '');
    $category  = trim($_POST['category']  ?? 'Kon Tum');
    $summary   = trim($_POST['summary']   ?? '');
    $content   = $_POST['content']        ?? '';
    $thumb     = trim($_POST['thumb']     ?? '');
    $img       = trim($_POST['img']       ?? '');
    $author    = trim($_POST['author']    ?? 'Admin');
    $tags      = trim($_POST['tags']      ?? '');
    $read_time = trim($_POST['read_time'] ?? '5 phút đọc');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($img))   $img   = $thumb;
    if (empty($thumb)) $thumb = $img;

    if ($mode === 'add') {
        $stmt = $conn->prepare("
            INSERT INTO blog_posts (title, category, summary, content, thumb, img, author, tags, read_time, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("sssssssssi",
            $title, $category, $summary, $content,
            $thumb, $img, $author, $tags, $read_time, $is_active
        );
        $stmt->execute();
        header('Location: blog_list.php?msg=saved');
    } else {
        $stmt = $conn->prepare("
            UPDATE blog_posts
            SET title=?, category=?, summary=?, content=?, thumb=?, img=?,
                author=?, tags=?, read_time=?, is_active=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("sssssssssii",
            $title, $category, $summary, $content,
            $thumb, $img, $author, $tags, $read_time, $is_active, $id
        );
        $stmt->execute();
        header('Location: blog_list.php?msg=updated');
    }
    exit;
}

$page_title    = $mode === 'add' ? 'Thêm Bài Viết' : 'Sửa Bài Viết';
$page_subtitle = $mode === 'edit'
    ? 'Chỉnh sửa: ' . mb_substr($post['title'], 0, 60) . '...'
    : date('d/m/Y');

include __DIR__ . '/../includes/admin_header.php';
?>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/ow67xpeaunybky8v0yhw32xarjc9ofsuda0we5oapc78rfzw/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#content-editor',
    height: 460,
    menubar: false,
    promotion: false,
    branding: false,
    language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@23.7.24/langs6/vi.js',
    language: 'vi',
    plugins: 'lists link image code table',
    toolbar: 'undo redo | formatselect | bold italic underline | forecolor | alignleft aligncenter alignright | bullist numlist | link image | table | code',
    content_style: 'body { font-family: "Segoe UI", Arial, sans-serif; font-size: 15px; line-height: 1.85; color: #374151; }',
    setup: function(editor) {
        editor.on('change', function() { editor.save(); });
    }
});
</script>

<div class="content">

    <!-- Header -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
        <div>
            <h2 style="font-size:20px;font-weight:800;color:#1a202c;margin:0 0 4px">
                <?= $mode === 'add' ? '➕ Thêm Bài Viết Mới' : '✏️ Sửa Bài Viết #' . $id ?>
            </h2>
            <p style="font-size:13px;color:#a0aec0;margin:0">
                <?= htmlspecialchars(mb_substr($post['title'], 0, 80)) ?>
            </p>
        </div>
        <div style="display:flex;gap:8px">
            <?php if ($mode === 'edit'): ?>
            <a href="/pages/blog-detail.php?id=<?= $id ?>"
            target="_blank" class="btn btn-edit">👁️ Xem bài viết</a>
            <?php endif; ?>
            <a href="blog_list.php" class="btn btn-edit">← Quay lại</a>
        </div>
    </div>

    <form method="POST" id="blogForm">
        <?= csrf_field() ?>

        <div style="display:grid;grid-template-columns:1fr 310px;gap:20px;align-items:start">

            <!-- -- CỘT TRÁI -- -->
            <div style="display:flex;flex-direction:column;gap:18px">

                <!-- Tiêu đề -->
                <div class="section-card" style="padding:20px">
                    <label class="field-label">Tiêu đề bài viết <span style="color:#e53e3e">*</span></label>
                    <input type="text" name="title"
                        value="<?= htmlspecialchars($post['title']) ?>"
                        placeholder="VD: KON TUM — THÀNH PHỐ CỦA NHỮNG NHÀ THỜ GỖ ĐỘC ĐÁO"
                        required
                        style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:15px;font-weight:600;color:#1a202c;box-sizing:border-box;font-family:inherit;transition:border-color .2s"
                        onfocus="this.style.borderColor='#1e73be'" onblur="this.style.borderColor='#e2e8f0'">
                </div>

                <!-- Tóm tắt -->
                <div class="section-card" style="padding:20px">
                    <label class="field-label">Tóm tắt <span style="color:#e53e3e">*</span>
                        <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:6px">Hiển thị trên trang danh sách & trang chủ</span>
                    </label>
                    <textarea name="summary" required rows="4"
                            placeholder="Mô tả ngắn gọn hấp dẫn về bài viết..."
                            style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#374151;box-sizing:border-box;font-family:inherit;resize:vertical;transition:border-color .2s"
                            onfocus="this.style.borderColor='#1e73be'" onblur="this.style.borderColor='#e2e8f0'"><?= htmlspecialchars($post['summary']) ?></textarea>
                </div>

                <!-- Nội dung -->
                <div class="section-card" style="padding:20px">
                    <label class="field-label">Nội dung bài viết <span style="color:#e53e3e">*</span></label>
                    <textarea name="content" id="content-editor"><?= $post['content'] ?></textarea>
                </div>

            </div>

            <!-- -- CỘT PHẢI -- -->
            <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:16px">

                <!-- Xuất bản -->
                <div class="section-card" style="padding:18px">
                    <h4 class="widget-title">🚀 Xuất bản</h4>

                    <label style="display:flex;align-items:center;gap:9px;margin-bottom:18px;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1"
                            <?= $post['is_active'] ? 'checked' : '' ?>
                            style="width:17px;height:17px;accent-color:#1e73be;cursor:pointer">
                        <span style="font-size:14px;font-weight:600;color:#374151">Hiển thị bài viết</span>
                    </label>

                    <button type="submit" style="
                        width:100%;padding:11px;background:#1e73be;color:#fff;border:none;
                        border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;
                        transition:background .2s;display:flex;align-items:center;justify-content:center;gap:8px;
                        margin-bottom:8px" onmouseover="this.style.background='#155d9e'" onmouseout="this.style.background='#1e73be'">
                        💾 <?= $mode === 'add' ? 'Đăng bài viết' : 'Lưu thay đổi' ?>
                    </button>
                    <a href="blog_list.php" style="display:block;text-align:center;padding:9px;background:#f1f5f9;color:#64748b;border-radius:8px;font-size:13.5px;font-weight:600;text-decoration:none">
                        Huỷ bỏ
                    </a>
                </div>

                <!-- Thông tin -->
                <div class="section-card" style="padding:18px">
                    <h4 class="widget-title">📋 Thông tin</h4>

                    <div class="field-group">
                        <label class="field-label">Danh mục <span style="color:#e53e3e">*</span></label>
                        <select name="category" style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13.5px;box-sizing:border-box;background:#fff;cursor:pointer">
                            <?php foreach (['Kon Tum', 'Mang Đen', 'Quảng Ngãi'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $post['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Tác giả</label>
                        <input type="text" name="author"
                            value="<?= htmlspecialchars($post['author']) ?>"
                            placeholder="VD: NGUYỄN THỊ MAI"
                            style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13.5px;box-sizing:border-box;font-family:inherit">
                    </div>

                    <div class="field-group">
                        <label class="field-label">Thời gian đọc</label>
                        <input type="text" name="read_time"
                            value="<?= htmlspecialchars($post['read_time']) ?>"
                            placeholder="5 phút đọc"
                            style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13.5px;box-sizing:border-box;font-family:inherit">
                    </div>

                    <div class="field-group" style="margin-bottom:0">
                        <label class="field-label">Tags
                            <span style="font-size:11px;color:#a0aec0;font-weight:400">(phân cách bằng dấu phẩy)</span>
                        </label>
                        <input type="text" name="tags"
                            value="<?= htmlspecialchars($post['tags']) ?>"
                            placeholder="VD: Kon Tum, Du lịch, Tây Nguyên"
                            style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13.5px;box-sizing:border-box;font-family:inherit">
                        <div id="tags-preview" style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px"></div>
                    </div>
                </div>

                <!-- Ảnh -->
                <div class="section-card" style="padding:18px">
                    <h4 class="widget-title">🖼️ Ảnh bài viết</h4>

                    <!-- Thumbnail -->
                    <div class="field-group">
                        <label class="field-label">Ảnh thumbnail <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="thumb" id="inp-thumb"
                            value="<?= htmlspecialchars($post['thumb']) ?>"
                            placeholder="https://... hoặc upload bên dưới"
                            oninput="previewImg(this.value,'prev-thumb')"
                            style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:12.5px;box-sizing:border-box;font-family:inherit">

                        <label class="upload-btn" id="lbl-thumb">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <span id="lbl-thumb-text">📁 Chọn ảnh từ máy tính</span>
                            <input type="file" accept="image/*" style="display:none"
                                onchange="uploadImage(this,'inp-thumb','prev-thumb','lbl-thumb-text')">
                        </label>

                        <div style="margin-top:8px;border-radius:8px;overflow:hidden;background:#f8fafc;border:1px solid #e2e8f0">
                            <img id="prev-thumb"
                                src="<?= htmlspecialchars($post['thumb']) ?>"
                                style="width:100%;height:130px;object-fit:cover;display:<?= empty($post['thumb'])?'none':'block' ?>"
                                onerror="this.style.display='none'">
                            <div id="prev-thumb-empty" style="height:80px;display:<?= empty($post['thumb'])?'flex':'none' ?>;align-items:center;justify-content:center;color:#cbd5e1;font-size:12px">
                                Chưa có ảnh
                            </div>
                        </div>
                    </div>

                    <!-- Hero image -->
                    <div class="field-group" style="margin-bottom:0">
                        <label class="field-label">Ảnh hero
                            <span style="font-size:11px;color:#a0aec0;font-weight:400">(để trống = dùng thumbnail)</span>
                        </label>
                        <input type="text" name="img" id="inp-img"
                            value="<?= htmlspecialchars($post['img']) ?>"
                            placeholder="https://... hoặc upload bên dưới"
                            oninput="previewImg(this.value,'prev-img')"
                            style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:12.5px;box-sizing:border-box;font-family:inherit">

                        <label class="upload-btn" id="lbl-img">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <span id="lbl-img-text">📁 Chọn ảnh từ máy tính</span>
                            <input type="file" accept="image/*" style="display:none"
                                onchange="uploadImage(this,'inp-img','prev-img','lbl-img-text')">
                        </label>

                        <div style="margin-top:8px;border-radius:8px;overflow:hidden;background:#f8fafc;border:1px solid #e2e8f0">
                            <img id="prev-img"
                                src="<?= htmlspecialchars($post['img']) ?>"
                                style="width:100%;height:130px;object-fit:cover;display:<?= empty($post['img'])?'none':'block' ?>"
                                onerror="this.style.display='none'">
                            <div id="prev-img-empty" style="height:80px;display:<?= empty($post['img'])?'flex':'none' ?>;align-items:center;justify-content:center;color:#cbd5e1;font-size:12px">
                                Chưa có ảnh
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($mode === 'edit'): ?>
                <div class="section-card" style="padding:16px">
                    <div style="font-size:12px;color:#a0aec0;line-height:1.8">
                        <div>📅 Tạo: <strong style="color:#64748b"><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></strong></div>
                        <div>✏️ Sửa: <strong style="color:#64748b"><?= date('d/m/Y H:i', strtotime($post['updated_at'])) ?></strong></div>
                        <div>🔖 ID: <strong style="color:#64748b">#<?= $post['id'] ?></strong></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>