<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$page_title = 'Tra cứu đặt phòng - StayGo';
$booking    = null;
$error_msg  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $order_code = strtoupper(trim($_POST['order_code'] ?? ''));
    $email      = trim($_POST['email'] ?? '');

    if (!$order_code || !$email) {
        $error_msg = 'Vui lòng nhập đầy đủ mã đơn hàng và email.';
    } else {
        $stmt = $conn->prepare("
            SELECT b.*, r.room_name, r.price as room_price,
                   h.name as hotel_name, h.address as hotel_address
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            JOIN hotels h ON r.hotel_id = h.id
            WHERE b.order_code = ? AND b.email = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $order_code, $email);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            $error_msg = 'Không tìm thấy đơn đặt phòng. Vui lòng kiểm tra lại mã đơn hàng và email.';
        }
    }
}

$status_labels = [
    'pending'    => ['label' => 'Chờ xác nhận', 'color' => '#d69e2e', 'bg' => '#fffff0'],
    'confirmed'  => ['label' => 'Đã xác nhận',  'color' => '#276749', 'bg' => '#f0fff4'],
    'checked_in' => ['label' => 'Đang ở',        'color' => '#1e73be', 'bg' => '#ebf8ff'],
    'completed'  => ['label' => 'Hoàn thành',    'color' => '#553c9a', 'bg' => '#faf5ff'],
    'cancelled'  => ['label' => 'Đã hủy',        'color' => '#c53030', 'bg' => '#fff5f5'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.lookup-wrap{max-width:640px;margin:48px auto;padding:0 16px}
.lookup-card{background:#fff;border-radius:20px;box-shadow:0 4px 32px rgba(0,0,0,.09);padding:40px}
.lookup-title{font-size:22px;font-weight:800;color:#1a202c;margin:0 0 6px}
.lookup-sub{font-size:14px;color:#718096;margin:0 0 28px}
.lookup-field{margin-bottom:16px}
.lookup-field label{display:block;font-size:13px;font-weight:600;color:#4a5568;margin-bottom:6px}
.lookup-field input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;transition:border-color .2s;box-sizing:border-box}
.lookup-field input:focus{outline:none;border-color:#1e73be;box-shadow:0 0 0 3px rgba(30,115,190,.12)}
.btn-lookup{width:100%;padding:13px;background:linear-gradient(135deg,#1e73be,#2d9cdb);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px;font-family:inherit}
.btn-lookup:hover{opacity:.92}
.lookup-error{padding:14px 16px;background:#fff5f5;border:1px solid #feb2b2;border-radius:10px;color:#c53030;font-size:13.5px;margin-bottom:20px}
.result-card{background:#fff;border-radius:20px;box-shadow:0 4px 32px rgba(0,0,0,.09);overflow:hidden;margin-top:32px}
.result-header{padding:20px 28px;background:linear-gradient(135deg,#1e3a5f,#1e73be);color:#fff}
.result-hotel{font-size:18px;font-weight:800;margin:0 0 4px}
.result-order{font-size:13px;opacity:.8;margin:0}
.result-body{padding:28px}
.status-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;margin-bottom:24px}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.detail-item{display:flex;flex-direction:column;gap:3px}
.detail-label{font-size:11.5px;font-weight:600;color:#a0aec0;text-transform:uppercase;letter-spacing:.5px}
.detail-value{font-size:14px;font-weight:600;color:#2d3748}
.total-box{background:#f7fafc;border-radius:12px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.total-label{font-size:13px;color:#718096;font-weight:600}
.total-amount{font-size:22px;font-weight:800;color:#1e73be}
.lookup-cta{background:#ebf8ff;border-radius:12px;padding:18px 20px;text-align:center}
.cta-text{font-size:13.5px;color:#2c5282;margin:0 0 12px}
.cta-buttons{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn-cta-primary{padding:10px 22px;background:linear-gradient(135deg,#1e73be,#2d9cdb);color:#fff;border-radius:10px;font-weight:700;font-size:13.5px;text-decoration:none}
.btn-cta-secondary{padding:10px 22px;background:#fff;border:1.5px solid #1e73be;color:#1e73be;border-radius:10px;font-weight:700;font-size:13.5px;text-decoration:none}
.search-again{margin-top:20px;text-align:center}
.search-again a{font-size:13px;color:#718096;text-decoration:none;font-weight:600}
.search-again a:hover{color:#1e73be}
@media(max-width:480px){.detail-grid{grid-template-columns:1fr}}
</style>

<div class="lookup-wrap">

    <div class="lookup-card">
        <div class="lookup-title">Tra cứu đặt phòng</div>
        <div class="lookup-sub">Nhập mã đơn hàng và email để xem trạng thái đặt phòng của bạn.</div>

        <?php if ($error_msg): ?>
        <div class="lookup-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="lookup-field">
                <label>Mã đơn hàng</label>
                <input type="text" name="order_code" placeholder="VD: ORD17381234567"
                       value="<?= htmlspecialchars($_POST['order_code'] ?? '') ?>"
                       style="text-transform:uppercase"
                       oninput="this.value=this.value.toUpperCase()" required>
            </div>
            <div class="lookup-field">
                <label>Email đã đặt phòng</label>
                <input type="email" name="email" placeholder="example@gmail.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn-lookup">Tra cứu đơn hàng</button>
        </form>
    </div>

    <?php if ($booking): ?>
    <?php
    $status    = $booking['status'] ?? 'pending';
    $st        = $status_labels[$status] ?? $status_labels['pending'];
    $nights    = max(1, intval((strtotime($booking['check_out']) - strtotime($booking['check_in'])) / 86400));
    $is_guest  = empty($booking['user_id']);
    $is_logged = isset($_SESSION['user_id']);
    ?>
    <div class="result-card">
        <div class="result-header">
            <div class="result-hotel"><?= htmlspecialchars($booking['hotel_name']) ?></div>
            <div class="result-order">Mã đơn: <?= htmlspecialchars($booking['order_code']) ?></div>
        </div>
        <div class="result-body">
            <div class="status-badge" style="color:<?= $st['color'] ?>;background:<?= $st['bg'] ?>">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= $st['color'] ?>;display:inline-block"></span>
                <?= $st['label'] ?>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Họ và tên</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['full_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Số điện thoại</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['phone']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Loại phòng</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['room_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Khách sạn</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['hotel_address']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Nhận phòng</span>
                    <span class="detail-value"><?= date('d/m/Y', strtotime($booking['check_in'])) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Trả phòng</span>
                    <span class="detail-value"><?= date('d/m/Y', strtotime($booking['check_out'])) ?> <span style="font-weight:400;color:#a0aec0">(<?= $nights ?> đêm)</span></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phương thức TT</span>
                    <span class="detail-value"><?= htmlspecialchars($booking['payment_method']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Ngày đặt</span>
                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?></span>
                </div>
            </div>

            <div class="total-box">
                <span class="total-label">Tổng thanh toán</span>
                <span class="total-amount"><?= number_format($booking['total_price'], 0, ',', '.') ?> VNĐ</span>
            </div>

            <?php if ($is_guest && !$is_logged): ?>
            <div class="lookup-cta">
                <div class="cta-text">
                    Tạo tài khoản để quản lý đặt phòng, xem lịch sử và nhận ưu đãi độc quyền!<br>
                    Đơn hàng này sẽ tự động được liên kết khi bạn đăng ký bằng email <strong><?= htmlspecialchars($booking['email']) ?></strong>.
                </div>
                <div class="cta-buttons">
                    <a href="/auth/register.php" class="btn-cta-primary">Tạo tài khoản miễn phí</a>
                    <a href="/auth/login.php" class="btn-cta-secondary">Đăng nhập</a>
                </div>
            </div>
            <?php elseif ($is_logged): ?>
            <div style="text-align:center">
                <a href="/pages/my_bookings.php" class="btn-cta-primary">Xem tất cả đơn của tôi →</a>
            </div>
            <?php endif; ?>

            <div class="search-again"><a href="/pages/booking_lookup.php">← Tra cứu đơn khác</a></div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
