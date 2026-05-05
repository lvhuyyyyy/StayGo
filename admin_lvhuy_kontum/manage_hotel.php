<?php
require_once __DIR__ . '/../includes/security.php';
include("../config/database.php");

$id     = isset($_GET['id'])     ? (int)$_GET['id']     : 0;
$action = isset($_GET['action']) ? $_GET['action']       : 'edit';

if (!$id) { header("Location: hotels.php?error=invalid"); exit; }

// -- XÓA KHÁCH SẠN ----------------------------------------------
if ($action === 'delete') {
    $check = $conn->query("SELECT id FROM hotels WHERE id = $id");
    if ($check->num_rows === 0) { header("Location: hotels.php?error=notfound"); exit; }
    $conn->query("DELETE FROM hotels WHERE id = $id");
    log_activity($conn, 'delete_hotel', 'hotel', $id, "Xóa khách sạn #$id");
    header("Location: hotels.php?success=deleted"); exit;
}

// -- XÓA ẢNH GALLERY --------------------------------------------
if ($action === 'delete_image' && isset($_GET['img_id'])) {
    $img_id = (int)$_GET['img_id'];
    $row = $conn->query("SELECT image FROM hotel_images WHERE id=$img_id AND hotel_id=$id")->fetch_assoc();
    if ($row) {
        $conn->query("DELETE FROM hotel_images WHERE id=$img_id AND hotel_id=$id");
        $file = __DIR__ . '/../assets/images/' . $row['image'];
        if (file_exists($file)) @unlink($file);
    }
    header("Location: manage_hotel.php?id=$id&success=img_deleted"); exit;
}

// -- UPLOAD ẢNH MỚI ---------------------------------------------
if ($action === 'upload_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $caption    = $conn->real_escape_string(trim($_POST['caption'] ?? ''));
    $sort_order = (int)($_POST['sort_order'] ?? 99);

    // Kiểm tra có file không
    if (empty($_FILES['img_file']['name']) || $_FILES['img_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_err_code = $_FILES['img_file']['error'] ?? -1;
        header("Location: manage_hotel.php?id=$id&error=no_file&code=$upload_err_code"); exit;
    }

    // -- THÊM MỚI: Validate ảnh đầy đủ 6 lớp --
    // Kiểm tra: lỗi upload, kích thước, extension, MIME type thật, getimagesize, kích thước ảnh
    $validation = validate_image_upload($_FILES['img_file'], 8 * 1024 * 1024);
    if (!$validation['success']) {
        header("Location: manage_hotel.php?id=$id&error=invalid_img&msg=" . urlencode($validation['message']));
        exit;
    }
    // Lấy ext đã được validate an toàn (không tin vào tên file từ client)
    $ext = $validation['ext'];

    // Xác định subfolder
    $existing = $conn->query("SELECT image FROM hotel_images WHERE hotel_id=$id LIMIT 1")->fetch_assoc();
    if ($existing) {
        $folder_name = dirname($existing['image']);
    } else {
        $hotel_row   = $conn->query("SELECT name FROM hotels WHERE id=$id")->fetch_assoc();
        $folder_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($hotel_row['name'] ?? 'hotel_'.$id));
        $folder_name = preg_replace('/_+/', '_', $folder_name);
        $folder_name = trim($folder_name, '_');
    }

    $base_dir = realpath(__DIR__ . '/../assets/images');
    if (!$base_dir) {
        header("Location: manage_hotel.php?id=$id&error=basedir"); exit;
    }
    $upload_dir = $base_dir . DIRECTORY_SEPARATOR . $folder_name . DIRECTORY_SEPARATOR;

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            header("Location: manage_hotel.php?id=$id&error=mkdir"); exit;
        }
    }

    $count    = (int)$conn->query("SELECT COUNT(*) as c FROM hotel_images WHERE hotel_id=$id")->fetch_assoc()['c'];
    $filename = $folder_name . '_gallery_' . ($count + 1) . '_' . time() . '.' . $ext;
    $dest     = $upload_dir . $filename;
    $rel_path = $folder_name . '/' . $filename;

    if (!move_uploaded_file($_FILES['img_file']['tmp_name'], $dest)) {
        error_log("move_uploaded_file FAILED: tmp={$_FILES['img_file']['tmp_name']} dest=$dest");
        header("Location: manage_hotel.php?id=$id&error=move&dest=" . urlencode($dest)); exit;
    }

    $conn->query("
        INSERT INTO hotel_images (hotel_id, image, caption, sort_order)
        VALUES ($id, '$rel_path', '$caption', $sort_order)
    ");
    header("Location: manage_hotel.php?id=$id&success=img_added"); exit;
}

// -- CẬP NHẬT CAPTION / SORT ------------------------------------
if ($action === 'update_image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $img_id     = (int)($_POST['img_id'] ?? 0);
    $caption    = $conn->real_escape_string(trim($_POST['caption'] ?? ''));
    $sort_order = (int)($_POST['sort_order'] ?? 99);
    $conn->query("UPDATE hotel_images SET caption='$caption', sort_order=$sort_order WHERE id=$img_id AND hotel_id=$id");
    header("Location: manage_hotel.php?id=$id&success=img_updated"); exit;
}

// -- LƯU SỬA THÔNG TIN (POST) -----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'edit' || $action === '')) {
    csrf_check();
    $name            = $conn->real_escape_string(trim($_POST['name']        ?? ''));
    $address         = $conn->real_escape_string(trim($_POST['address']     ?? ''));
    $description     = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $price           = (int)($_POST['price'] ?? 0);
    $old_price       = (int)($_POST['old_price'] ?? 0);
    $is_active       = isset($_POST['is_active'])       ? 1 : 0;
    $is_weekend_deal = isset($_POST['is_weekend_deal']) ? 1 : 0;
    $conn->query("UPDATE hotels SET name='$name',address='$address',description='$description',price=$price,old_price=$old_price,is_active=$is_active,is_weekend_deal=$is_weekend_deal WHERE id=$id");
    log_activity($conn, 'edit_hotel', 'hotel', $id, "Sửa khách sạn: $name");
    header("Location: hotels.php?success=updated"); exit;
}

// -- HIỆN FORM (GET) --------------------------------------------
$hotel = $conn->query("SELECT * FROM hotels WHERE id=$id")->fetch_assoc();
if (!$hotel) { header("Location: hotels.php?error=notfound"); exit; }

$gallery = $conn->query("SELECT * FROM hotel_images WHERE hotel_id=$id ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);

$page_title    = 'Sửa khách sạn';
$page_subtitle = 'Chỉnh sửa: ' . htmlspecialchars($hotel['name']);
include("../includes/admin_header.php");

$err_code = $_GET['code'] ?? '';
$dest_dbg = urldecode($_GET['dest'] ?? '');
?>

<div class="page-header">
    <h2>🏨 Sửa khách sạn</h2>
    <a href="hotels.php" class="btn-back" style="margin-bottom:0">← Quay lại</a>
</div>

<?php
$msg     = $_GET['success'] ?? '';
$err     = $_GET['error']   ?? '';
$err_msg = htmlspecialchars(urldecode($_GET['msg'] ?? ''));
$upload_err_map = [
    '0'=>'Không có lỗi','1'=>'File vượt upload_max_filesize trong php.ini',
    '2'=>'File vượt MAX_FILE_SIZE trong form','3'=>'File chỉ upload được một phần',
    '4'=>'Không có file nào được upload','6'=>'Thiếu thư mục tạm',
    '7'=>'Không ghi được file vào đĩa','8'=>'Extension PHP chặn upload',
];
if ($msg==='img_added')   echo '<div class="alert alert-success">✅ Đã thêm ảnh thành công!</div>';
if ($msg==='img_deleted') echo '<div class="alert alert-success">✅ Đã xóa ảnh.</div>';
if ($msg==='img_updated') echo '<div class="alert alert-success">✅ Đã cập nhật ảnh.</div>';
if ($err==='ext')         echo '<div class="alert alert-error">❌ Sai định dạng file. Chỉ chấp nhận JPG, PNG, WEBP.</div>';
if ($err==='size')        echo '<div class="alert alert-error">❌ File quá lớn (tối đa 8MB).</div>';
if ($err==='basedir')     echo '<div class="alert alert-error">❌ Không tìm thấy thư mục <code>assets/images/</code>.</div>';
if ($err==='mkdir')       echo '<div class="alert alert-error">❌ Không tạo được thư mục ảnh.</div>';
if ($err==='move')        echo '<div class="alert alert-error">❌ Lưu file thất bại. Đường dẫn đích: <code>' . htmlspecialchars($dest_dbg) . '</code></div>';
if ($err==='invalid_img') echo '<div class="alert alert-error">❌ File không hợp lệ: <strong>' . $err_msg . '</strong></div>';
if ($err==='no_file') {
    $desc = $upload_err_map[$err_code] ?? "Code: $err_code";
    echo '<div class="alert alert-error">❌ Lỗi upload file: <code>' . htmlspecialchars($desc) . '</code></div>';
}
?>

<!-- -- THÔNG TIN KHÁCH SẠN -- -->
<div class="form-card">
    <form method="POST" action="manage_hotel.php?id=<?= $id ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Tên khách sạn *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($hotel['name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Địa chỉ</label>
            <input type="text" name="address" value="<?= htmlspecialchars($hotel['address'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Mô tả</label>
            <textarea name="description"><?= htmlspecialchars($hotel['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>Giá hiện tại (đ) *
                <span style="font-size:11px;font-weight:400;color:#a0aec0;margin-left:6px">
                    → Giá đang bán, hiển thị màu xanh trên trang
                </span>
            </label>
            <input type="number" name="price" value="<?= $hotel['price'] ?? 0 ?>" min="0" required>
        </div>
        <div class="form-group">
            <label>Giá gốc (đ)
                <span style="font-size:11px;font-weight:400;color:#a0aec0;margin-left:6px">
                    → Giá trước khi giảm, hiển thị gạch ngang <s>850.000đ</s> (để trống nếu không có)
                </span>
            </label>
            <input type="number" name="old_price" value="<?= $hotel['old_price'] ?? '' ?>" min="0" placeholder="VD: 850000">
        </div>
        <div class="toggle-row">
            <input type="checkbox" name="is_active" id="is_active" <?= ($hotel['is_active']??0)==1?'checked':'' ?>>
            <label for="is_active">✅ <strong>Hoạt động</strong> → Hiển thị trên trang người dùng</label>
        </div>
        <div class="toggle-row">
            <input type="checkbox" name="is_weekend_deal" id="is_weekend_deal" <?= ($hotel['is_weekend_deal']??0)==1?'checked':'' ?>>
            <label for="is_weekend_deal">🏷️ <strong>Deal cuối tuần</strong> → Hiện badge ưu đãi cuối tuần</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-save">💾 Lưu thay đổi</button>
            <a href="hotels.php" class="btn-cancel">Huỷ</a>
            <a href="manage_hotel.php?id=<?= $id ?>&action=delete"
            class="btn-del-inline"
            onclick="return confirm('Xóa khách sạn «<?= addslashes($hotel['name']) ?>»?')">
                🗑️ Xóa khách sạn này
            </a>
        </div>
    </form>
</div>

<!-- -- QUẢN LÝ ẢNH GALLERY -- -->
<div class="gallery-card">
    <h3>🖼️ Ảnh gallery
        <span style="font-size:13px;font-weight:500;color:#718096;margin-left:8px">
            (<?= count($gallery) ?> ảnh)
        </span>
    </h3>
    <p style="font-size:12.5px;color:#718096;margin:-10px 0 18px">
        📌 Ảnh dưới đây là ảnh <strong>gallery phụ</strong> hiện trong lưới thumbnail trên trang chi tiết khách sạn.
    </p>

    <?php if(empty($gallery)): ?>
        <div style="text-align:center;padding:30px;color:#a0aec0;border:1.5px dashed #e2e8f0;border-radius:12px;margin-bottom:24px">
            📭 Chưa có ảnh gallery. Upload ảnh đầu tiên bên dưới ↓
        </div>
    <?php else: ?>
    <div class="img-grid">
        <?php foreach($gallery as $gi): ?>
        <div class="img-item">
            <div class="img-item-wrap">
                <img src="/assets/images/<?= htmlspecialchars($gi['image']) ?>"
                    alt=""
                    onerror="this.src='https://placehold.co/190x130?text=No+Image'">
                <span class="img-order-badge">#<?= $gi['sort_order'] ?></span>
            </div>
            <div class="img-item-body">
                <form method="POST" action="manage_hotel.php?id=<?= $id ?>&action=update_image">
                    <?= csrf_field() ?>
                    <input type="hidden" name="img_id" value="<?= $gi['id'] ?>">
                    <input type="text" class="img-caption-input" name="caption"
                        value="<?= htmlspecialchars($gi['caption'] ?? '') ?>"
                        placeholder="Chú thích (tuỳ chọn)">
                    <div class="img-sort-row">
                        <label>Thứ tự:</label>
                        <input type="number" class="img-sort-input" name="sort_order"
                            value="<?= $gi['sort_order'] ?>" min="1" max="99">
                    </div>
                    <div class="img-actions">
                        <button type="submit" class="btn-img-save">💾 Lưu</button>
                        <a href="manage_hotel.php?id=<?= $id ?>&action=delete_image&img_id=<?= $gi['id'] ?>"
                        class="btn-img-del"
                        onclick="return confirm('Xóa ảnh này?')">🗑️</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- -- UPLOAD ẢNH MỚI -- -->
    <div style="border-top:2px solid #f0f4f8;padding-top:22px">
        <p style="font-size:14px;font-weight:700;color:#1a202c;margin:0 0 14px">➕ Thêm ảnh mới</p>

        <?php
        $existing_img = $conn->query("SELECT image FROM hotel_images WHERE hotel_id=$id LIMIT 1")->fetch_assoc();
        if ($existing_img) {
            $debug_folder = dirname($existing_img['image']);
        } else {
            $hotel_name_raw = $hotel['name'] ?? 'hotel_'.$id;
            $debug_folder   = preg_replace('/_+/','_',preg_replace('/[^a-zA-Z0-9_\-]/','_',trim($hotel_name_raw)));
        }
        $debug_path = realpath(__DIR__ . '/../assets/images') . DIRECTORY_SEPARATOR . $debug_folder . DIRECTORY_SEPARATOR;
        ?>
        <p style="font-size:11.5px;color:#a0aec0;margin:0 0 14px">
            📁 Ảnh sẽ lưu vào: <code style="background:#f0f4f8;padding:2px 7px;border-radius:4px"><?= htmlspecialchars($debug_path) ?></code>
        </p>

        <form method="POST"
            action="manage_hotel.php?id=<?= $id ?>&action=upload_image"
            enctype="multipart/form-data"
            id="uploadForm">
            <?= csrf_field() ?>

            <input type="file" name="img_file" id="imgFile"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                style="position:absolute;opacity:0;width:1px;height:1px;pointer-events:none"
                onchange="previewImg(this)">

            <label for="imgFile" class="upload-zone" id="uploadZone" style="display:block;cursor:pointer">
                <div class="uz-icon">🖼️</div>
                <div class="uz-text">Click hoặc kéo thả ảnh vào đây</div>
                <div class="uz-sub">JPG · PNG · WEBP · Tối đa 8MB</div>
            </label>

            <div class="upload-preview" id="uploadPreview">
                <img id="previewImg" src="" alt="preview">
                <p id="previewName" style="font-size:12px;color:#718096;margin-top:6px"></p>
            </div>

            <div class="upload-meta">
                <div class="um-field">
                    <label>Chú thích ảnh</label>
                    <input type="text" name="caption" placeholder="VD: Phòng Deluxe, Hồ bơi...">
                </div>
                <div class="um-field">
                    <label>Thứ tự hiển thị</label>
                    <input type="number" name="sort_order" value="<?= count($gallery)+1 ?>" min="1" max="99">
                </div>
                <button type="submit" class="btn-upload" id="btnUpload" disabled>
                    ⬆️ Upload ảnh
                </button>
            </div>
        </form>
    </div>
</div>

<?php include("../includes/admin_footer.php"); ?>