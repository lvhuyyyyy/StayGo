<?php
require_once __DIR__ . '/../includes/security.php';
include("../config/database.php");

$action = $_GET['action'] ?? 'add';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$room   = null;
$errors = [];

// ============================================================
// ➕ THÊM PHÒNG
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add'])) {
    csrf_check();
    $hotel_id   = (int)($_POST['hotel_id'] ?? 0);
    $custom     = trim($_POST['room_name_custom'] ?? '');
    $room_name  = $custom !== '' ? $custom : trim($_POST['room_name'] ?? '');
    $price      = (int)($_POST['price'] ?? 0);
    $quantity   = (int)($_POST['quantity'] ?? 1);

    if (!$hotel_id)          $errors[] = 'Vui lòng chọn khách sạn.';
    if (empty($room_name))   $errors[] = 'Tên loại phòng không được để trống.';
    if ($price <= 0)         $errors[] = 'Giá phòng phải lớn hơn 0.';
    if ($quantity <= 0)      $errors[] = 'Số lượng phòng phải lớn hơn 0.';

    if (empty($errors)) {
        // Xử lý upload ảnh
        $image = '';
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext_allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_allowed)) {
                $errors[] = 'Ảnh chỉ chấp nhận JPG, PNG, WEBP.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Ảnh tối đa 5MB.';
            } else {
                $upload_dir = __DIR__ . '/../assets/images/rooms/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'room_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image = 'rooms/' . $filename;
                } else {
                    $errors[] = 'Lỗi khi upload ảnh.';
                }
            }
        }

        if (empty($errors)) {
            $room_name_esc = $conn->real_escape_string($room_name);
            $image_esc     = $conn->real_escape_string($image);
            $conn->query("INSERT INTO rooms (hotel_id, room_name, price, quantity, image)
                        VALUES ($hotel_id, '$room_name_esc', $price, $quantity, '$image_esc')");
            log_activity($conn, 'add_room', 'room', $conn->insert_id, "Thêm phòng: $room_name");
            header("Location: rooms.php?success=added");
            exit;
        }
    }
    $action = 'add';
}

// ============================================================
// ✏️ SỬA PHÒNG
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    csrf_check();
    $id         = (int)($_POST['id'] ?? 0);
    $hotel_id   = (int)($_POST['hotel_id'] ?? 0);
    $custom     = trim($_POST['room_name_custom'] ?? '');
    $room_name  = $custom !== '' ? $custom : trim($_POST['room_name'] ?? '');
    $price      = (int)($_POST['price'] ?? 0);
    $quantity   = (int)($_POST['quantity'] ?? 1);

    if (!$hotel_id)          $errors[] = 'Vui lòng chọn khách sạn.';
    if (empty($room_name))   $errors[] = 'Tên loại phòng không được để trống.';
    if ($price <= 0)         $errors[] = 'Giá phòng phải lớn hơn 0.';
    if ($quantity <= 0)      $errors[] = 'Số lượng phòng phải lớn hơn 0.';

    if (empty($errors)) {
        // Lấy ảnh cũ
        $old = $conn->query("SELECT image FROM rooms WHERE id=$id")->fetch_assoc();
        $image = $old['image'] ?? '';

        // Xử lý upload ảnh mới nếu có
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext_allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_allowed)) {
                $errors[] = 'Ảnh chỉ chấp nhận JPG, PNG, WEBP.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Ảnh tối đa 5MB.';
            } else {
                $upload_dir = __DIR__ . '/../assets/images/rooms/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'room_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    // Xóa ảnh cũ nếu có
                    if ($image && file_exists(__DIR__ . '/../assets/images/' . $image)) {
                        @unlink(__DIR__ . '/../assets/images/' . $image);
                    }
                    $image = 'rooms/' . $filename;
                } else {
                    $errors[] = 'Lỗi khi upload ảnh.';
                }
            }
        }

        if (empty($errors)) {
            $room_name_esc = $conn->real_escape_string($room_name);
            $image_esc     = $conn->real_escape_string($image);
            $conn->query("UPDATE rooms SET hotel_id=$hotel_id, room_name='$room_name_esc',
                        price=$price, quantity=$quantity, image='$image_esc'
                        WHERE id=$id");
            log_activity($conn, 'edit_room', 'room', $id, "Sửa phòng #$id");
            header("Location: rooms.php?success=updated");
            exit;
        }
    }
    $action = 'edit';
}

// ============================================================
// 🔍 LẤY THÔNG TIN PHÒNG KHI SỬA
// ============================================================
if ($action === 'edit' && $id > 0) {
    $res  = $conn->query("SELECT r.*, h.name as hotel_name FROM rooms r JOIN hotels h ON r.hotel_id = h.id WHERE r.id = $id");
    $room = $res->fetch_assoc();
    if (!$room) {
        header("Location: rooms.php?error=not_found");
        exit;
    }
}

// Lấy danh sách khách sạn cho dropdown
$hotels_list = $conn->query("SELECT id, name FROM hotels WHERE is_active=1 ORDER BY name ASC");

$page_title    = $action === 'edit' ? 'Chỉnh sửa phòng' : 'Thêm phòng mới';
$page_subtitle = $action === 'edit' ? 'Cập nhật thông tin loại phòng' : 'Tạo loại phòng mới';
include("../includes/admin_header.php");
?>

<!-- Header -->
<div class="page-header">
    <h2><?= $action === 'edit' ? '✏️ Chỉnh sửa phòng' : '➕ Thêm phòng mới' ?></h2>
    <a href="rooms.php" class="btn-back">← Quay lại</a>
</div>

<!-- Thông báo lỗi -->
<?php if (!empty($errors)): ?>
    <div class="error-box">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li>❌ <?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="form-card">
    <h2><?= $action === 'edit' ? '✏️ Chỉnh sửa thông tin phòng' : '➕ Thêm loại phòng mới' ?></h2>

    <form method="POST" enctype="multipart/form-data" id="roomForm">
        <?= csrf_field() ?>
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $room['id'] ?>">
            <input type="hidden" name="action_edit" value="1">
        <?php else: ?>
            <input type="hidden" name="action_add" value="1">
        <?php endif; ?>

        <!-- Tên phòng thực sự gửi lên server (hidden, được điền bởi JS) -->
        <input type="hidden" name="room_name_final" id="room_name_final">

        <!-- Chọn khách sạn -->
        <div class="form-group">
            <label>Khách sạn <span class="required">*</span></label>
            <select name="hotel_id" required>
                <option value="">— Chọn khách sạn —</option>
                <?php
                $hotels_list->data_seek(0);
                while ($h = $hotels_list->fetch_assoc()):
                    $selected = (($room['hotel_id'] ?? $_POST['hotel_id'] ?? 0) == $h['id']) ? 'selected' : '';
                ?>
                    <option value="<?= $h['id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($h['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Tên loại phòng — Bỏ required để tên tuỳ chỉnh không bị chặn -->
        <div class="form-group">
            <label>Tên loại phòng</label>
            <select name="room_name" id="room_name_select">
                <option value="">— Chọn loại phòng —</option>
                <?php
                $room_types = ['Phòng Tiêu Chuẩn', 'Phòng Cao Cấp', 'Phòng Hạng Sang', 'Phòng Suite', 'Phòng Gia Đình', 'Phòng Đơn', 'Phòng Đôi'];
                $current_room_name = $room['room_name'] ?? $_POST['room_name'] ?? '';
                foreach ($room_types as $rt):
                    $sel = ($current_room_name === $rt) ? 'selected' : '';
                ?>
                    <option value="<?= $rt ?>" <?= $sel ?>><?= $rt ?></option>
                <?php endforeach; ?>
                <?php if ($current_room_name && !in_array($current_room_name, $room_types)): ?>
                    <option value="<?= htmlspecialchars($current_room_name) ?>" selected>
                        <?= htmlspecialchars($current_room_name) ?>
                    </option>
                <?php endif; ?>
            </select>
            <small>Hoặc nhập tên tuỳ chỉnh bên dưới nếu không có trong danh sách</small>
        </div>

        <!-- Tên tuỳ chỉnh -->
        <div class="form-group">
            <label>Tên tuỳ chỉnh <span style="font-size:11px;color:#a0aec0">(để trống nếu dùng loại phòng ở trên)</span></label>
            <input type="text" name="room_name_custom" id="room_name_custom"
                placeholder="VD: Phòng Bungalow, Phòng View Rừng..."
                value="<?= (!in_array($current_room_name ?? '', $room_types ?? [])) ? htmlspecialchars($current_room_name ?? '') : '' ?>">
            <small>💡 Nếu nhập tên tuỳ chỉnh, sẽ ưu tiên dùng tên này thay cho loại phòng ở trên</small>
        </div>

        <!-- Giá & Số lượng -->
        <div class="form-row">
            <div class="form-group">
                <label>Giá phòng/đêm (đ) <span class="required">*</span></label>
                <input type="number" name="price" min="0" step="1000"
                    value="<?= $room['price'] ?? $_POST['price'] ?? '' ?>"
                    placeholder="VD: 800000" required>
                <small>Nhập số không có dấu chấm/phẩy</small>
            </div>
            <div class="form-group">
                <label>Số lượng phòng <span class="required">*</span></label>
                <input type="number" name="quantity" min="1" max="999"
                    value="<?= $room['quantity'] ?? $_POST['quantity'] ?? 1 ?>"
                    required>
                <small>Tổng số phòng loại này trong khách sạn</small>
            </div>
        </div>

        <!-- Ảnh phòng -->
        <div class="form-group">
            <label>Ảnh phòng</label>

            <?php if ($action === 'edit' && !empty($room['image'])): ?>
                <div class="current-img">
                    <small style="color:#276749;font-weight:600">✅ Ảnh hiện tại:</small>
                    <img src="/assets/images/<?= htmlspecialchars($room['image']) ?>"
                        alt="<?= htmlspecialchars($room['room_name']) ?>"
                        onerror="this.src='https://placehold.co/200x120?text=No+Image'">
                </div>
            <?php endif; ?>

            <label for="imgInput" class="img-upload-area" id="uploadZone">
                <input type="file" name="image" id="imgInput"
                    accept=".jpg,.jpeg,.png,.webp"
                    onchange="previewImage(this)">
                <div id="uploadText">
                    <div style="font-size:28px;margin-bottom:6px">🖼️</div>
                    <div style="font-size:13.5px;font-weight:600;color:#4a5568">
                        <?= ($action === 'edit' && !empty($room['image'])) ? 'Click để thay ảnh mới' : 'Click hoặc kéo thả ảnh' ?>
                    </div>
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">JPG · PNG · WEBP · Tối đa 5MB</div>
                </div>
            </label>

            <div class="img-preview" id="imgPreview">
                <img id="previewImg" src="" alt="preview">
                <p id="previewName" style="font-size:12px;color:#718096;margin-top:6px;text-align:center"></p>
            </div>
        </div>

        <!-- Buttons -->
        <div class="form-actions">
            <button type="submit" class="btn-submit">
                <?= $action === 'edit' ? '💾 Lưu thay đổi' : '➕ Thêm phòng' ?>
            </button>
            <a href="rooms.php" class="btn-back">Huỷ</a>
        </div>
    </form>
</div>

<?php include("../includes/admin_footer.php"); ?>