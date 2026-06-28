<?php
$page_title    = 'Thêm phòng mới';
$page_subtitle = 'Tạo loại phòng mới cho khách sạn';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

$msg    = null;
$errors = [];
$input  = ['room_name'=>'','price'=>'','quantity'=>1,'max_guests'=>2,'bed_type'=>'','description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input['room_name']   = trim($_POST['room_name'] ?? '');
    $input['price']       = (int)($_POST['price'] ?? 0);
    $input['quantity']    = max(0, (int)($_POST['quantity'] ?? 1));
    $input['max_guests']  = max(1, (int)($_POST['max_guests'] ?? 2));
    $input['min_stay']    = max(1, (int)($_POST['min_stay'] ?? 1));
    $input['max_stay']    = ($_POST['max_stay'] ?? '') !== '' ? max(1, (int)$_POST['max_stay']) : null;
    $input['bed_type']    = trim($_POST['bed_type'] ?? '');
    $input['description'] = trim($_POST['description'] ?? '');

    if (!$input['room_name']) $errors[] = 'Tên loại phòng không được để trống.';
    if ($input['price'] <= 0) $errors[] = 'Giá phòng phải lớn hơn 0.';

    // Upload ảnh
    $image = null;
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
            $filename = "room_" . time() . "_" . rand(100, 999) . ".$ext";
            if (move_uploaded_file($_FILES['image']['tmp_name'], "{$upload_dir}{$filename}")) {
                $image = "rooms/$filename";
            } else {
                $errors[] = 'Lỗi khi upload ảnh.';
            }
        }
    }

    if (empty($errors)) {
        $rn_esc   = $conn->real_escape_string($input['room_name']);
        $bt_esc   = $conn->real_escape_string($input['bed_type']);
        $desc_esc = $conn->real_escape_string($input['description']);
        $img_esc  = $conn->real_escape_string($image ?? '');

        $max_stay_val = $input['max_stay'] !== null ? (int)$input['max_stay'] : 'NULL';
        $conn->query("
            INSERT INTO rooms (hotel_id, room_name, bed_type, price, quantity, min_stay, max_stay, max_guests, description, image, is_active)
            VALUES ($hotel_id, '$rn_esc', '$bt_esc', {$input['price']}, {$input['quantity']},
                    {$input['min_stay']}, $max_stay_val, {$input['max_guests']}, '$desc_esc',
                    " . ($image ? "'$img_esc'" : "NULL") . ", 1)
        ");

        // Cập nhật giá thấp nhất cho hotel
        $conn->query("
            UPDATE hotels SET price = (SELECT MIN(price) FROM rooms WHERE hotel_id = $hotel_id AND is_active = 1)
            WHERE id = $hotel_id
        ");

        header('Location: ' . BASE_PATH . '/hotel/rooms.php?added=1');
        exit;
    }
}
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $e): ?>⚠️ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="max-width:680px">
<div class="card">
    <div class="card-header">
        <span class="card-title">➕ Thêm loại phòng mới</span>
        <a href="/hotel/rooms.php" class="btn btn-outline btn-sm">← Quay lại</a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="form-row">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1">
                    <label>Tên loại phòng <span style="color:#e53e3e">*</span></label>
                    <input type="text" name="room_name" value="<?= htmlspecialchars($input['room_name']) ?>"
                           placeholder="VD: Phòng Deluxe, Phòng Superior..." required>
                </div>

                <div class="form-group">
                    <label>Giá / đêm (VNĐ) <span style="color:#e53e3e">*</span></label>
                    <input type="number" name="price" value="<?= $input['price'] ?: '' ?>" min="1"
                           placeholder="VD: 500000" required>
                </div>

                <div class="form-group">
                    <label>Số phòng hiện có</label>
                    <input type="number" name="quantity" value="<?= $input['quantity'] ?>" min="0">
                </div>

                <div class="form-group">
                    <label>Số khách tối đa</label>
                    <input type="number" name="max_guests" value="<?= $input['max_guests'] ?>" min="1" max="20">
                </div>

                <div class="form-group">
                    <label>Lưu trú tối thiểu (đêm)</label>
                    <input type="number" name="min_stay" value="<?= $input['min_stay'] ?>" min="1" max="30">
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">Khách phải đặt ít nhất n đêm</div>
                </div>

                <div class="form-group">
                    <label>Lưu trú tối đa (đêm, tuỳ chọn)</label>
                    <input type="number" name="max_stay" value="<?= $input['max_stay'] ?? '' ?>" min="1" max="365" placeholder="Không giới hạn">
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">Để trống = không giới hạn</div>
                </div>

                <div class="form-group">
                    <label>Loại giường</label>
                    <select name="bed_type" style="padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;width:100%">
                        <option value="">-- Chọn loại giường --</option>
                        <?php foreach (['Giường đôi','Giường đơn','2 giường đơn','Giường king','Giường queen'] as $bt): ?>
                        <option value="<?= $bt ?>" <?= $input['bed_type'] === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label>Mô tả phòng</label>
                    <textarea name="description" rows="4"
                              placeholder="Tiện nghi, diện tích, view... (tuỳ chọn)"><?= htmlspecialchars($input['description']) ?></textarea>
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label>Ảnh phòng</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           style="padding:8px;background:#f7fafc">
                    <div style="font-size:12px;color:#a0aec0;margin-top:4px">JPG, PNG, WEBP · Tối đa 5MB</div>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px">
                <button type="submit" class="btn btn-primary">➕ Tạo phòng</button>
                <a href="/hotel/rooms.php" class="btn btn-outline">Hủy</a>
            </div>
        </form>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
