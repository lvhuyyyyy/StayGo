<?php
// Cần include database/security trước header.php để có thể redirect sang VNPay/MoMo
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/payment_config.php';
require_once __DIR__ . '/../includes/email_helper.php';

if (!isset($_GET['hotel_id'])) {
    header("Location: hotels.php");
    exit();
}

$hotel_id = intval($_GET['hotel_id']);
$hotel_query = mysqli_query($conn, "SELECT id, name FROM hotels WHERE id = $hotel_id");
$hotel = $hotel_query ? mysqli_fetch_assoc($hotel_query) : null;
if (!$hotel) { header("Location: hotels.php"); exit(); }

$rooms_result = mysqli_query($conn, "SELECT id, room_name, price, quantity FROM rooms WHERE hotel_id = $hotel_id");
$rooms_data = [];
if ($rooms_result) while ($r = mysqli_fetch_assoc($rooms_result)) $rooms_data[] = $r;

// Kiểm tra discount tài khoản mới
$new_user_discount = 0;
$has_new_discount  = false;
if (isset($_SESSION['user_id'])
    && isset($_SESSION['new_user_discount'])
    && $_SESSION['new_user_discount_used'] === false) {
    $new_user_discount = intval($_SESSION['new_user_discount']);
    $has_new_discount  = true;
}

// Pre-fill từ hotel_detail modal
$prefill_room_id  = intval($_GET['room_id']  ?? 0);
$prefill_checkin  = $_GET['checkin']  ?? '';
$prefill_checkout = $_GET['checkout'] ?? '';

$qr_data    = null;
$booking_id = null;
$error_msg  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $full_name      = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $order_code     = trim($_POST['order_code'] ?? '');
    $note           = trim($_POST['note'] ?? '');
    $checkin        = $_POST['checkin'] ?? '';
    $checkout       = $_POST['checkout'] ?? '';
    $room_id        = intval($_POST['room_id'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? '';

    $total_price     = 0;
    $original_price  = 0;
    $discount_amount = 0;

    // ── Validate ngày: check_out phải SAU check_in, cả hai phải hợp lệ ──
    $ts_in  = strtotime($checkin);
    $ts_out = strtotime($checkout);
    if (!$ts_in || !$ts_out) {
        $error_msg = 'Ngày nhận phòng hoặc ngày trả phòng không hợp lệ.';
    } elseif ($ts_out <= $ts_in) {
        $error_msg = 'Ngày trả phòng phải sau ngày nhận phòng.';
    } elseif ($ts_in < strtotime('today')) {
        $error_msg = 'Ngày nhận phòng không được là ngày trong quá khứ.';
    }

    if (!$room_id || !$payment_method) {
        $error_msg = $error_msg ?? 'Vui lòng chọn phòng và phương thức thanh toán.';
    }

    // -- Lấy tên phòng để lưu vào payments --
    $room_name_val = '';
    if (!isset($error_msg)) {
    foreach ($rooms_data as $rm) {
        if ($rm['id'] == $room_id) {
            $nights         = intval(($ts_out - $ts_in) / 86400);
            $nights         = max(1, $nights);
            $original_price = $rm['price'] * $nights;
            $room_name_val  = $rm['room_name'];
            break;
        }
    }
    }

    if (!isset($error_msg)) {

    if ($has_new_discount && $new_user_discount > 0) {
        $discount_amount = $original_price * $new_user_discount / 100;
        $total_price     = $original_price - $discount_amount;
    } else {
        $total_price = $original_price;
    }

    // Voucher
    $voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));
    $voucher_disc = 0;
    $voucher_row  = null;
    if ($voucher_code) {
        $vs = $conn->prepare("
            SELECT * FROM vouchers
            WHERE code = ? AND is_active = 1
            AND (expires_at IS NULL OR expires_at >= CURDATE())
            AND used_count < max_uses
        ");
        $vs->bind_param("s", $voucher_code);
        $vs->execute();
        $voucher_row = $vs->get_result()->fetch_assoc();
        if ($voucher_row && $total_price >= $voucher_row['min_order']) {
            $voucher_disc = ($voucher_row['type'] === 'percent')
                ? round($total_price * $voucher_row['value'] / 100)
                : min((float)$voucher_row['value'], $total_price);
            $total_price     -= $voucher_disc;
            $discount_amount += $voucher_disc;
        }
    }

    // Chống overbooking: lock room row, kiểm tra còn phòng trước khi insert
    $conn->begin_transaction();
    $lock_s = $conn->prepare("SELECT r.quantity, h.commission_rate FROM rooms r JOIN hotels h ON r.hotel_id = h.id WHERE r.id = ? FOR UPDATE");
    $lock_s->bind_param("i", $room_id);
    $lock_s->execute();
    $room_lock = $lock_s->get_result()->fetch_assoc();

    if (!$room_lock || (int)$room_lock['quantity'] < 1) {
        $conn->rollback();
        $error_msg = 'Phòng này vừa hết chỗ. Vui lòng chọn phòng khác hoặc thay đổi ngày lưu trú.';
    } else {
        // Xác định luồng thu tiền
        $payment_flow = ($payment_method === 'hotel') ? 'hotel_collect' : 'platform_collect';

        $stmt = $conn->prepare("INSERT INTO bookings (order_code, user_id, room_id, full_name, email, phone, check_in, check_out, total_price, payment_method, payment_flow, note, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt->bind_param("siisssssdssss", $order_code, $user_id, $room_id, $full_name, $email, $phone, $checkin, $checkout, $total_price, $payment_method, $payment_flow, $note);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno == 1062) {
                $order_code = 'ORD' . time() . rand(1000, 9999);
                $stmt->bind_param("siisssssdssss", $order_code, $user_id, $room_id, $full_name, $email, $phone, $checkin, $checkout, $total_price, $payment_method, $payment_flow, $note);
                $stmt->execute();
            } else {
                $conn->rollback();
                throw $e;
            }
        }
        $booking_id = $conn->insert_id;

        // Snapshot commission_rate: hotel_collect = 0 (platform không thu)
        $snap_rate = ($payment_flow === 'hotel_collect') ? 0.0 : (float)$room_lock['commission_rate'];
        $conn->query("UPDATE bookings SET commission_rate = $snap_rate WHERE id = $booking_id");

        // Trừ 1 phòng (AND quantity > 0 đảm bảo không âm khi concurrent)
        $conn->query("UPDATE rooms SET quantity = quantity - 1 WHERE id = $room_id AND quantity > 0");
        $conn->commit();

    $method_label_map = [
        'bank'  => 'Chuyển khoản ngân hàng',
        'momo'  => 'Ví MoMo',
        'vnpay' => 'VNPay',
        'hotel' => 'Thanh toán tại khách sạn',
        'card'  => 'Thẻ quốc tế',
    ];
    $payment_method_label = $method_label_map[$payment_method] ?? $payment_method;

    // INSERT payments — chỉ với platform_collect (hotel_collect không qua payment gateway)
    if ($payment_flow === 'platform_collect') {
        $stmt2 = $conn->prepare("INSERT INTO payments (booking_id, hotel_id, hotel_name, room_name, payment_method, amount, full_name, email, phone, payment_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt2->bind_param("iisssdsss",
            $booking_id,
            $hotel_id,
            $hotel['name'],
            $room_name_val,
            $payment_method_label,
            $total_price,
            $full_name,
            $email,
            $phone
        );
        $stmt2->execute();
    }

    if ($has_new_discount) {
        $_SESSION['new_user_discount_used'] = true;
    }

    // Tăng used_count voucher
    if ($voucher_row) {
        $conn->query("UPDATE vouchers SET used_count = used_count + 1 WHERE id = " . (int)$voucher_row['id']);
    }

    // ── Redirect sang cổng thanh toán thật ────────────────────────────
    if ($payment_method === 'vnpay') {
        header('Location: ' . vnpay_build_url($order_code, $total_price));
        exit();
    }
    if ($payment_method === 'momo') {
        $momo = momo_create_payment($order_code, $total_price, $booking_id);
        if ($momo['success']) {
            header('Location: ' . $momo['pay_url']);
            exit();
        }
        $error_msg = 'MoMo: ' . $momo['message'];
    }

    // Gửi email xác nhận đặt phòng cho thanh toán không qua gateway
    send_booking_email($email, $full_name, [
        'order_code'     => $order_code,
        'hotel_name'     => $hotel['name'],
        'room_name'      => $room_name_val,
        'checkin'        => $checkin,
        'checkout'       => $checkout,
        'payment_method' => $payment_method,
        'total_price'    => $total_price,
        'full_name'      => $full_name,
    ]);

    $qr_content = urlencode("StayGo | Ma don: $order_code | Ho ten: $full_name | Email: $email | SDT: $phone | Tong tien: " . number_format($total_price, 0, ',', '.') . "d | PT: $payment_method");
    $qr_data = [
        'content'         => $qr_content,
        'full_name'       => $full_name,
        'email'           => $email,
        'phone'           => $phone,
        'order_code'      => $order_code,
        'method'          => $payment_method,
        'total'           => $total_price,
        'original_price'  => $original_price,
        'discount_amount' => $discount_amount,
        'discount_pct'    => $new_user_discount,
        'voucher_code'    => $voucher_code,
        'voucher_disc'    => $voucher_disc,
        'checkin'         => $checkin,
        'checkout'        => $checkout,
    ];
    } // end else (room available)
    } // end if (!isset($error_msg)) — date/room/method validation passed
}
$is_hotel_pay = $qr_data && $qr_data['method'] === 'hotel';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pay-page">

<?php if (!empty($error_msg)): ?>
<div style="max-width:600px;margin:30px auto;padding:16px 20px;background:#fff5f5;border:1px solid #feb2b2;border-radius:12px;color:#c53030;font-size:14px;text-align:center">
    ⚠️ <?= htmlspecialchars($error_msg) ?>
    <br><br><a href="hotels.php" style="color:#c53030;font-weight:600">← Quay lại chọn phòng</a>
</div>
<?php endif; ?>

<?php if (!$qr_data): ?>
<div class="pay-wrap">
    <div class="pay-head">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
        Thanh toán đặt phòng
    </div>
    <div class="pay-hotel-name">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
        <?php echo htmlspecialchars($hotel['name']); ?>
    </div>

    <?php if ($has_new_discount): ?>
    <div class="discount-banner">
        🎉 <strong>Chúc mừng!</strong> Bạn được <strong>giảm <?= $new_user_discount ?>%</strong> cho lần đặt phòng đầu tiên!
        <span class="discount-badge-tag">-<?= $new_user_discount ?>%</span>
    </div>
    <?php elseif (!isset($_SESSION['user_id'])): ?>
    <div class="login-hint-banner">
        <a href="/auth/register.php"><strong>Tạo tài khoản mới</strong></a>
        để nhận <strong>giảm 10%</strong> cho lần đặt đầu tiên!
    </div>
    <?php endif; ?>

    <form method="POST" id="payForm">
    <?= csrf_field() ?>
        <input type="hidden" name="hotel_id" value="<?php echo $hotel_id; ?>">

        <!-- BƯỚC 1: Thông tin khách hàng -->
        <div class="pay-section">
            <div class="section-label"><span class="step-badge">1</span> Thông tin khách hàng</div>
            <div class="field-grid">
                <div class="field-wrap">
                    <label>Họ và tên <span class="req">*</span></label>
                    <input type="text" name="full_name" placeholder="Họ và tên" required>
                </div>
                <div class="field-wrap">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" placeholder="example@gmail.com" required>
                </div>
                <div class="field-wrap">
                    <label>Số điện thoại <span class="req">*</span></label>
                    <input type="tel" name="phone" placeholder="0xxx xxx xxx" required>
                </div>
                <div class="field-wrap">
                    <label>Mã đơn hàng</label>
                    <input type="text" name="order_code" value="ORD<?php echo time() . substr((string)(int)(microtime(true)*1000), -3) . rand(10,99); ?>" readonly class="readonly-field">
                </div>
            </div>
            <div class="field-wrap" style="margin-top:0">
                <label>Ghi chú thêm</label>
                <textarea name="note" placeholder="Yêu cầu đặc biệt (nếu có)..." rows="2"></textarea>
            </div>
        </div>

        <!-- BƯỚC 2: Thông tin đặt phòng -->
        <div class="pay-section">
            <div class="section-label"><span class="step-badge">2</span> Thông tin đặt phòng</div>
            <div class="field-grid">
                <div class="field-wrap">
                    <label>Ngày nhận phòng <span class="req">*</span></label>
                    <input type="date" name="checkin" id="checkin" required
                        value="<?= htmlspecialchars($prefill_checkin) ?>"
                        min="" max="" onchange="updateCheckout()">
                </div>
                <div class="field-wrap">
                    <label>Ngày trả phòng <span class="req">*</span></label>
                    <input type="date" name="checkout" id="checkout" required
                        value="<?= htmlspecialchars($prefill_checkout) ?>"
                        min="" max="">
                </div>
            </div>
            <div class="field-wrap">
                <label>Chọn phòng <span class="req">*</span></label>
                <select name="room_id" id="roomSelect" required onchange="calcPrice()">
                    <option value="">-- Chọn phòng --</option>
                    <?php foreach($rooms_data as $rm): ?>
                        <?php if($rm['quantity'] > 0): ?>
                            <option value="<?php echo $rm['id']; ?>" data-price="<?php echo $rm['price']; ?>"
                                <?= ($prefill_room_id && $prefill_room_id == $rm['id']) ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($rm['room_name']); ?> – <?php echo number_format($rm['price'],0,',','.'); ?> VNĐ/đêm
                            </option>
                        <?php else: ?>
                            <option disabled><?php echo htmlspecialchars($rm['room_name']); ?> (Hết phòng)</option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="priceBox" class="price-box" style="display:none">
                <div class="price-row"><span>Số đêm:</span><strong id="nightCount">–</strong></div>
                <div class="price-row"><span>Giá/đêm:</span><strong id="pricePerNight">–</strong></div>
                <?php if ($has_new_discount): ?>
                <div class="price-row original"><span>Giá gốc:</span><strong id="originalPrice" class="line-through-price">–</strong></div>
                <div class="price-row discount-row"><span>Giảm <?= $new_user_discount ?>% (tài khoản mới):</span><strong id="discountAmount" class="discount-text">–</strong></div>
                <?php endif; ?>
                <div id="voucherDiscountRow" class="price-row discount-row" style="display:none">
                    <span>Voucher (<span id="voucherCodeLabel"></span>):</span>
                    <strong id="voucherDiscountAmt" class="discount-text">–</strong>
                </div>
                <div class="price-row total"><span>Tổng tiền:</span><strong id="totalPrice">–</strong></div>
            </div>

            <!-- Voucher -->
            <div id="voucherWrap" class="voucher-wrap" style="display:none">
                <label>Mã giảm giá <span style="font-weight:400;color:#718096">(tùy chọn)</span></label>
                <div class="voucher-row">
                    <input type="text" id="voucherInput" placeholder="Nhập mã voucher..." maxlength="30"
                           style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
                    <button type="button" id="voucherApplyBtn" onclick="applyVoucher()" class="btn-apply-voucher">Áp dụng</button>
                </div>
                <div id="voucherMsg" class="voucher-msg" style="display:none"></div>
                <input type="hidden" name="voucher_code" id="voucherCodeHidden" value="">
            </div>
        </div>

        <!-- BƯỚC 3: Phương thức thanh toán -->
        <div class="pay-section">
            <div class="section-label"><span class="step-badge">3</span> Phương thức thanh toán</div>
            <div class="method-grid">

                <!-- Chuyển khoản ngân hàng -->
                <label class="method-card" id="m_bank" onclick="showMethodDetail('bank')">
                    <input type="radio" name="payment_method" value="bank" required onchange="selectMethod(this)">
                    <div class="method-logo bank-logo">
                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#1a5fa8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>
                    </div>
                    <div class="method-info">
                        <div class="method-name">Chuyển khoản ngân hàng</div>
                        <div class="method-desc">Vietinbank · Vietcombank · MB Bank...</div>
                    </div>
                    <span class="method-check">✓</span>
                </label>

                <!-- Ví MoMo -->
                <label class="method-card" id="m_momo" onclick="showMethodDetail('momo')">
                    <input type="radio" name="payment_method" value="momo" onchange="selectMethod(this)">
                    <div class="method-logo">
                        <img src="/assets/images/momo.png" alt="MoMo" style="width:46px;height:46px;object-fit:contain;border-radius:10px;">
                    </div>
                    <div class="method-info">
                        <div class="method-name">Ví MoMo</div>
                        <div class="method-desc">Thanh toán qua ví điện tử MoMo</div>
                    </div>
                    <span class="method-check">✓</span>
                </label>

                <!-- VNPay -->
                <label class="method-card" id="m_vnpay" onclick="showMethodDetail('vnpay')">
                    <input type="radio" name="payment_method" value="vnpay" onchange="selectMethod(this)">
                    <div class="method-logo">
                        <img src="/assets/images/vnpay.png" alt="VNPay" style="width:46px;height:46px;object-fit:contain;border-radius:8px;">
                    </div>
                    <div class="method-info">
                        <div class="method-name">VNPay</div>
                        <div class="method-desc">Cổng thanh toán · QR & thẻ ATM nội địa</div>
                    </div>
                    <span class="method-check">✓</span>
                </label>

                <!-- Thanh toán tại khách sạn -->
                <label class="method-card" id="m_hotel" onclick="showMethodDetail('hotel')">
                    <input type="radio" name="payment_method" value="hotel" onchange="selectMethod(this)">
                    <div class="method-logo hotel-logo">
                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#276749" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                    </div>
                    <div class="method-info">
                        <div class="method-name">Thanh toán tại khách sạn</div>
                        <div class="method-desc">Trả tiền mặt khi nhận phòng</div>
                    </div>
                    <span class="method-check">✓</span>
                </label>

                <!-- Thẻ quốc tế -->
                <label class="method-card" id="m_card" onclick="showMethodDetail('card')">
                    <input type="radio" name="payment_method" value="card" onchange="selectMethod(this)">
                    <div class="method-logo card-logo">
                        <img src="/assets/images/thanh_toanqt.png" alt="Thẻ quốc tế" style="width:46px;height:46px;object-fit:contain;border-radius:8px;">
                    </div>
                    <div class="method-info">
                        <div class="method-name">Thẻ quốc tế</div>
                        <div class="method-desc">Visa · Mastercard · JCB · American Express</div>
                    </div>
                    <span class="method-check">✓</span>
                </label>

            </div>

            <!-- PANEL CHI TIẾT TỪNG PHƯƠNG THỨC -->

            <!-- PANEL: Chuyển khoản ngân hàng (VietQR động) -->
            <?php
            $vqr_bank    = BANK_ID;           // ICB
            $vqr_stk     = BANK_ACCOUNT_NO;   // 107645394761
            $vqr_name    = BANK_ACCOUNT_NAME; // LE VAN HUY
            $vqr_bank_name = BANK_NAME;       // VietinBank
            $vqr_branch  = BANK_BRANCH;       // Kon Tum
            ?>
            <div id="detail_bank" class="method-detail-panel" style="display:none">
                <div class="panel-header bank-panel-header">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18"/></svg>
                    Chuyển khoản ngân hàng · <?= htmlspecialchars($vqr_bank_name) ?>
                </div>
                <div class="bank-detail-layout">
                    <div class="bank-info-col">
                        <div class="bank-info-row">
                            <span class="bank-info-label">Ngân hàng</span>
                            <span class="bank-info-val bank-name-tag"><?= htmlspecialchars($vqr_bank_name) ?></span>
                        </div>
                        <div class="bank-info-row">
                            <span class="bank-info-label">Số tài khoản</span>
                            <div class="bank-stk-wrap">
                                <span class="bank-stk" id="stkText"><?= htmlspecialchars($vqr_stk) ?></span>
                                <button type="button" class="btn-copy" onclick="copySTK()">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg>
                                    <span id="copyBtnText">Sao chép</span>
                                </button>
                            </div>
                        </div>
                        <div class="bank-info-row">
                            <span class="bank-info-label">Chủ tài khoản</span>
                            <span class="bank-info-val"><?= htmlspecialchars($vqr_name) ?></span>
                        </div>
                        <div class="bank-info-row">
                            <span class="bank-info-label">Chi nhánh</span>
                            <span class="bank-info-val"><?= htmlspecialchars($vqr_branch) ?></span>
                        </div>
                        <div class="bank-info-row" id="bankAmountRow" style="display:none">
                            <span class="bank-info-label">Số tiền</span>
                            <span class="bank-info-val" id="bankAmountDisplay" style="color:#276749;font-weight:800">—</span>
                        </div>
                        <div class="bank-transfer-note">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#744210" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                            Nội dung CK bắt buộc ghi đúng mã đơn:<br>
                            <strong id="bankNoteText">ORD... + Họ tên</strong><br>
                            <small style="color:#a0aec0">Hệ thống tự xác nhận sau khi nhận được tiền</small>
                        </div>
                    </div>
                    <div class="bank-qr-col">
                        <div class="qr-placeholder-wrap">
                            <div class="qr-placeholder-box" id="bankQrBox">
                                <!-- VietQR động — cập nhật khi chọn phòng + ngày -->
                                <img id="vietqrImg"
                                    src="https://img.vietqr.io/image/<?= $vqr_bank ?>-<?= $vqr_stk ?>-compact2.png?addInfo=StayGo&accountName=<?= urlencode($vqr_name) ?>"
                                    alt="VietQR"
                                    style="width:180px;height:180px;object-fit:contain;border-radius:8px">
                            </div>
                        </div>
                        <p class="qr-scan-hint">Quét bằng app ngân hàng bất kỳ</p>
                        <p style="font-size:11px;color:#a0aec0;text-align:center;margin-top:2px">QR cập nhật tự động theo số tiền</p>
                    </div>
                </div>
            </div>

            <!-- PANEL: Ví MoMo -->
            <div id="detail_momo" class="method-detail-panel" style="display:none">
                <div class="panel-header momo-panel-header">
                    <img src="/assets/images/momo.png" alt="MoMo" style="width:20px;height:20px;border-radius:5px;object-fit:contain;">
                    Thanh toán qua Ví MoMo
                </div>
                <div class="momo-detail-layout">
                    <div class="momo-qr-col">
                        <div class="qr-placeholder-box momo-qr-box">
                            <img src="/assets/images/qr_momo.jpg"
                                alt="QR MoMo"
                                style="width:160px;height:160px;object-fit:contain;border-radius:12px;">
                        </div>
                        <p class="qr-scan-hint">Mở app MoMo → Quét mã QR</p>
                    </div>
                    <div class="momo-info-col">
                        <div class="momo-phone-wrap">
                            <div class="momo-phone-label">Số điện thoại MoMo</div>
                            <div class="momo-phone-val">
                                <span id="momoPhone">037 384 8395</span>
                                <button type="button" class="btn-copy btn-copy-momo" onclick="copyMoMo()">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg>
                                    <span id="momoCopyText">Sao chép</span>
                                </button>
                            </div>
                        </div>
                        <div class="momo-steps">
                            <div class="momo-step"><span class="step-num">1</span> Mở ứng dụng MoMo trên điện thoại</div>
                            <div class="momo-step"><span class="step-num">2</span> Chọn <strong>Quét mã QR</strong> hoặc <strong>Chuyển tiền</strong></div>
                            <div class="momo-step"><span class="step-num">3</span> Nhập số tiền và xác nhận</div>
                            <div class="momo-step"><span class="step-num">4</span> Ghi nội dung: <code>mã đơn hàng + họ tên</code></div>
                        </div>
                        <div class="momo-note-tag">
                            ⏱ Sau khi chuyển thành công, đơn hàng sẽ được xác nhận trong vòng 5–10 phút.
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL: VNPay -->
            <div id="detail_vnpay" class="method-detail-panel" style="display:none">
                <div class="panel-header vnpay-panel-header">
                    <img src="/assets/images/vnpay.png" alt="VNPay" style="width:20px;height:20px;border-radius:4px;object-fit:contain;">
                    Cổng thanh toán VNPay
                </div>
                <div class="vnpay-layout">
                    <div class="vnpay-explain">
                        <div class="vnpay-logo-row">
                            <img src="/assets/images/vnpay.png" alt="VNPay" style="height:36px;object-fit:contain;">
                            <span class="vnpay-badge">Cổng thanh toán bảo mật</span>
                        </div>
                        <p class="vnpay-desc">
                            <strong>VNPay</strong> là cổng thanh toán điện tử uy tín hàng đầu Việt Nam.
                            Khi xác nhận đặt phòng, bạn sẽ được chuyển hướng đến trang thanh toán VNPay để hoàn tất giao dịch an toàn.
                        </p>
                        <div class="vnpay-support-list">
                            <div class="vnpay-support-item">
                                <span class="vnpay-icon">📱</span>
                                <div>
                                    <strong>VNPay QR</strong>
                                    <p>Quét QR bằng app ngân hàng bất kỳ</p>
                                </div>
                            </div>
                            <div class="vnpay-support-item">
                                <span class="vnpay-icon">💳</span>
                                <div>
                                    <strong>Thẻ ATM nội địa</strong>
                                    <p>Tất cả thẻ ATM có đăng ký Internet Banking</p>
                                </div>
                            </div>
                            <div class="vnpay-support-item">
                                <span class="vnpay-icon">🏦</span>
                                <div>
                                    <strong>40+ ngân hàng hỗ trợ</strong>
                                    <p>Vietcombank, Techcombank, MB, BIDV, Agribank...</p>
                                </div>
                            </div>
                        </div>
                        <div class="vnpay-secure-row">
                            <span>🔒 Giao dịch được mã hóa SSL 256-bit</span>
                            <span>✅ Chứng nhận PCI DSS</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL: Thanh toán tại khách sạn -->
            <div id="detail_hotel" class="method-detail-panel" style="display:none">
                <div class="panel-header hotel-panel-header">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                    Thanh toán tiền mặt tại khách sạn
                </div>
                <div class="hotel-pay-layout">
                    <div class="hotel-pay-steps">
                        <div class="hotel-step-item">
                            <div class="hotel-step-icon" style="background:#ebf4ff;color:#1e73be">📋</div>
                            <div>
                                <strong>Xác nhận đặt phòng</strong>
                                <p>Hoàn tất form và nhấn "Xác nhận đặt phòng". Đơn hàng sẽ ở trạng thái <em>chờ xác nhận</em>.</p>
                            </div>
                        </div>
                        <div class="hotel-step-item">
                            <div class="hotel-step-icon" style="background:#f0fff4;color:#276749">📧</div>
                            <div>
                                <strong>Nhận email xác nhận</strong>
                                <p>Chúng tôi sẽ gửi email xác nhận đặt phòng kèm mã đơn hàng đến địa chỉ của bạn.</p>
                            </div>
                        </div>
                        <div class="hotel-step-item">
                            <div class="hotel-step-icon" style="background:#fff5f0;color:#e05c1a">💵</div>
                            <div>
                                <strong>Thanh toán khi nhận phòng</strong>
                                <p>Xuất trình mã đơn hàng tại lễ tân và thanh toán bằng <strong>tiền mặt</strong> hoặc <strong>thẻ ngân hàng</strong> tại chỗ.</p>
                            </div>
                        </div>
                    </div>
                    <div class="hotel-pay-note">
                        <span>⚠️</span>
                        <div>
                            Vui lòng đến nhận phòng đúng ngày đã đặt. Nếu cần hủy hoặc thay đổi, hãy liên hệ chúng tôi trước ít nhất <strong>24 giờ</strong>.
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL: Thẻ quốc tế -->
            <div id="detail_card" class="method-detail-panel" style="display:none">
                <div class="panel-header card-panel-header">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                    Thanh toán bằng thẻ quốc tế
                </div>

                <div id="cardStep1" class="card-step">
                    <p class="card-step-title">Chọn loại thẻ của bạn:</p>
                    <div class="card-type-grid">
                        <div class="card-type-item" onclick="selectCardType('visa')">
                            <svg viewBox="0 0 80 26" height="28" xmlns="http://www.w3.org/2000/svg">
                                <rect width="80" height="26" rx="3" fill="#1434CB"/>
                                <text x="40" y="19" text-anchor="middle" font-family="Arial,sans-serif" font-size="16" font-weight="bold" font-style="italic" fill="white" letter-spacing="1">VISA</text>
                            </svg>
                        </div>
                        <div class="card-type-item" onclick="selectCardType('mastercard')">
                            <svg viewBox="0 0 60 38" height="32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="22" cy="19" r="14" fill="#EB001B"/>
                                <circle cx="38" cy="19" r="14" fill="#F79E1B"/>
                                <path d="M30 8.2a14 14 0 0 1 0 21.6A14 14 0 0 1 30 8.2z" fill="#FF5F00"/>
                            </svg>
                        </div>
                        <div class="card-type-item" onclick="selectCardType('jcb')">
                            <svg viewBox="0 0 60 38" height="32" xmlns="http://www.w3.org/2000/svg">
                                <rect width="60" height="38" rx="4" fill="white"/>
                                <rect x="4" y="4" width="16" height="30" rx="3" fill="#003087"/>
                                <rect x="22" y="4" width="16" height="30" rx="3" fill="#CC0000"/>
                                <rect x="40" y="4" width="16" height="30" rx="3" fill="#009F6B"/>
                                <text x="12" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-size="9" font-weight="bold" fill="white">J</text>
                                <text x="30" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-size="9" font-weight="bold" fill="white">C</text>
                                <text x="48" y="23" text-anchor="middle" font-family="Arial,sans-serif" font-size="9" font-weight="bold" fill="white">B</text>
                            </svg>
                        </div>
                        <div class="card-type-item" onclick="selectCardType('amex')">
                            <svg viewBox="0 0 80 26" height="28" xmlns="http://www.w3.org/2000/svg">
                                <rect width="80" height="26" rx="3" fill="#007BC1"/>
                                <text x="40" y="18" text-anchor="middle" font-family="Arial,sans-serif" font-size="9" font-weight="bold" fill="white" letter-spacing="0.5">AMERICAN</text>
                                <text x="40" y="27" text-anchor="middle" font-family="Arial,sans-serif" font-size="6" font-weight="bold" fill="white" letter-spacing="2">EXPRESS</text>
                            </svg>
                        </div>
                    </div>
                </div>

                <div id="cardStep2" class="card-step" style="display:none">
                    <div class="card-preview" id="cardPreview">
                        <div class="card-preview-top">
                            <div class="card-chip"></div>
                            <div id="cardTypeLogoPreview" class="card-type-logo-preview"></div>
                        </div>
                        <div class="card-number-preview" id="cardNumPreview">•••• •••• •••• ••••</div>
                        <div class="card-preview-bottom">
                            <div>
                                <div class="card-preview-label">Chủ thẻ</div>
                                <div class="card-holder-preview" id="cardHolderPreview">TÊN CHỦ THẺ</div>
                            </div>
                            <div>
                                <div class="card-preview-label">Hết hạn</div>
                                <div class="card-expire-preview" id="cardExpirePreview">MM/YY</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-form-fields">
                        <div class="field-wrap">
                            <label>Số thẻ <span class="req">*</span></label>
                            <div class="card-input-wrap">
                                <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19"
                                    oninput="formatCardNumber(this)" class="card-field-input">
                                <span id="cardTypeIcon" class="card-input-icon"></span>
                            </div>
                        </div>
                        <div class="field-wrap">
                            <label>Tên chủ thẻ <span class="req">*</span></label>
                            <input type="text" id="cardHolder" placeholder="VD: NGUYEN VAN A" style="text-transform:uppercase"
                                oninput="document.getElementById('cardHolderPreview').textContent = this.value.toUpperCase() || 'TÊN CHỦ THẺ'">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field-wrap">
                                <label>Ngày hết hạn <span class="req">*</span></label>
                                <input type="text" id="cardExpiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this)">
                            </div>
                            <div class="field-wrap">
                                <label>CVV <span class="req">*</span></label>
                                <div class="cvv-wrap">
                                    <input type="password" id="cardCVV" placeholder="•••" maxlength="4" class="card-field-input">
                                    <div class="cvv-tooltip" title="3–4 số ở mặt sau thẻ">
                                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#a0aec0" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-secure-note">
                            🔒 Thông tin thẻ được mã hóa an toàn. Chúng tôi không lưu trữ số thẻ của bạn.
                        </div>
                        <div style="display:flex;gap:10px;margin-top:8px">
                            <button type="button" class="btn-card-back" onclick="backToCardTypes()">← Đổi loại thẻ</button>
                            <button type="button" class="btn-card-link" onclick="linkCard()">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
                                Liên kết thẻ
                            </button>
                        </div>
                    </div>
                </div>

                <div id="cardStep3" class="card-step" style="display:none">
                    <div class="card-linked-success">
                        <div class="linked-check">✓</div>
                        <div class="linked-title">Thẻ đã được liên kết!</div>
                        <div class="linked-info" id="linkedCardInfo"></div>
                        <div class="linked-desc">Bạn có thể tiến hành xác nhận thanh toán.</div>
                        <button type="button" class="btn-change-card" onclick="backToCardTypes()">Đổi thẻ khác</button>
                    </div>
                </div>

            </div>
            <!-- end detail_card -->

        </div><!-- end pay-section -->

        <button type="submit" class="btn-confirm" id="btnConfirm">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Xác nhận thanh toán
        </button>
    </form>
</div>

<?php else: ?>
<?php
$method_label = [
    'bank'  => 'Chuyển khoản ngân hàng',
    'momo'  => 'Ví MoMo',
    'vnpay' => 'VNPay',
    'hotel' => 'Thanh toán tại khách sạn',
    'card'  => 'Thẻ quốc tế',
];
$nights      = max(1, intval((strtotime($qr_data['checkout']) - strtotime($qr_data['checkin'])) / 86400));
$qr_url      = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . $qr_data['content'];
$expire_secs = 15 * 60;
?>

<?php if ($is_hotel_pay): ?>
<!-- LUỒNG: THANH TOÁN TẠI KHÁCH SẠN -->
<div class="hotel-confirm-wrap">
    <div class="hotel-confirm-card">
        <div class="hotel-confirm-icon">
            <svg viewBox="0 0 52 52" width="72" height="72">
                <circle cx="26" cy="26" r="25" fill="none" stroke="#276749" stroke-width="2"
                    style="stroke-dasharray:166;stroke-dashoffset:166;animation:stroke-anim .6s cubic-bezier(.65,0,.45,1) forwards;"/>
                <path fill="none" stroke="#276749" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                    d="M14.1 27.2l7.1 7.2 16.7-16.8"
                    style="stroke-dasharray:48;stroke-dashoffset:48;animation:stroke-anim .4s cubic-bezier(.65,0,.45,1) .55s forwards;"/>
            </svg>
        </div>
        <div class="hotel-confirm-title">Đặt phòng thành công!</div>
        <div class="hotel-confirm-sub">Chúng tôi đã nhận được yêu cầu đặt phòng của bạn.</div>
        <div class="hotel-confirm-order">
            <div class="hco-label">Mã đơn hàng của bạn</div>
            <div class="hco-code"><?php echo htmlspecialchars($qr_data['order_code']); ?></div>
            <button type="button" onclick="copyOrderCode()" class="hco-copy-btn" id="hcoCopyBtn">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg>
                Sao chép mã
            </button>
        </div>
        <div class="hotel-confirm-details">
            <?php if ($qr_data['discount_pct'] > 0): ?>
            <div class="hotel-confirm-discount">
                🎉 Đã áp dụng giảm <strong><?= $qr_data['discount_pct'] ?>%</strong> tài khoản mới
                · Tiết kiệm <strong><?= number_format($qr_data['discount_amount'], 0, ',', '.') ?>đ</strong>
            </div>
            <?php endif; ?>
            <div class="hcd-grid">
                <div class="hcd-item"><span class="hcd-icon">👤</span><div><div class="hcd-label">Họ và tên</div><div class="hcd-val"><?php echo htmlspecialchars($qr_data['full_name']); ?></div></div></div>
                <div class="hcd-item"><span class="hcd-icon">📧</span><div><div class="hcd-label">Email</div><div class="hcd-val"><?php echo htmlspecialchars($qr_data['email']); ?></div></div></div>
                <div class="hcd-item"><span class="hcd-icon">📞</span><div><div class="hcd-label">Số điện thoại</div><div class="hcd-val"><?php echo htmlspecialchars($qr_data['phone']); ?></div></div></div>
                <div class="hcd-item"><span class="hcd-icon">📅</span><div><div class="hcd-label">Thời gian lưu trú</div><div class="hcd-val"><?php echo date('d/m/Y', strtotime($qr_data['checkin'])); ?> → <?php echo date('d/m/Y', strtotime($qr_data['checkout'])); ?> <span style="color:#a0aec0">(<?php echo $nights; ?> đêm)</span></div></div></div>
                <div class="hcd-item"><span class="hcd-icon">💳</span><div><div class="hcd-label">Phương thức</div><div class="hcd-val">Thanh toán tại khách sạn</div></div></div>
                <div class="hcd-item"><span class="hcd-icon">💰</span><div>
                    <div class="hcd-label">Tổng thanh toán</div>
                    <div class="hcd-val hcd-total">
                        <?php if ($qr_data['discount_pct'] > 0): ?>
                        <span style="font-size:12px;color:#a0aec0;text-decoration:line-through;font-weight:400;"><?= number_format($qr_data['original_price'], 0, ',', '.') ?> VNĐ</span><br>
                        <?php endif; ?>
                        <?php echo number_format($qr_data['total'], 0, ',', '.'); ?> VNĐ
                    </div>
                </div></div>
            </div>
        </div>
        <div class="hotel-status-badge">
            <span class="hotel-status-dot"></span>
            Đơn hàng đang chờ xác nhận · Vui lòng thanh toán khi nhận phòng
        </div>
        <div class="hotel-next-steps">
            <div class="hns-title">Các bước tiếp theo</div>
            <div class="hns-item"><div class="hns-num">1</div><div>Kiểm tra email xác nhận đặt phòng gửi đến <strong><?php echo htmlspecialchars($qr_data['email']); ?></strong></div></div>
            <div class="hns-item"><div class="hns-num">2</div><div>Đến khách sạn vào ngày <strong><?php echo date('d/m/Y', strtotime($qr_data['checkin'])); ?></strong> và xuất trình mã đơn hàng tại lễ tân</div></div>
            <div class="hns-item"><div class="hns-num">3</div><div>Thanh toán <strong><?php echo number_format($qr_data['total'], 0, ',', '.'); ?> VNĐ</strong> bằng tiền mặt hoặc thẻ ngân hàng tại chỗ</div></div>
        </div>
        <div class="hotel-confirm-actions">
            <a href="hotels.php" class="btn-hc-primary">← Về trang khách sạn</a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="my_bookings.php" class="btn-hc-secondary">Xem đơn hàng của tôi</a>
            <?php else: ?>
            <a href="booking_lookup.php" class="btn-hc-secondary">Tra cứu đơn hàng</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyOrderCode() {
    navigator.clipboard.writeText('<?php echo htmlspecialchars($qr_data['order_code']); ?>').then(function() {
        var btn = document.getElementById('hcoCopyBtn');
        btn.innerHTML = '✓ Đã chép!';
        btn.style.background = '#276749';
        btn.style.color = 'white';
        setTimeout(function() {
            btn.innerHTML = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg> Sao chép mã';
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}
</script>

<?php else: ?>
<!-- LUỒNG: THANH TOÁN QR / ONLINE -->

<!-- OVERLAY: THANH TOÁN THÀNH CÔNG -->
<div id="successOverlay" class="pay-overlay" style="display:none">
    <div class="overlay-modal">
        <div class="checkmark-wrap">
            <svg class="checkmark-svg" viewBox="0 0 52 52">
                <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                <path   class="checkmark-path"   fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        <div class="overlay-title" style="color:#276749">Thanh toán thành công!</div>
        <div class="overlay-sub">Đặt phòng của bạn đã được xác nhận.</div>
        <div class="overlay-order-code">Mã đơn: <strong id="successOrderCode"></strong></div>
        <div class="overlay-redirect">Tự động chuyển hướng sau <span id="redirectCount">5</span> giây...</div>
        <a href="hotels.php" class="btn-overlay-action" style="background:linear-gradient(135deg,#276749,#38a169)">← Về trang khách sạn</a>
    </div>
</div>

<!-- OVERLAY: HẾT THỜI GIAN -->
<div id="expiredOverlay" class="pay-overlay" style="display:none">
    <div class="overlay-modal">
        <div style="font-size:54px;margin-bottom:12px">⏰</div>
        <div class="overlay-title" style="color:#c53030">Phiên thanh toán hết hạn</div>
        <div class="overlay-sub">Vui lòng đặt phòng lại để tiếp tục.</div>
        <a href="hotels.php" class="btn-overlay-action" style="background:linear-gradient(135deg,#e05c1a,#ff7043);margin-top:20px">Đặt phòng lại</a>
    </div>
</div>

<div class="qr-wrap">
    <span id="bookingIdData" data-id="<?php echo intval($booking_id); ?>" style="display:none"></span>

    <div class="qr-status-bar">
        <div class="status-indicator" id="statusIndicator"></div>
        <span id="statusText">Đang chờ thanh toán...</span>
        <div class="countdown-pill" id="countdownPill">
            ⏱ Còn lại: <span id="countdown">15:00</span>
        </div>
    </div>

    <div class="qr-layout">
        <div class="qr-left">
            <div class="qr-title">Quét mã để thanh toán</div>
            <div class="qr-method-tag"><?php echo $method_label[$qr_data['method']] ?? $qr_data['method']; ?></div>
            <div class="qr-img-wrap">
                <div class="qr-frame-wrap">
                    <img src="<?php echo $qr_url; ?>" alt="QR Code" id="qrImg">
                    <div class="qr-expired-cover" id="qrExpiredCover" style="display:none"><span>⏰ Hết hạn</span></div>
                </div>
                <div class="qr-label">Quét bằng ứng dụng ngân hàng / ví điện tử</div>
            </div>
            <div class="time-progress-wrap">
                <div class="time-progress-bar" id="timeProgressBar"></div>
            </div>
            <div class="qr-amount">
                <?php echo number_format($qr_data['total'], 0, ',', '.'); ?> <span>VNĐ</span>
            </div>
            <a href="<?php echo $qr_url; ?>" download="QR_<?php echo $qr_data['order_code']; ?>.png" class="btn-download-qr">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Tải QR Code
            </a>
        </div>

        <div class="qr-right">
            <div class="order-head">Chi tiết đơn hàng</div>
            <div class="order-code"><?php echo htmlspecialchars($qr_data['order_code']); ?></div>

            <?php if ($qr_data['discount_pct'] > 0): ?>
            <div class="order-discount-info">
                🎉 Đã áp dụng giảm <strong><?= $qr_data['discount_pct'] ?>%</strong> tài khoản mới
                · Tiết kiệm <strong><?= number_format($qr_data['discount_amount'], 0, ',', '.') ?>đ</strong>
            </div>
            <?php endif; ?>

            <div class="order-details">
                <div class="od-item"><span class="od-icon">👤</span><div><div class="od-label">Họ và tên</div><div class="od-val"><?php echo htmlspecialchars($qr_data['full_name']); ?></div></div></div>
                <div class="od-item"><span class="od-icon">📧</span><div><div class="od-label">Email</div><div class="od-val"><?php echo htmlspecialchars($qr_data['email']); ?></div></div></div>
                <div class="od-item"><span class="od-icon">📞</span><div><div class="od-label">Số điện thoại</div><div class="od-val"><?php echo htmlspecialchars($qr_data['phone']); ?></div></div></div>
                <div class="od-item"><span class="od-icon">📅</span><div><div class="od-label">Thời gian lưu trú</div><div class="od-val"><?php echo date('d/m/Y', strtotime($qr_data['checkin'])); ?> → <?php echo date('d/m/Y', strtotime($qr_data['checkout'])); ?> (<?php echo $nights; ?> đêm)</div></div></div>
                <div class="od-item"><span class="od-icon">💳</span><div><div class="od-label">Phương thức</div><div class="od-val"><?php echo $method_label[$qr_data['method']] ?? ''; ?></div></div></div>
            </div>

            <div class="order-total-box">
                <?php if ($qr_data['discount_pct'] > 0): ?>
                <div style="display:flex;flex-direction:column;gap:2px">
                    <span style="font-size:12px;color:#a0aec0;text-decoration:line-through"><?= number_format($qr_data['original_price'], 0, ',', '.') ?> VNĐ</span>
                    <span>Tổng thanh toán</span>
                </div>
                <?php else: ?>
                <span>Tổng thanh toán</span>
                <?php endif; ?>
                <strong><?php echo number_format($qr_data['total'], 0, ',', '.'); ?> VNĐ</strong>
            </div>

            <div class="live-status-box" id="liveStatusBox">
                <div class="live-dot" id="liveDot"></div>
                <div>
                    <div class="live-label">Trạng thái thanh toán</div>
                    <div class="live-val" id="liveVal">Đang chờ xác nhận...</div>
                </div>
            </div>
            <div class="qr-note">
                ℹ️ Vui lòng thanh toán trong vòng <b>15 phút</b>.<br>
                Đơn hàng sẽ được xác nhận ngay sau khi nhận được thanh toán.
            </div>

            <!-- NÚT DEMO — xác nhận thanh toán mô phỏng -->
            <button type="button" id="demoConfirmBtn" onclick="demoConfirmPayment()"
                style="width:100%;margin-top:14px;padding:13px 0;background:linear-gradient(135deg,#276749,#38a169);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;letter-spacing:.3px">
                ✅ Xác nhận đã thanh toán (Demo)
            </button>
            <div id="demoMsg" style="margin-top:8px;font-size:12.5px;color:#718096;text-align:center;display:none"></div>

            <a href="hotels.php" class="btn-back-hotels">← Quay lại khách sạn</a>
        </div>
    </div>
</div>

<?php endif; // end is_hotel_pay ?>

<?php endif; // end qr_data ?>
</div>

<script>
var HAS_DISCOUNT = <?= $has_new_discount ? 'true' : 'false' ?>;
var DISCOUNT_PCT = <?= $new_user_discount ?>;

window.VOUCHER_TYPE     = '';
window.VOUCHER_VALUE    = 0;
window.VOUCHER_MIN_ORDER= 0;
window.VOUCHER_CODE     = '';

function applyVoucher() {
    var code  = document.getElementById('voucherInput').value.trim();
    var msgEl = document.getElementById('voucherMsg');
    if (!code) { showVoucherMsg('Vui lòng nhập mã voucher.', false); return; }

    var amount = window._baseTotalForVoucher || 0;
    showVoucherMsg('Đang kiểm tra...', null);

    var fd = new FormData();
    fd.append('code', code);
    fd.append('amount', amount);

    fetch('/pages/voucher_check.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                window.VOUCHER_TYPE      = res.type;
                window.VOUCHER_VALUE     = res.value;
                window.VOUCHER_MIN_ORDER = res.min_order;
                window.VOUCHER_CODE      = res.code;
                document.getElementById('voucherCodeHidden').value = res.code;
                document.getElementById('voucherCodeLabel').textContent = res.code;
                document.getElementById('voucherInput').disabled = true;
                var btn = document.getElementById('voucherApplyBtn');
                btn.textContent = '✕ Bỏ mã';
                btn.className = 'btn-remove-voucher';
                btn.onclick = function(){ removeVoucher(); };
                showVoucherMsg('✓ ' + res.message, true);
                calcPrice();
            } else {
                showVoucherMsg(res.message, false);
            }
        })
        .catch(() => showVoucherMsg('Có lỗi, vui lòng thử lại.', false));
}

function removeVoucher(silent) {
    window.VOUCHER_TYPE = window.VOUCHER_VALUE = window.VOUCHER_MIN_ORDER = 0;
    window.VOUCHER_CODE = '';
    document.getElementById('voucherCodeHidden').value = '';
    document.getElementById('voucherInput').value = '';
    document.getElementById('voucherInput').disabled = false;
    var btn = document.getElementById('voucherApplyBtn');
    btn.textContent = 'Áp dụng';
    btn.className = 'btn-apply-voucher';
    btn.onclick = function(){ applyVoucher(); };
    if (!silent) { document.getElementById('voucherMsg').style.display = 'none'; }
    calcPrice();
}

function showVoucherMsg(text, ok) {
    var el = document.getElementById('voucherMsg');
    el.textContent = text;
    el.className = 'voucher-msg' + (ok === true ? ' success' : ok === false ? ' error' : '');
    el.style.display = 'block';
}

<?php if ($prefill_room_id && $prefill_checkin && $prefill_checkout): ?>
document.addEventListener('DOMContentLoaded', function() { calcPrice(); });
<?php endif; ?>

function demoConfirmPayment() {
    var bookingEl = document.getElementById('bookingIdData');
    var bookingId = bookingEl ? bookingEl.dataset.id : 0;
    if (!bookingId || bookingId == '0') {
        alert('Không tìm thấy booking_id!');
        return;
    }

    var btn  = document.getElementById('demoConfirmBtn');
    var msg  = document.getElementById('demoMsg');
    btn.disabled = true;
    btn.textContent = '⏳ Đang xử lý...';

    var fd = new FormData();
    fd.append('booking_id', bookingId);

    fetch('/pages/demo_pay_confirm.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = '✅ Đã xác nhận! Đang tải kết quả...';
                msg.style.color = '#276749';
                msg.style.display = 'block';
            } else {
                msg.textContent = '⚠️ ' + res.message;
                msg.style.color = '#c53030';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.textContent = '✅ Xác nhận đã thanh toán (Demo)';
            }
        })
        .catch(() => {
            msg.textContent = 'Có lỗi, vui lòng thử lại.';
            msg.style.display = 'block';
            btn.disabled = false;
            btn.textContent = '✅ Xác nhận đã thanh toán (Demo)';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>