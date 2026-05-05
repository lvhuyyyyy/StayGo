<?php

// FIX 1: Khởi tạo tất cả biến trước để tránh Undefined variable warning
$current_user_id  = 0;
$eligible_bookings = [];
$has_booking      = 0;
$all_reviews      = [];
$stats            = [];

// 1. Đảm bảo session đã khởi động
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Kiểm tra $hotel_id và $conn tồn tại
$rv_valid = !empty($hotel_id) && !empty($conn);

if ($rv_valid) {
    // 3. Lấy user hiện tại từ session
    $current_user_id = (int)($_SESSION['user_id'] ?? 0);

    // -- Lấy tất cả đánh giá của khách sạn --
    $reviews_result = $conn->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at,
            u.full_name, u.email,
            r.booking_id
        FROM   reviews r
        JOIN   users   u ON u.id = r.user_id
        WHERE  r.hotel_id = ? AND r.is_active = 1
        ORDER  BY r.created_at DESC
    ");
    $reviews_result->bind_param("i", $hotel_id);
    $reviews_result->execute();
    $all_reviews = $reviews_result->get_result()->fetch_all(MYSQLI_ASSOC);

    // -- Thống kê rating --
    $stats_stmt = $conn->prepare("
        SELECT
            COUNT(*)             AS total,
            ROUND(AVG(rating),1) AS avg_rating,
            SUM(rating = 5)      AS s5,
            SUM(rating = 4)      AS s4,
            SUM(rating = 3)      AS s3,
            SUM(rating = 2)      AS s2,
            SUM(rating = 1)      AS s1
        FROM reviews
        WHERE hotel_id = ? AND is_active = 1
    ");
    $stats_stmt->bind_param("i", $hotel_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();

    // -- Booking có thể review --
    if ($current_user_id) {
        $eb = $conn->prepare("
            SELECT b.id AS booking_id, b.order_code,
                DATE_FORMAT(b.check_in,'%d/%m/%Y')  AS check_in,
                DATE_FORMAT(b.check_out,'%d/%m/%Y') AS check_out
            FROM   bookings b
            JOIN   rooms    r ON r.id = b.room_id
            WHERE  b.user_id   = ?
            AND  r.hotel_id  = ?
            AND  b.status    = 'completed'
            AND  b.id NOT IN (SELECT booking_id FROM reviews WHERE hotel_id = ?)
        ");
        $eb->bind_param("iii", $current_user_id, $hotel_id, $hotel_id);
        $eb->execute();
        $eligible_bookings = $eb->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // -- Kiểm tra có booking completed không --
    if ($current_user_id) {
        $hb = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            WHERE b.user_id = ? AND r.hotel_id = ? AND b.status = 'completed'
        ");
        $hb->bind_param("ii", $current_user_id, $hotel_id);
        $hb->execute();
        $has_booking = (int)$hb->get_result()->fetch_assoc()['cnt'];
    }
}
?>

<?php if (!$rv_valid): ?>
<!-- review_section: thiếu $hotel_id hoặc $conn -->
<?php return; endif; ?>

<section class="rv-section" id="reviews">
    <div class="rv-header">
        <h2 class="rv-title">Đánh giá của khách hàng</h2>

        <?php if (!empty($stats) && $stats['total'] > 0): ?>
        <div class="rv-overview">
            <div class="rv-score-box">
                <div class="rv-score-num"><?= number_format($stats['avg_rating'], 1) ?></div>

                <div class="rv-score-label">
                    <?php
                    $avg = (float)$stats['avg_rating'];
                    if ($avg >= 4.5)     echo 'Trên cả tuyệt vời';
                    elseif ($avg >= 4.0) echo 'Tuyệt vời';
                    elseif ($avg >= 3.5) echo 'Rất tốt';
                    elseif ($avg >= 3.0) echo 'Tốt';
                    else                 echo 'Kém';
                    ?>
                </div>

                <div class="rv-score-count"><?= (int)$stats['total'] ?> đánh giá</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <?php if (!$current_user_id): ?>
    <div class="rv-login-notice">
        <a href="/tour_khach_san_project/auth/login.php">Đăng nhập</a> để đánh giá khách sạn này.
    </div>

    <?php elseif (!empty($eligible_bookings)): ?>
    <div class="rv-form-wrap">
        <button class="rv-write-btn" id="rvWriteBtn" onclick="rvToggleForm()">✏️ Viết đánh giá của bạn</button>

        <div class="rv-form" id="rvForm" style="display:none;">
            <p class="rv-form-title">Đánh giá của bạn</p>

            <!-- Chọn booking -->
            <div class="rv-field">
                <label>Chọn lần lưu trú</label>
                <select class="rv-select" id="rvBookingId">
                    <option value="">-- Chọn đơn đặt phòng --</option>
                    <?php foreach ($eligible_bookings as $eb): ?>
                    <option value="<?= $eb['booking_id'] ?>">
                        Đơn #<?= htmlspecialchars($eb['order_code']) ?> (<?= $eb['check_in'] ?> → <?= $eb['check_out'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Chọn sao -->
            <div class="rv-field">
                <label>Số sao đánh giá</label>
                <div class="rv-star-picker" id="rvStarPicker">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="rv-star-btn" data-val="<?= $i ?>" onclick="rvSetStar(<?= $i ?>)" onmouseover="rvHoverStar(<?= $i ?>)" onmouseout="rvHoverStar(0)">
                        <svg width="28" height="28" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                    </button>
                    <?php endfor; ?>
                    <span class="rv-star-label" id="rvStarLabel">Chưa chọn</span>
                </div>
                <input type="hidden" id="rvRating" value="0">
            </div>

            <!-- Nhận xét -->
            <div class="rv-field">
                <label>Nhận xét (tối thiểu 10 ký tự)</label>
                <textarea class="rv-textarea" id="rvComment" placeholder="Chia sẻ trải nghiệm của bạn tại khách sạn này..."></textarea>
            </div>

            <div class="rv-form-actions">
                <button type="button" class="rv-submit-btn" onclick="rvSubmit()">Gửi đánh giá</button>
                <button type="button" class="rv-cancel-btn" onclick="rvToggleForm()">Hủy</button>
            </div>
            <div class="rv-msg" id="rvMsg"></div>
        </div>
    </div>

    <?php else: ?>
    <div class="rv-already-notice">
        <?php if ($has_booking > 0): ?>
            ✅ Bạn đã đánh giá tất cả các lần lưu trú tại khách sạn này.
        <?php else: ?>
            ℹ️ Bạn chưa có lần lưu trú hoàn thành tại khách sạn này.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Danh sách đánh giá -->
    <div class="rv-list" id="rvList">
        <?php if (empty($all_reviews)): ?>
        <div class="rv-empty"><p>Chưa có đánh giá nào.</p></div>
        <?php else: ?>
        <?php foreach ($all_reviews as $rv):
            $initial = mb_strtoupper(mb_substr($rv['full_name'], 0, 1));
        ?>
        <div class="rv-item">
            <div class="rv-item-header">
                <div class="rv-avatar"><?= htmlspecialchars($initial) ?></div>
                <div class="rv-item-info">
                    <p class="rv-item-name"><?= htmlspecialchars($rv['full_name']) ?></p>
                    <p class="rv-item-date"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></p>
                </div>
                <div class="rv-item-stars">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                    <svg class="rv-star-icon <?= $s <= $rv['rating'] ? 'filled' : '' ?>" width="16" height="16" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                    <?php endfor; ?>
                    <span class="rv-item-rating"><?= $rv['rating'] ?>/5</span>
                </div>
            </div>
            <p class="rv-item-comment"><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<script>
const RV_HOTEL_ID = <?= (int)$hotel_id ?>;
const RV_HANDLER  = '/tour_khach_san_project/pages/reviews_handler.php';
const rvStarLabels = ['', 'Tệ', 'Không tốt', 'Bình thường', 'Tốt', 'Tuyệt vời'];

function rvToggleForm() {
    const form = document.getElementById('rvForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function rvSetStar(val) {
    document.getElementById('rvRating').value = val;
    document.querySelectorAll('.rv-star-btn').forEach(btn => {
        btn.classList.toggle('selected', parseInt(btn.dataset.val) <= val);
    });
    document.getElementById('rvStarLabel').textContent = rvStarLabels[val] || '';
}

function rvHoverStar(val) {
    document.querySelectorAll('.rv-star-btn').forEach(btn => {
        btn.classList.toggle('hovered', val > 0 && parseInt(btn.dataset.val) <= val);
    });
}

function rvSubmit() {
    const bookingId = document.getElementById('rvBookingId').value;
    const rating    = document.getElementById('rvRating').value;
    const comment   = document.getElementById('rvComment').value.trim();
    const msgEl     = document.getElementById('rvMsg');

    if (!bookingId) { rvShowMsg('Vui lòng chọn lần lưu trú.', false); return; }
    if (rating < 1) { rvShowMsg('Vui lòng chọn số sao.', false); return; }
    if (comment.length < 10) { rvShowMsg('Nhận xét tối thiểu 10 ký tự.', false); return; }

    const fd = new FormData();
    fd.append('action',     'submit');
    fd.append('hotel_id',   RV_HOTEL_ID);
    fd.append('booking_id', bookingId);
    fd.append('rating',     rating);
    fd.append('comment',    comment);

    fetch(RV_HANDLER, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            rvShowMsg(res.message, res.success);
            if (res.success) {
                setTimeout(() => location.reload(), 1200);
            }
        })
        .catch(() => rvShowMsg('Có lỗi xảy ra, vui lòng thử lại.', false));
}

function rvShowMsg(text, ok) {
    const el = document.getElementById('rvMsg');
    el.textContent = text;
    el.className = 'rv-msg ' + (ok ? 'success' : 'error');
}
</script>