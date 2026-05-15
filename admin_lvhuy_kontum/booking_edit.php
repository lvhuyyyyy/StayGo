<?php
include "../config/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: bookings.php"); exit; }

$errors = [];

// ---- Xử lý POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_in  = trim($_POST['check_in']  ?? '');
    $check_out = trim($_POST['check_out'] ?? '');
    $room_id   = (int)($_POST['room_id']  ?? 0);
    $note      = trim($_POST['note']      ?? '');

    if (!$check_in  || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in))  $errors[] = 'Ngày nhận phòng không hợp lệ.';
    if (!$check_out || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) $errors[] = 'Ngày trả phòng không hợp lệ.';
    if (empty($errors) && strtotime($check_out) <= strtotime($check_in))  $errors[] = 'Ngày trả phòng phải sau ngày nhận phòng.';
    if (!$room_id) $errors[] = 'Vui lòng chọn loại phòng.';

    if (empty($errors)) {
        // Tính lại tổng tiền theo số đêm × giá phòng
        $nights    = (int)round((strtotime($check_out) - strtotime($check_in)) / 86400);
        $room_info = $conn->query("SELECT price FROM rooms WHERE id = $room_id")->fetch_assoc();
        if (!$room_info) { $errors[] = 'Loại phòng không tồn tại.'; }
        else {
            $new_price = $nights * (float)$room_info['price'];
            $note_esc  = $conn->real_escape_string($note);
            $conn->query("UPDATE bookings SET
                check_in    = '$check_in',
                check_out   = '$check_out',
                room_id     = $room_id,
                total_price = $new_price,
                note        = '$note_esc'
                WHERE id = $id");
            header("Location: booking_detail.php?id=$id&success=edited");
            exit;
        }
    }
}

// ---- Lấy booking ----
$booking = $conn->query("
    SELECT b.*, r.room_name, r.hotel_id, h.name AS hotel_name
    FROM bookings b
    LEFT JOIN rooms  r ON b.room_id  = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.id = $id
")->fetch_assoc();

if (!$booking) { header("Location: bookings.php?error=notfound"); exit; }

// ---- Danh sách phòng của khách sạn đó ----
$hotel_id    = (int)($booking['hotel_id'] ?? 0);
$rooms_list  = $hotel_id
    ? $conn->query("SELECT r.id, r.room_name, r.price FROM rooms r WHERE r.hotel_id = $hotel_id ORDER BY r.price ASC")->fetch_all(MYSQLI_ASSOC)
    : [];

// ---- Tất cả khách sạn (để đổi khách sạn nếu cần) ----
$hotels_all = $conn->query("SELECT id, name FROM hotels WHERE is_active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$page_title    = 'Sửa đặt phòng #' . htmlspecialchars($booking['order_code']);
$page_subtitle = 'Chỉnh sửa ngày và loại phòng';
include "../includes/admin_header.php";
?>

<div style="margin-bottom:18px">
    <a href="booking_detail.php?id=<?= $id ?>" style="display:inline-flex;align-items:center;gap:6px;color:#4a5568;text-decoration:none;font-size:13.5px;font-weight:600;
       padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;background:#fff"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
        ← Quay lại chi tiết
    </a>
</div>

<div style="max-width:700px">
    <div style="background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;padding:28px 32px">
        <h2 style="font-size:17px;font-weight:800;color:#1a202c;margin:0 0 6px">✏️ Sửa đặt phòng</h2>
        <p style="font-size:13px;color:#a0aec0;margin:0 0 24px">Mã đơn: <strong><?= htmlspecialchars($booking['order_code']) ?></strong> · Khách: <strong><?= htmlspecialchars($booking['full_name']) ?></strong></p>

        <?php if (!empty($errors)): ?>
        <div style="background:#fff5f5;border:1.5px solid #feb2b2;border-radius:10px;padding:12px 16px;margin-bottom:20px">
            <?php foreach ($errors as $e): ?>
                <div style="color:#c53030;font-size:13.5px;font-weight:600">❌ <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Khách sạn (chỉ đọc, đổi phòng qua select) -->
            <div style="margin-bottom:18px">
                <label style="display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px">🏨 Khách sạn</label>
                <div style="padding:10px 14px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;color:#718096">
                    <?= htmlspecialchars($booking['hotel_name'] ?? '—') ?>
                    <span style="font-size:11px;color:#a0aec0;margin-left:6px">(Để đổi khách sạn, vui lòng tạo booking mới)</span>
                </div>
            </div>

            <!-- Loại phòng -->
            <div style="margin-bottom:18px">
                <label style="display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px">🛏️ Loại phòng <span style="color:#e53e3e">*</span></label>
                <?php if (empty($rooms_list)): ?>
                    <div style="color:#c53030;font-size:13px">Không tìm thấy phòng nào cho khách sạn này.</div>
                <?php else: ?>
                <select name="room_id" id="room_id"
                    style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;background:#fff"
                    onchange="updatePrice()">
                    <?php foreach ($rooms_list as $r): ?>
                    <option value="<?= $r['id'] ?>"
                            data-price="<?= $r['price'] ?>"
                            <?= $r['id'] == $booking['room_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['room_name']) ?> — <?= number_format($r['price'], 0, ',', '.') ?>đ/đêm
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Ngày nhận / trả phòng -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px">📅 Ngày nhận phòng <span style="color:#e53e3e">*</span></label>
                    <input type="date" name="check_in" id="check_in"
                        value="<?= htmlspecialchars($_POST['check_in'] ?? $booking['check_in'] ?? '') ?>"
                        style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;box-sizing:border-box"
                        onchange="updatePrice()">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px">📅 Ngày trả phòng <span style="color:#e53e3e">*</span></label>
                    <input type="date" name="check_out" id="check_out"
                        value="<?= htmlspecialchars($_POST['check_out'] ?? $booking['check_out'] ?? '') ?>"
                        style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;box-sizing:border-box"
                        onchange="updatePrice()">
                </div>
            </div>

            <!-- Preview tổng tiền -->
            <div id="price-preview" style="background:#f0fff4;border:1.5px solid #9ae6b4;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:#276749;font-weight:600">
                Chọn ngày và phòng để xem tổng tiền mới
            </div>

            <!-- Ghi chú -->
            <div style="margin-bottom:24px">
                <label style="display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px">📝 Ghi chú</label>
                <textarea name="note" rows="3"
                    style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:13.5px;resize:vertical;box-sizing:border-box"
                    placeholder="Ghi chú của khách (tuỳ chọn)"><?= htmlspecialchars($_POST['note'] ?? $booking['note'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:10px">
                <button type="submit"
                    style="padding:11px 28px;background:#1e73be;color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer"
                    onmouseover="this.style.background='#1558a8'" onmouseout="this.style.background='#1e73be'">
                    💾 Lưu thay đổi
                </button>
                <a href="booking_detail.php?id=<?= $id ?>"
                   style="padding:11px 20px;background:#f1f5f9;color:#4a5568;border-radius:9px;font-size:14px;font-weight:600;text-decoration:none">
                    Hủy
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function updatePrice() {
    const sel      = document.getElementById('room_id');
    const checkIn  = document.getElementById('check_in').value;
    const checkOut = document.getElementById('check_out').value;
    const preview  = document.getElementById('price-preview');
    if (!sel || !checkIn || !checkOut) return;
    const pricePerNight = parseFloat(sel.options[sel.selectedIndex].dataset.price || 0);
    const d1 = new Date(checkIn), d2 = new Date(checkOut);
    const nights = Math.round((d2 - d1) / 86400000);
    if (nights <= 0) {
        preview.style.background = '#fff5f5'; preview.style.borderColor = '#feb2b2'; preview.style.color = '#c53030';
        preview.textContent = '⚠️ Ngày trả phòng phải sau ngày nhận phòng';
        return;
    }
    const total = nights * pricePerNight;
    preview.style.background = '#f0fff4'; preview.style.borderColor = '#9ae6b4'; preview.style.color = '#276749';
    preview.innerHTML = `✅ ${nights} đêm × ${pricePerNight.toLocaleString('vi-VN')}đ = <strong>${total.toLocaleString('vi-VN')}đ</strong>`;
}
document.addEventListener('DOMContentLoaded', updatePrice);
</script>

<?php include "../includes/admin_footer.php"; ?>
