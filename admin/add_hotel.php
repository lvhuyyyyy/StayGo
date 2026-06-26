=<?php
require_once __DIR__ . '/../includes/security.php';
include "../config/database.php";

// -- XỬ LÝ THÊM KHÁCH SẠN (POST) -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name            = $conn->real_escape_string(trim($_POST['name']         ?? ''));
    $address         = $conn->real_escape_string(trim($_POST['address']      ?? ''));
    $description     = $conn->real_escape_string(trim($_POST['description']  ?? ''));
    $price           = (int)($_POST['price']        ?? 0);
    $old_price       = (int)($_POST['old_price']    ?? 0);
    $rating          = (float)($_POST['rating']     ?? 0);
    $review_count    = (int)($_POST['review_count'] ?? 0);
    $location_id     = (int)($_POST['location_id']  ?? 0);
    $is_active       = isset($_POST['is_active'])       ? 1 : 0;
    $is_weekend_deal = isset($_POST['is_weekend_deal']) ? 1 : 0;

    // Upload ảnh đại diện
    $main_image = '';
    if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $ext_ok  = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $ext_ok) && $_FILES['main_image']['size'] <= 8*1024*1024) {
            $safe_name   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($name));
            $safe_name   = preg_replace('/_+/', '_', $safe_name);
            $safe_name   = trim($safe_name, '_');
            $base_dir    = realpath(__DIR__ . '/../assets/images');
            $folder_path = $base_dir . DIRECTORY_SEPARATOR . $safe_name . DIRECTORY_SEPARATOR;
            if (!is_dir($folder_path)) mkdir($folder_path, 0755, true);
            $filename    = $safe_name . '.' . $ext;
            $dest        = $folder_path . $filename;
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $dest)) {
                $main_image = $safe_name . '/' . $filename;
            }
        }
    }

    // INSERT khách sạn
    $main_image_esc = $conn->real_escape_string($main_image);
    $conn->query("
        INSERT INTO hotels (name, address, description, price, old_price, rating, review_count,
                            location_id, image, is_active, is_weekend_deal, created_at)
        VALUES ('$name','$address','$description',$price,$old_price,$rating,$review_count,
                " . ($location_id ?: 'NULL') . ",'$main_image_esc',$is_active,$is_weekend_deal,NOW())
    ");
    $new_id = $conn->insert_id;

    // Upload ảnh gallery
    if ($new_id && !empty($_FILES['gallery_imgs']['name'][0])) {
        $safe_name = preg_replace('/_+/','_',preg_replace('/[^a-zA-Z0-9_\-]/','_',trim($name)));
        $safe_name = trim($safe_name, '_');
        $base_dir  = realpath(__DIR__ . '/../assets/images');

        $folder_path = $base_dir . DIRECTORY_SEPARATOR . $safe_name . DIRECTORY_SEPARATOR;
        if (!is_dir($folder_path)) mkdir($folder_path, 0755, true);

        $ext_ok = ['jpg','jpeg','png','webp'];
        $order  = 1;
        foreach ($_FILES['gallery_imgs']['tmp_name'] as $i => $tmp) {
            if ($_FILES['gallery_imgs']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['gallery_imgs']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_ok)) continue;
            if ($_FILES['gallery_imgs']['size'][$i] > 8*1024*1024) continue;

            $filename = $safe_name . '_gallery_' . $order . '_' . time() . '.' . $ext;
            $dest     = $folder_path . $filename;
            if (move_uploaded_file($tmp, $dest)) {
                $rel   = $conn->real_escape_string($safe_name . '/' . $filename);
                $cap   = $conn->real_escape_string(trim($_POST['gallery_captions'][$i] ?? ''));
                $conn->query("INSERT INTO hotel_images (hotel_id, image, caption, sort_order) VALUES ($new_id,'$rel','$cap',$order)");
                $order++;
            }
        }
    }

    log_activity($conn, 'add_hotel', 'hotel', $new_id, "Thêm khách sạn: $name");
    header("Location: hotels.php?success=added"); exit;
}

// -- LẤY DANH SÁCH LOCATIONS ------------------------------------
$locations = $conn->query("SELECT id, name FROM locations ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$page_title    = 'Thêm khách sạn';
$page_subtitle = 'Tạo khách sạn mới';
include "../includes/admin_header.php";
?>

<div class="page-header">
    <h2>➕ Thêm khách sạn mới</h2>
    <a href="hotels.php" class="btn-cancel" style="margin-bottom:0">← Quay lại</a>
</div>

<form method="POST" action="add_hotel.php" enctype="multipart/form-data" id="addForm">
    <?= csrf_field() ?>

    <!-- Input file ẩn -->
    <input type="file" id="mainImgFile" name="main_image"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        style="position:absolute;opacity:0;width:1px;height:1px;pointer-events:none"
        onchange="previewMainImg(this)">
    <input type="file" id="galleryFiles" name="gallery_imgs[]" multiple
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        style="position:absolute;opacity:0;width:1px;height:1px;pointer-events:none"
        onchange="previewGallery(this)">

    <div class="add-wrap">

        <!-- -- CỘT TRÁI: Thông tin -- -->
        <div>
            <!-- Thông tin cơ bản -->
            <div class="form-card" style="margin-bottom:20px">
                <div class="card-title">🏨 Thông tin cơ bản</div>

                <div class="form-group">
                    <label>Tên khách sạn <span class="req">*</span></label>
                    <input type="text" name="name" id="hotelName" placeholder="VD: Golden Boutique Hotel Mang Đen" required>
                </div>
                <div class="form-group">
                    <label>Địa chỉ</label>
                    <input type="text" name="address" placeholder="VD: 38 Nguyễn Du, Thị trấn Mang Đen, Kon Plông, Kon Tum">
                </div>
                <div class="form-group">
                    <label>Khu vực / Địa điểm</label>
                    <select name="location_id">
                        <option value="">— Chọn khu vực —</option>
                        <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mô tả</label>
                    <textarea name="description" placeholder="Mô tả ngắn về khách sạn, điểm nổi bật, cảnh quan..."></textarea>
                </div>
            </div>

            <!-- Giá & Đánh giá -->
            <div class="form-card" style="margin-bottom:20px">
                <div class="card-title">💰 Giá & Đánh giá</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Giá phòng từ (đ) <span class="req">*</span></label>
                        <input type="number" name="price" placeholder="VD: 800000" min="0" required>
                        <div class="hint">Giá hiển thị trên danh sách</div>
                    </div>
                    <div class="form-group">
                        <label>Giá gốc (đ) <i style="font-weight:400">(để tính % giảm)</i></label>
                        <input type="number" name="old_price" placeholder="VD: 950000" min="0">
                        <div class="hint">Để trống nếu không có giảm giá</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Điểm đánh giá (0–10)</label>
                    <div class="star-row">
                        <input type="range" name="rating" id="ratingRange" min="0" max="10" step="0.1" value="8.5"
                            oninput="updateStars(this.value)">
                        <span class="rating-val" id="ratingVal">8.5</span>
                        <span class="star-display" id="starDisplay">⭐⭐⭐⭐⭐</span>
                    </div>
                    <div class="hint">Kéo thanh để chọn điểm</div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0">
                        <label>Số lượt đánh giá</label>
                        <input type="number" name="review_count" value="0" min="0" placeholder="VD: 120">
                    </div>
                </div>
            </div>

            <!-- Ảnh gallery -->
            <div class="form-card">
                <div class="card-title">🖼️ Ảnh gallery phụ
                    <span style="font-size:12px;font-weight:400;color:#a0aec0;margin-left:6px">(hiện trong lưới thumbnail trang chi tiết)</span>
                </div>

                <label for="galleryFiles" class="gallery-drop" id="galleryDrop" style="display:block">
                    <div class="uz-icon">🖼️</div>
                    <div class="uz-text">Click hoặc kéo thả nhiều ảnh</div>
                    <div class="uz-sub">Chọn được nhiều ảnh cùng lúc · JPG PNG WEBP · Max 8MB/ảnh</div>
                </label>

                <div class="gallery-preview-grid" id="galleryPreviewGrid"></div>
            </div>
        </div>

        <!-- -- CỘT PHẢI: Sidebar -- -->
        <div>
            <!-- Ảnh đại diện -->
            <div class="form-card" style="margin-bottom:20px">
                <div class="card-title">🖼️ Ảnh đại diện <span class="req">*</span></div>
                <p style="font-size:12.5px;color:#718096;margin:-8px 0 12px">Ảnh hiển thị ở lớn bên trái trên trang chi tiết</p>

                <label for="mainImgFile" class="main-img-zone" id="mainImgZone" style="display:block">
                    <div class="uz-icon">🖼️</div>
                    <div class="uz-text">Click chọn ảnh đại diện</div>
                    <div class="uz-sub">JPG · PNG · WEBP · Max 8MB</div>
                </label>
                <div class="main-img-preview" id="mainImgPreview">
                    <img id="mainImgPreviewImg" src="" alt="">
                    <p id="mainImgName" style="font-size:11.5px;color:#718096;margin-top:6px;text-align:center"></p>
                </div>
            </div>

            <!-- Trạng thái -->
            <div class="form-card" style="margin-bottom:20px">
                <div class="card-title">⚙️ Cài đặt</div>
                <div class="toggle-row">
                    <input type="checkbox" name="is_active" id="is_active" checked>
                    <label for="is_active">✅ <strong>Hoạt động</strong><br><span style="font-weight:400;font-size:12px;color:#a0aec0">Hiển thị trên trang người dùng</span></label>
                </div>
                <div class="toggle-row">
                    <input type="checkbox" name="is_weekend_deal" id="is_weekend_deal">
                    <label for="is_weekend_deal">🏷️ <strong>Deal cuối tuần</strong><br><span style="font-weight:400;font-size:12px;color:#a0aec0">Hiện badge ưu đãi cuối tuần</span></label>
                </div>
            </div>

            <!-- Submit -->
            <div class="form-card">
                <div class="card-title">✅ Xác nhận</div>
                <p style="font-size:13px;color:#718096;margin:0 0 16px">Kiểm tra lại thông tin trước khi thêm khách sạn.</p>
                <div id="submitSummary" style="font-size:12.5px;color:#4a5568;background:#f8fafc;border-radius:8px;padding:12px 14px;margin-bottom:14px;line-height:1.8">
                    <div>🏨 Tên: <span id="sumName" style="font-weight:700">—</span></div>
                    <div>💰 Giá: <span id="sumPrice" style="font-weight:700">—</span></div>
                    <div>⭐ Đánh giá: <span id="sumRating" style="font-weight:700">8.5</span></div>
                    <div>🖼️ Ảnh đại diện: <span id="sumImg" style="font-weight:700">Chưa chọn</span></div>
                    <div>🖼️ Ảnh gallery: <span id="sumGallery" style="font-weight:700">0 ảnh</span></div>
                </div>
                <button type="submit" class="btn-add" id="btnAdd">
                    ➕ Thêm khách sạn
                </button>
                <a href="hotels.php" class="btn-cancel-link">← Huỷ, quay lại danh sách</a>
            </div>
        </div>

    </div><!-- /.add-wrap -->
</form>

<?php include "../includes/admin_footer.php"; ?>