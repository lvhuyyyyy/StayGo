<?php
require_once __DIR__ . '/../includes/security.php';
include("../config/database.php");

// -- XỬ LÝ XÓA --
if (isset($_GET['delete']) && isset($_GET['del_id']) && is_numeric($_GET['del_id'])) {
    $del_id   = (int)$_GET['del_id'];
    $hotel_id = (int)($_GET['hotel_id'] ?? 0);
    $row = $conn->query("SELECT image FROM hotel_images WHERE id=$del_id")->fetch_assoc();
    if ($row) {
        $filepath = __DIR__ . '/../assets/images/' . $row['image'];
        if (file_exists($filepath)) @unlink($filepath);
        $conn->query("DELETE FROM hotel_images WHERE id=$del_id");
    }
    header("Location: hotel_images.php?hotel_id=$hotel_id&msg=deleted");
    exit;
}

// -- XỬ LÝ UPLOAD --
$msg = ''; $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $hotel_id = (int)$_POST['hotel_id'];
    $caption  = trim($_POST['caption'] ?? '');
    $sort     = (int)($_POST['sort_order'] ?? 0);

    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = __DIR__ . '/../assets/images/';
        $uploaded = 0; $errors = [];
        foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
            if ($_FILES['images']['error'][$k] !== UPLOAD_ERR_OK) continue;
            $orig = $_FILES['images']['name'][$k];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { $errors[] = "$orig: không hỗ trợ"; continue; }
            if ($_FILES['images']['size'][$k] > 5*1024*1024) { $errors[] = "$orig: quá 5MB"; continue; }
            $new_name = 'hotel_' . $hotel_id . '_' . time() . '_' . $k . '.' . $ext;
            if (move_uploaded_file($tmp, $upload_dir . $new_name)) {
                $st = $conn->prepare("INSERT INTO hotel_images (hotel_id,image,caption,sort_order) VALUES (?,?,?,?)");
                $st->bind_param("issi", $hotel_id, $new_name, $caption, $sort);
                $st->execute();
                $uploaded++;
            }
        }
        $msg = $uploaded > 0 ? "✅ Đã upload $uploaded ảnh!" : '❌ Không upload được ảnh nào.';
        $msg_type = $uploaded > 0 ? 'success' : 'error';
        if ($errors) $msg .= ' | Lỗi: ' . implode(', ', $errors);
    } else {
        $msg = '⚠️ Vui lòng chọn ít nhất 1 ảnh.'; $msg_type = 'warning';
    }
}

// -- XỬ LÝ CẬP NHẬT --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $img_id  = (int)$_POST['img_id'];
    $caption = trim($_POST['caption'] ?? '');
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $st = $conn->prepare("UPDATE hotel_images SET caption=?, sort_order=? WHERE id=?");
    $st->bind_param("sii", $caption, $sort, $img_id);
    $st->execute();
    $msg = '✅ Đã cập nhật!'; $msg_type = 'success';
}

// -- DATA --
$hotels = $conn->query("SELECT id, name, image FROM hotels WHERE is_active=1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$selected = isset($_GET['hotel_id']) ? (int)$_GET['hotel_id'] : ($hotels[0]['id'] ?? 0);
$hotel_info = $selected ? $conn->query("SELECT * FROM hotels WHERE id=$selected")->fetch_assoc() : null;
$images = $selected ? $conn->query("SELECT * FROM hotel_images WHERE hotel_id=$selected ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC) : [];

$img_counts = [];
$cr = $conn->query("SELECT hotel_id, COUNT(*) c FROM hotel_images GROUP BY hotel_id");
while ($r = $cr->fetch_assoc()) $img_counts[$r['hotel_id']] = $r['c'];

$page_title = 'Quản lý ảnh KS';
$page_subtitle = 'Upload & quản lý gallery ảnh khách sạn';
include("../includes/admin_header.php");
?>

<?php if ($msg): ?>
<div class="hi-alert <?= htmlspecialchars($msg_type) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="hi-alert success">✅ Đã xóa ảnh thành công!</div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:18px;font-weight:700;color:#1a202c;margin:0">🖼️ Quản lý ảnh gallery</h2>
    <a href="hotels.php" style="font-size:13px;color:#1e73be;text-decoration:none;font-weight:600">← Về trang Khách sạn</a>
</div>

<div class="hi-wrap">

    <!-- SIDEBAR KS -->
    <div class="hi-ks-list">
        <div class="hi-ks-head">🏨 Chọn khách sạn</div>
        <?php foreach ($hotels as $h):
            $act = ($h['id'] == $selected) ? 'active' : '';
            $cnt = $img_counts[$h['id']] ?? 0;
        ?>
        <a class="hi-ks-item <?= $act ?>" href="hotel_images.php?hotel_id=<?= $h['id'] ?>">
            <img class="hi-ks-thumb" src="../assets/images/<?= htmlspecialchars($h['image'] ?? '') ?>" onerror="this.style.visibility='hidden'" alt="">
            <span class="hi-ks-name"><?= htmlspecialchars(mb_substr($h['name'], 0, 30)) ?></span>
            <span class="hi-ks-cnt"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- MAIN -->
    <div class="hi-main">
        <?php if ($hotel_info): ?>

        <!-- Upload card -->
        <div class="hi-card">
            <div class="hi-card-title">
                ⬆️ Upload ảnh — <span style="color:#1e73be;font-size:14px"><?= htmlspecialchars($hotel_info['name']) ?></span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="hotel_id" value="<?= $selected ?>">
                <div class="hi-dropzone" id="dropzone">
                    <input type="file" name="images[]" id="fileInput" multiple accept="image/*" onchange="previewFiles(this)">
                    <div class="hi-dz-icon">🖼️</div>
                    <div class="hi-dz-text" id="dzText">Kéo thả ảnh vào đây hoặc click để chọn</div>
                    <div class="hi-dz-sub">JPG, PNG, WEBP · Tối đa 5MB/ảnh · Chọn nhiều ảnh cùng lúc</div>
                    <div class="hi-preview" id="previewArea"></div>
                </div>
                <div class="hi-fields">
                    <div class="hi-field">
                        <label>Chú thích (tuỳ chọn)</label>
                        <input type="text" name="caption" placeholder="VD: Hồ bơi, Sảnh chờ, Phòng Deluxe...">
                    </div>
                    <div class="hi-field">
                        <label>Thứ tự</label>
                        <input type="number" name="sort_order" value="0" min="0">
                    </div>
                </div>
                <button type="submit" class="hi-upload-btn">⬆️ Upload ảnh</button>
            </form>
        </div>

        <!-- Gallery card -->
        <div class="hi-card">
            <div class="hi-gallery-head">
                <div class="hi-gallery-title">
                    🖼️ Gallery hiện tại
                    <span class="hi-cnt-badge"><?= count($images) ?> ảnh</span>
                </div>
                <?php if (!empty($images)): ?>
                <a href="../pages/hotel_detail.php?id=<?= $selected ?>" target="_blank"
                style="font-size:12.5px;color:#1e73be;text-decoration:none;font-weight:600">
                    👁️ Xem trang KS →
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($images)): ?>
            <div class="hi-empty">
                <div class="hi-empty-icon">📭</div>
                <p style="font-weight:700;color:#4a5568;margin:0 0 6px">Chưa có ảnh nào</p>
                <p style="font-size:13px;margin:0">Upload ảnh ở trên để hiển thị trên trang chi tiết khách sạn</p>
            </div>
            <?php else: ?>
            <div class="hi-img-grid">
                <?php foreach ($images as $img): ?>
                <div class="hi-img-item">
                    <img src="../assets/images/<?= htmlspecialchars($img['image']) ?>"
                        alt=""
                        onerror="this.parentElement.style.background='#f0f4f8'">
                    <div class="hi-img-ov">
                        <button class="hi-ov" onclick="openLb('../assets/images/<?= htmlspecialchars($img['image']) ?>','<?= htmlspecialchars(addslashes($img['caption'] ?? '')) ?>')" title="Xem">🔍</button>
                        <button class="hi-ov" onclick="openEdit(<?= $img['id'] ?>,'<?= htmlspecialchars(addslashes($img['caption'] ?? '')) ?>',<?= $img['sort_order'] ?>)" title="Sửa">✏️</button>
                        <button class="hi-ov del" onclick="doDelete(<?= $img['id'] ?>,<?= $selected ?>)" title="Xóa">🗑️</button>
                    </div>
                    <div class="hi-img-meta">
                        <div class="hi-img-cap"><?= $img['caption'] ? htmlspecialchars($img['caption']) : '<em style="color:#a0aec0">Chưa có chú thích</em>' ?></div>
                        <div class="hi-img-sort">Thứ tự: <?= $img['sort_order'] ?> &nbsp;·&nbsp; #<?= $img['id'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div style="background:#fff;border-radius:14px;padding:60px;text-align:center;color:#a0aec0;box-shadow:0 2px 10px rgba(0,0,0,.06)">
            <div style="font-size:48px;margin-bottom:12px">🏨</div>
            <p style="font-weight:600;color:#4a5568;margin:0">Chọn khách sạn bên trái để quản lý ảnh gallery</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="hi-modal-bg" id="editModal">
    <div class="hi-modal">
        <h3>✏️ Sửa thông tin ảnh</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="hotel_id" value="<?= $selected ?>">
            <input type="hidden" name="img_id" id="edit_id">
            <div class="hi-field" style="margin-bottom:13px">
                <label>Chú thích ảnh</label>
                <input type="text" name="caption" id="edit_caption" placeholder="VD: Hồ bơi, Phòng Deluxe...">
            </div>
            <div class="hi-field">
                <label>Thứ tự (số nhỏ = hiển thị trước)</label>
                <input type="number" name="sort_order" id="edit_sort" value="0" min="0">
            </div>
            <div class="hi-modal-btns">
                <button type="button" class="hi-btn-cancel" onclick="closeEdit()">Huỷ</button>
                <button type="submit" class="hi-btn-save">💾 Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="hi-lb" id="lightbox">
    <button class="hi-lb-close" onclick="closeLb()">✕</button>
    <img id="lbImg" src="" alt="">
    <div class="hi-lb-cap" id="lbCap"></div>
</div>

<?php include("../includes/admin_footer.php"); ?>