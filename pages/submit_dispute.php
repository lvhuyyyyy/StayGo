<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$booking_id = (int)($_GET['booking_id'] ?? 0);
$success    = false;
$error      = null;
$booking    = null;

// Lấy booking — cho phép cả user đăng nhập lẫn guest (verify bằng email nếu GET có email)
if ($booking_id) {
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("
            SELECT b.*, r.room_name, h.name AS hotel_name
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN hotels h ON r.hotel_id = h.id
            WHERE b.id = ? AND b.user_id = ?
        ");
        $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    } else {
        $guest_email = trim($_GET['email'] ?? '');
        $stmt = $conn->prepare("
            SELECT b.*, r.room_name, h.name AS hotel_name
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN hotels h ON r.hotel_id = h.id
            WHERE b.id = ? AND b.email = ? AND b.user_id IS NULL
        ");
        $stmt->bind_param("is", $booking_id, $guest_email);
    }
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
}

if (!$booking) {
    $error = 'Không tìm thấy đơn đặt phòng. Vui lòng kiểm tra lại.';
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking && !$success) {
    csrf_check();
    $type        = $_POST['type']        ?? 'OTHER';
    $description = trim($_POST['description'] ?? '');
    $valid_types = ['WRONG_ROOM', 'INCIDENT', 'REFUND_REQUEST', 'OTHER'];

    if (!in_array($type, $valid_types, true)) $type = 'OTHER';

    if (strlen($description) < 20) {
        $error = 'Vui lòng mô tả chi tiết hơn (ít nhất 20 ký tự).';
    } else {
        // Kiểm tra đã có dispute chưa (chỉ cho 1 dispute mở per booking)
        $ex = $conn->prepare("SELECT id FROM disputes WHERE booking_id=? AND status IN ('OPEN','IN_PROGRESS') LIMIT 1");
        $ex->bind_param("i", $booking_id);
        $ex->execute();
        if ($ex->get_result()->fetch_assoc()) {
            $error = 'Đơn đặt phòng này đã có khiếu nại đang được xử lý.';
        } else {
            $user_id     = $_SESSION['user_id'] ?? null;
            $guest_email = !$user_id ? $booking['email'] : null;
            $desc_esc    = $conn->real_escape_string($description);
            $type_esc    = $conn->real_escape_string($type);
            $ge_esc      = $guest_email ? $conn->real_escape_string($guest_email) : 'NULL';
            $uid_val     = $user_id ? (int)$user_id : 'NULL';
            $ge_sql      = $guest_email ? "'$ge_esc'" : 'NULL';

            $conn->query("
                INSERT INTO disputes (booking_id, user_id, guest_email, type, description, status, created_at)
                VALUES ($booking_id, $uid_val, $ge_sql, '$type_esc', '$desc_esc', 'OPEN', NOW())
            ");

            if ($conn->affected_rows > 0) {
                $success = true;
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại.';
            }
        }
    }
}

$page_title = 'Gửi khiếu nại - StayGo';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dispute-wrap{max-width:620px;margin:48px auto;padding:0 16px}
.dispute-card{background:#fff;border-radius:20px;box-shadow:0 4px 32px rgba(0,0,0,.09);overflow:hidden}
.dispute-head{background:linear-gradient(135deg,#1e3a5f,#c53030);padding:24px 28px;color:#fff}
.dispute-head h2{font-size:20px;font-weight:800;margin:0 0 4px}
.dispute-head p{font-size:13.5px;opacity:.85;margin:0}
.dispute-body{padding:28px}
.booking-info{background:#f7fafc;border-radius:12px;padding:16px;margin-bottom:24px}
.booking-info-title{font-size:11.5px;font-weight:700;color:#a0aec0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.booking-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.bi-item label{font-size:11.5px;color:#a0aec0;font-weight:600;display:block;text-transform:uppercase;letter-spacing:.3px;margin-bottom:2px}
.bi-item span{font-size:13.5px;font-weight:600;color:#2d3748}
.field-group{margin-bottom:18px}
.field-group label{display:block;font-size:13px;font-weight:700;color:#4a5568;margin-bottom:6px}
.field-group select,.field-group textarea{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box;transition:border-color .2s}
.field-group select:focus,.field-group textarea:focus{outline:none;border-color:#c53030;box-shadow:0 0 0 3px rgba(197,48,48,.1)}
.field-group textarea{resize:vertical;min-height:110px;line-height:1.6}
.type-hint{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:18px}
.type-hint-item{padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:.15s}
.type-hint-item:hover{border-color:#c53030;background:#fff5f5}
.type-hint-item.active{border-color:#c53030;background:#fff5f5}
.type-hint-icon{font-size:20px;margin-bottom:4px}
.type-hint-label{font-size:12.5px;font-weight:700;color:#2d3748}
.type-hint-desc{font-size:11.5px;color:#718096;margin-top:2px}
.btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,#c53030,#e53e3e);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-submit:hover{opacity:.92}
.alert-err{padding:14px;background:#fff5f5;border:1px solid #feb2b2;border-radius:10px;color:#c53030;font-size:13.5px;margin-bottom:18px}
.success-card{text-align:center;padding:48px 28px}
.success-icon{font-size:56px;margin-bottom:16px}
.success-title{font-size:22px;font-weight:800;color:#276749;margin-bottom:8px}
.success-sub{font-size:14px;color:#718096;line-height:1.6;margin-bottom:28px}
.btn-back{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#276749,#38a169);color:#fff;border-radius:12px;font-weight:700;font-size:14px;text-decoration:none}
</style>

<div class="dispute-wrap">
    <div class="dispute-card">
        <div class="dispute-head">
            <h2>⚖️ Gửi khiếu nại</h2>
            <p>Chúng tôi sẽ xem xét và phản hồi trong vòng 1–3 ngày làm việc.</p>
        </div>

        <?php if ($success): ?>
        <div class="success-card">
            <div class="success-icon">✅</div>
            <div class="success-title">Đã gửi khiếu nại!</div>
            <div class="success-sub">
                Chúng tôi đã nhận được khiếu nại của bạn về đơn hàng
                <strong><?= htmlspecialchars($booking['order_code']) ?></strong>.<br>
                Admin sẽ liên hệ qua email <strong><?= htmlspecialchars($booking['email']) ?></strong> trong 1–3 ngày làm việc.
            </div>
            <a href="<?= isset($_SESSION['user_id']) ? '/pages/my_bookings.php' : '/pages/booking_lookup.php' ?>" class="btn-back">
                ← Quay lại
            </a>
        </div>

        <?php elseif (!$booking): ?>
        <div class="dispute-body">
            <div class="alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
            <a href="<?= isset($_SESSION['user_id']) ? '/pages/my_bookings.php' : '/pages/booking_lookup.php' ?>"
               style="color:#1e73be;font-weight:600;font-size:13.5px;text-decoration:none">← Quay lại</a>
        </div>

        <?php else: ?>
        <div class="dispute-body">

            <!-- Thông tin booking -->
            <div class="booking-info">
                <div class="booking-info-title">Thông tin đặt phòng</div>
                <div class="booking-info-grid">
                    <div class="bi-item"><label>Mã đơn</label><span><?= htmlspecialchars($booking['order_code']) ?></span></div>
                    <div class="bi-item"><label>Khách sạn</label><span><?= htmlspecialchars($booking['hotel_name']) ?></span></div>
                    <div class="bi-item"><label>Loại phòng</label><span><?= htmlspecialchars($booking['room_name']) ?></span></div>
                    <div class="bi-item"><label>Nhận phòng</label><span><?= date('d/m/Y', strtotime($booking['check_in'])) ?> → <?= date('d/m/Y', strtotime($booking['check_out'])) ?></span></div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="disputeForm">
                <?= csrf_field() ?>

                <!-- Chọn loại khiếu nại -->
                <div class="field-group">
                    <label>Loại khiếu nại</label>
                    <div class="type-hint">
                        <?php
                        $types = [
                            'WRONG_ROOM'     => ['🛏️', 'Phòng sai mô tả', 'Phòng không đúng như quảng cáo'],
                            'INCIDENT'       => ['⚠️', 'Sự cố tại khách sạn', 'Mất an toàn, vệ sinh, thiết bị hỏng'],
                            'REFUND_REQUEST' => ['💸', 'Yêu cầu hoàn tiền', 'Tranh chấp về khoản hoàn trả'],
                            'OTHER'          => ['📋', 'Khác', 'Vấn đề không thuộc các mục trên'],
                        ];
                        $sel_type = $_POST['type'] ?? 'OTHER';
                        foreach ($types as $k => [$icon, $label, $desc]):
                        ?>
                        <div class="type-hint-item <?= $sel_type===$k?'active':'' ?>"
                             onclick="selectType('<?= $k ?>')">
                            <div class="type-hint-icon"><?= $icon ?></div>
                            <div class="type-hint-label"><?= $label ?></div>
                            <div class="type-hint-desc"><?= $desc ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($sel_type) ?>">
                </div>

                <div class="field-group">
                    <label>Mô tả chi tiết <span style="color:#c53030">*</span></label>
                    <textarea name="description" placeholder="Mô tả rõ vấn đề gặp phải, thời gian xảy ra, ảnh hưởng đến bạn như thế nào... (tối thiểu 20 ký tự)" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div style="font-size:12.5px;color:#a0aec0;margin-bottom:16px;line-height:1.6">
                    ℹ️ Sau khi gửi, Admin sẽ xem xét khiếu nại và liên hệ với bạn qua email
                    <strong><?= htmlspecialchars($booking['email']) ?></strong>.
                    Trong thời gian xử lý, khoản giải ngân cho khách sạn có thể bị tạm giữ.
                </div>

                <button type="submit" class="btn-submit">Gửi khiếu nại</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function selectType(val) {
    document.getElementById('typeInput').value = val;
    document.querySelectorAll('.type-hint-item').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
