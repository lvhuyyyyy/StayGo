<?php
$page_title    = 'Phòng của tôi';
$page_subtitle = 'Quản lý danh sách phòng, giá và số lượng';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

$msg   = null;
$error = null;

// ── Toggle active status ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['toggle_room'])) {
    csrf_check();
    $rid = (int)($_POST['room_id'] ?? 0);
    $chk = $conn->query("SELECT id, is_active FROM rooms WHERE id = $rid AND hotel_id = $hotel_id");
    if ($room = $chk->fetch_assoc()) {
        $new = $room['is_active'] ? 0 : 1;
        $conn->query("UPDATE rooms SET is_active = $new WHERE id = $rid");
        $msg = $new ? 'Đã kích hoạt phòng.' : 'Đã tắt phòng.';
    }
}

// ── Danh sách phòng ───────────────────────────────────────────────────
$rooms = $conn->query("
    SELECT r.*,
           (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status NOT IN ('cancelled','pending')) AS booking_count,
           (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status = 'confirmed') AS active_bookings
    FROM rooms r
    WHERE r.hotel_id = $hotel_id
    ORDER BY r.is_active DESC, r.room_name ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="font-size:13.5px;color:#718096"><?= count($rooms) ?> phòng tổng cộng</div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
<?php foreach ($rooms as $r): ?>
<div class="card" style="margin-bottom:0">
    <!-- Room image -->
    <?php if ($r['image']): ?>
    <img src="<?= htmlspecialchars($r['image']) ?>" alt=""
         style="width:100%;height:160px;object-fit:cover;border-radius:16px 16px 0 0">
    <?php else: ?>
    <div style="width:100%;height:120px;background:linear-gradient(135deg,#ebf8ff,#bee3f8);border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:center;font-size:40px">🛏️</div>
    <?php endif; ?>

    <div style="padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
            <div>
                <div style="font-size:15px;font-weight:700;color:#1a365d"><?= htmlspecialchars($r['room_name']) ?></div>
                <?php if ($r['room_type'] ?? null): ?>
                <div style="font-size:12px;color:#a0aec0"><?= htmlspecialchars($r['room_type']) ?></div>
                <?php endif; ?>
            </div>
            <span class="badge <?= $r['is_active'] ? 'badge-confirmed' : 'badge-cancelled' ?>">
                <?= $r['is_active'] ? 'Hoạt động' : 'Tắt' ?>
            </span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
            <div style="background:#f7fafc;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:18px;font-weight:800;color:#276749"><?= number_format($r['price'], 0, ',', '.') ?></div>
                <div style="font-size:11px;color:#a0aec0">đ / đêm</div>
            </div>
            <div style="background:#f7fafc;border-radius:8px;padding:10px;text-align:center">
                <div style="font-size:18px;font-weight:800;color:#2b6cb0"><?= (int)$r['quantity'] ?></div>
                <div style="font-size:11px;color:#a0aec0">phòng còn lại</div>
            </div>
        </div>

        <?php if ($r['description'] ?? null): ?>
        <div style="font-size:12px;color:#718096;margin-bottom:12px;line-height:1.5">
            <?= htmlspecialchars(mb_substr($r['description'], 0, 80)) ?><?= mb_strlen($r['description']) > 80 ? '...' : '' ?>
        </div>
        <?php endif; ?>

        <div style="font-size:12px;color:#a0aec0;margin-bottom:12px">
            <?= (int)$r['booking_count'] ?> đặt phòng · <?= (int)$r['active_bookings'] ?> đang xác nhận
        </div>

        <div style="display:flex;gap:8px">
            <a href="/hotel/edit_room.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">✏️ Chỉnh sửa</a>
            <form method="POST" style="flex:1">
                <?= csrf_field() ?>
                <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="toggle_room" value="1">
                <button type="submit" class="btn <?= $r['is_active'] ? 'btn-outline' : 'btn-success' ?> btn-sm" style="width:100%"
                        onclick="return confirm('<?= $r['is_active'] ? 'Tắt' : 'Bật' ?> phòng này?')">
                    <?= $r['is_active'] ? '🔕 Tắt' : '✅ Bật' ?>
                </button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($rooms)): ?>
<div style="grid-column:1/-1;text-align:center;padding:60px;color:#a0aec0;background:#fff;border-radius:16px">
    Chưa có phòng nào. Liên hệ Admin để thêm phòng cho khách sạn của bạn.
</div>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
