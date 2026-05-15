<?php
$page_title    = 'Ảnh khách sạn';
$page_subtitle = 'Quản lý hình ảnh hiển thị trên trang khách sạn';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

$msg   = null;
$error = null;

// ── POST: Upload ảnh mới ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'upload') {
    csrf_check();
    if (empty($_FILES['images']['name'][0])) {
        $error = 'Vui lòng chọn ít nhất một ảnh.';
    } else {
        $upload_dir = __DIR__ . '/../assets/images/hotels/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $caption   = $conn->real_escape_string(trim($_POST['caption'] ?? ''));
        $uploaded  = 0;
        $is_primary_set = (bool)$conn->query("SELECT id FROM hotel_images WHERE hotel_id=$hotel_id AND is_primary=1 LIMIT 1")->fetch_assoc();

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            $file_entry = [
                'name'     => $_FILES['images']['name'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];
            $check = validate_image_upload($file_entry);
            if (!$check['success']) continue;

            $ext      = $check['ext'];
            $filename = safe_filename('hotel_' . $hotel_id, $ext);
            if (!move_uploaded_file($tmp, $upload_dir . $filename)) continue;

            // Ảnh đầu tiên upload nếu chưa có primary → đặt làm primary
            $make_primary = (!$is_primary_set && $uploaded === 0) ? 1 : 0;
            $img_esc = $conn->real_escape_string('hotels/' . $filename);
            $sort = (int)($conn->query("SELECT COALESCE(MAX(sort_order),0) as m FROM hotel_images WHERE hotel_id=$hotel_id")->fetch_assoc()['m'] ?? 0) + 1;
            $conn->query("INSERT INTO hotel_images (hotel_id, image, caption, sort_order, is_primary) VALUES ($hotel_id, '$img_esc', '$caption', $sort, $make_primary)");
            $uploaded++;
        }

        if ($uploaded > 0) {
            $msg = "Đã upload $uploaded ảnh thành công.";
        } else {
            $error = 'Không có ảnh nào hợp lệ. Chỉ chấp nhận JPG/PNG/WEBP, tối đa 5MB mỗi ảnh.';
        }
    }
}

// ── POST: Xóa ảnh ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    csrf_check();
    $img_id = (int)($_POST['img_id'] ?? 0);
    $row    = $conn->query("SELECT * FROM hotel_images WHERE id=$img_id AND hotel_id=$hotel_id")->fetch_assoc();
    if ($row) {
        $filepath = __DIR__ . '/../assets/images/' . $row['image'];
        if (file_exists($filepath)) @unlink($filepath);
        $conn->query("DELETE FROM hotel_images WHERE id=$img_id");
        // Nếu xóa primary → đặt ảnh đầu tiên còn lại làm primary
        if ($row['is_primary']) {
            $conn->query("UPDATE hotel_images SET is_primary=1 WHERE hotel_id=$hotel_id ORDER BY sort_order ASC LIMIT 1");
        }
        $msg = 'Đã xóa ảnh.';
    } else {
        $error = 'Không tìm thấy ảnh.';
    }
}

// ── POST: Đặt làm ảnh đại diện ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'set_primary') {
    csrf_check();
    $img_id = (int)($_POST['img_id'] ?? 0);
    if ($conn->query("SELECT id FROM hotel_images WHERE id=$img_id AND hotel_id=$hotel_id")->fetch_assoc()) {
        $conn->query("UPDATE hotel_images SET is_primary=0 WHERE hotel_id=$hotel_id");
        $conn->query("UPDATE hotel_images SET is_primary=1 WHERE id=$img_id");
        // Đồng bộ sang cột image của bảng hotels (ảnh đại diện hiển thị trên listing)
        $primary_path = $conn->query("SELECT image FROM hotel_images WHERE id=$img_id")->fetch_assoc()['image'] ?? '';
        if ($primary_path) {
            $p = $conn->real_escape_string($primary_path);
            $conn->query("UPDATE hotels SET image='$p' WHERE id=$hotel_id");
        }
        $msg = 'Đã cập nhật ảnh đại diện.';
    }
}

// Lấy danh sách ảnh hiện có
$images = $conn->query("
    SELECT * FROM hotel_images WHERE hotel_id=$hotel_id ORDER BY sort_order ASC, id ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Upload form -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">📤 Upload ảnh mới</span>
        <span style="font-size:12px;color:#a0aec0">JPG/PNG/WEBP · Tối đa 5MB/ảnh · Có thể chọn nhiều ảnh</span>
    </div>
    <div class="card-body form-row">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="upload">
            <div class="form-grid" style="margin-bottom:16px">
                <div class="form-group" style="margin-bottom:0">
                    <label>Chọn ảnh <span style="color:#c53030">*</span></label>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required
                        style="padding:8px;background:#f7fafc">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label>Chú thích (áp dụng cho tất cả ảnh upload lần này)</label>
                    <input type="text" name="caption" placeholder="Ví dụ: Sảnh khách sạn, Phòng Deluxe..." maxlength="100">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">📤 Upload ảnh</button>
        </form>
    </div>
</div>

<!-- Gallery hiện tại -->
<div class="card">
    <div class="card-header">
        <span class="card-title">🖼️ Ảnh hiện tại (<?= count($images) ?>)</span>
    </div>
    <div class="card-body" style="padding:20px">
        <?php if (empty($images)): ?>
        <div style="text-align:center;padding:60px;color:#a0aec0">
            <div style="font-size:40px;margin-bottom:12px">📷</div>
            <p>Chưa có ảnh nào. Upload ảnh để hiển thị trên trang khách sạn.</p>
        </div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
            <?php foreach ($images as $img): ?>
            <div style="border-radius:12px;overflow:hidden;border:2px solid <?= $img['is_primary'] ? '#3182ce' : '#e2e8f0' ?>;position:relative;background:#f7fafc">
                <?php if ($img['is_primary']): ?>
                <div style="position:absolute;top:8px;left:8px;background:#3182ce;color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;z-index:2">
                    ★ Ảnh đại diện
                </div>
                <?php endif; ?>
                <img src="/assets/images/<?= htmlspecialchars($img['image']) ?>"
                     alt="<?= htmlspecialchars($img['caption'] ?? '') ?>"
                     style="width:100%;height:145px;object-fit:cover;display:block"
                     onerror="this.src='/assets/images/default-hotel.jpg'">
                <?php if ($img['caption']): ?>
                <div style="padding:8px 10px;font-size:12px;color:#4a5568;font-weight:500">
                    <?= htmlspecialchars($img['caption']) ?>
                </div>
                <?php endif; ?>
                <div style="padding:8px 10px;display:flex;gap:8px;border-top:1px solid #f0f4f8">
                    <?php if (!$img['is_primary']): ?>
                    <form method="POST" style="flex:1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="set_primary">
                        <input type="hidden" name="img_id" value="<?= $img['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="width:100%;font-size:11px">
                            ★ Đặt đại diện
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Xóa ảnh này?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="delete">
                        <input type="hidden" name="img_id" value="<?= $img['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="font-size:11px">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
