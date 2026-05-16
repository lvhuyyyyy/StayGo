<?php
$page_title    = 'Chỉnh sửa phòng';
$page_subtitle = 'Cập nhật thông tin, giá và ảnh phòng';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

$room_id = (int)($_GET['id'] ?? 0);
$msg     = null;
$errors  = [];

// Lấy thông tin phòng — chỉ cho phép chỉnh phòng thuộc hotel này
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ? AND hotel_id = ?");
$stmt->bind_param("ii", $room_id, $hotel_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    echo '<div class="alert alert-error">⚠️ Không tìm thấy phòng.</div>';
    require_once __DIR__ . '/../includes/hotel_footer.php';
    exit;
}

// ── POST: Lưu thay đổi ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $room_name   = trim($_POST['room_name'] ?? '');
    $price       = (int)($_POST['price'] ?? 0);
    $quantity    = (int)($_POST['quantity'] ?? 1);
    $min_stay    = max(1, (int)($_POST['min_stay'] ?? 1));
    $max_stay    = ($_POST['max_stay'] ?? '') !== '' ? max(1, (int)$_POST['max_stay']) : null;
    $description = trim($_POST['description'] ?? '');

    if (!$room_name) $errors[] = 'Tên phòng không được để trống.';
    if ($price <= 0) $errors[] = 'Giá phòng phải lớn hơn 0.';
    if ($quantity < 0) $errors[] = 'Số lượng không hợp lệ.';

    // Upload ảnh mới (nếu có)
    $new_image = $room['image'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext_allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ext_allowed)) {
            $errors[] = 'Ảnh chỉ chấp nhận JPG, PNG, WEBP.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Ảnh tối đa 5MB.';
        } else {
            $upload_dir = __DIR__ . '/../assets/images/rooms/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'room_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $new_image = 'rooms/' . $filename;
            } else {
                $errors[] = 'Lỗi khi upload ảnh.';
            }
        }
    }

    if (empty($errors)) {
        $rn_esc   = $conn->real_escape_string($room_name);
        $desc_esc = $conn->real_escape_string($description);
        $img_esc  = $conn->real_escape_string($new_image);

        $max_stay_val = $max_stay !== null ? (int)$max_stay : 'NULL';
        $conn->query("
            UPDATE rooms
            SET room_name = '$rn_esc', price = $price, quantity = $quantity,
                min_stay = $min_stay, max_stay = $max_stay_val,
                description = '$desc_esc', image = '$img_esc'
            WHERE id = $room_id AND hotel_id = $hotel_id
        ");

        // Cập nhật giá thấp nhất cho hotel
        $conn->query("
            UPDATE hotels SET price = (SELECT MIN(price) FROM rooms WHERE hotel_id = $hotel_id AND is_active = 1)
            WHERE id = $hotel_id
        ");

        $msg = 'Đã lưu thay đổi cho phòng ' . htmlspecialchars($room_name) . '.';
        // Reload room data
        $stmt2 = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt2->bind_param("i", $room_id);
        $stmt2->execute();
        $room = $stmt2->get_result()->fetch_assoc();
    }
}
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= $msg ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="max-width:680px">
<div class="card">
    <div class="card-header">
        <span class="card-title">✏️ Chỉnh sửa: <?= htmlspecialchars($room['room_name']) ?></span>
        <a href="/hotel/rooms.php" class="btn btn-outline btn-sm">← Quay lại</a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="form-row">
            <?= csrf_field() ?>

            <!-- Ảnh hiện tại -->
            <?php if ($room['image']): ?>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px">Ảnh hiện tại</label>
                <img src="/assets/images/<?= htmlspecialchars($room['image']) ?>" alt=""
                     style="max-height:180px;border-radius:10px;object-fit:cover;border:2px solid #e2e8f0">
            </div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1">
                    <label>Tên loại phòng <span style="color:#e53e3e">*</span></label>
                    <input type="text" name="room_name" value="<?= htmlspecialchars($room['room_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Giá / đêm (VNĐ) <span style="color:#e53e3e">*</span></label>
                    <input type="number" name="price" value="<?= (int)$room['price'] ?>" min="1" required>
                </div>

                <div class="form-group">
                    <label>Số phòng còn lại</label>
                    <input type="number" name="quantity" value="<?= (int)$room['quantity'] ?>" min="0">
                </div>

                <div class="form-group">
                    <label>Lưu trú tối thiểu (đêm)</label>
                    <input type="number" name="min_stay" value="<?= (int)($room['min_stay'] ?? 1) ?>" min="1" max="30">
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">Khách phải đặt ít nhất n đêm</div>
                </div>

                <div class="form-group">
                    <label>Lưu trú tối đa (đêm, tuỳ chọn)</label>
                    <input type="number" name="max_stay" value="<?= $room['max_stay'] ?? '' ?>" min="1" max="365" placeholder="Không giới hạn">
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">Để trống = không giới hạn</div>
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label>Mô tả phòng</label>
                    <textarea name="description" rows="4" placeholder="Tiện nghi, diện tích, view... (tuỳ chọn)"><?= htmlspecialchars($room['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label>Ảnh phòng mới (để trống nếu không đổi)</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           style="padding:8px;background:#f7fafc">
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">JPG, PNG, WEBP · Tối đa 5MB</div>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px">
                <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
                <a href="/hotel/rooms.php" class="btn btn-outline">Hủy</a>
            </div>
        </form>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
