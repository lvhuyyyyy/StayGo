<?php
$page_title    = 'Đánh giá của khách';
$page_subtitle = 'Xem và phản hồi nhận xét từ khách hàng';
require_once __DIR__ . '/../includes/hotel_header.php';
require_once __DIR__ . '/../includes/security.php';

// Auto-add hotel_response column if not exists
if (!@$conn->query("SELECT hotel_response FROM reviews LIMIT 0")) {
    $conn->query("ALTER TABLE reviews ADD COLUMN hotel_response TEXT NULL DEFAULT NULL AFTER comment");
    $conn->query("ALTER TABLE reviews ADD COLUMN response_at DATETIME NULL DEFAULT NULL AFTER hotel_response");
}

$msg   = null;
$error = null;

// ── POST: lưu phản hồi ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_response'])) {
    csrf_check();
    $rid      = (int)($_POST['review_id'] ?? 0);
    $response = trim($_POST['response'] ?? '');

    // Xác minh review thuộc hotel này
    $chk = $conn->prepare("SELECT id FROM reviews WHERE id=? AND hotel_id=?");
    $chk->bind_param("ii", $rid, $hotel_id);
    $chk->execute();
    if ($chk->get_result()->num_rows) {
        if (mb_strlen($response) < 5) {
            $error = 'Phản hồi quá ngắn (tối thiểu 5 ký tự).';
        } else {
            $response = mb_substr($response, 0, 1000);
            $stmt = $conn->prepare("UPDATE reviews SET hotel_response=?, response_at=NOW() WHERE id=? AND hotel_id=?");
            $stmt->bind_param("sii", $response, $rid, $hotel_id);
            $stmt->execute();
            $msg = 'Đã lưu phản hồi.';
        }
    }
}

// ── Thống kê tổng quan ────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*)                                             AS total,
        ROUND(AVG(rating), 2)                               AS avg_rating,
        SUM(rating = 5)                                     AS cnt5,
        SUM(rating = 4)                                     AS cnt4,
        SUM(rating = 3)                                     AS cnt3,
        SUM(rating = 2)                                     AS cnt2,
        SUM(rating = 1)                                     AS cnt1,
        SUM(hotel_response IS NULL OR hotel_response = '')  AS pending_resp
    FROM reviews
    WHERE hotel_id = $hotel_id AND is_active = 1
")->fetch_assoc();

// ── Filter ────────────────────────────────────────────────────────────────
$filterRating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filterResp   = $_GET['resp'] ?? '';   // 'no' | ''
$whereExtra   = '';
if ($filterRating >= 1 && $filterRating <= 5) {
    $whereExtra .= " AND rv.rating = $filterRating";
}
if ($filterResp === 'no') {
    $whereExtra .= " AND (rv.hotel_response IS NULL OR rv.hotel_response = '')";
}

// ── Danh sách đánh giá ────────────────────────────────────────────────────
$reviews = $conn->query("
    SELECT rv.id, rv.rating, rv.comment, rv.hotel_response, rv.response_at,
           rv.created_at, rv.is_active,
           u.full_name AS user_name,
           b.order_code, b.check_in, b.check_out,
           r.room_name
    FROM reviews rv
    JOIN bookings b ON rv.booking_id = b.id
    JOIN rooms r    ON b.room_id = r.id
    JOIN users u    ON rv.user_id = u.id
    WHERE rv.hotel_id = $hotel_id AND rv.is_active = 1
    $whereExtra
    ORDER BY rv.created_at DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

function stars(int $n, bool $small = false): string {
    $sz = $small ? '14px' : '16px';
    $s = '';
    for ($i = 1; $i <= 5; $i++) {
        $s .= "<span style='color:".($i<=$n?'#f6c90e':'#cbd5e0').";font-size:$sz'>★</span>";
    }
    return $s;
}
function ratingColor(int $r): string {
    return $r >= 4 ? '#276749' : ($r == 3 ? '#92400e' : '#c53030');
}
?>

<?php if ($msg): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="stat-grid" style="margin-bottom:20px">
    <div class="stat-card blue">
        <div class="stat-num"><?= number_format((float)($stats['avg_rating'] ?? 0), 1) ?></div>
        <div class="stat-label">Điểm TB / 5 ★</div>
    </div>
    <div class="stat-card green">
        <div class="stat-num"><?= (int)$stats['total'] ?></div>
        <div class="stat-label">Tổng đánh giá</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-num"><?= (int)$stats['pending_resp'] ?></div>
        <div class="stat-label">Chưa phản hồi</div>
    </div>
    <div class="stat-card">
        <div style="display:flex;flex-direction:column;gap:3px">
            <?php foreach ([5,4,3,2,1] as $s):
                $cnt  = (int)$stats["cnt$s"];
                $pct  = $stats['total'] > 0 ? round($cnt / $stats['total'] * 100) : 0;
                $col  = $s >= 4 ? '#276749' : ($s == 3 ? '#92400e' : '#c53030');
            ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:12px">
                <span style="width:12px;color:<?= $col ?>;font-weight:700"><?= $s ?>★</span>
                <div style="flex:1;background:#edf2f7;border-radius:4px;height:6px;overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:4px"></div>
                </div>
                <span style="width:24px;text-align:right;color:#718096"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="stat-label" style="margin-top:6px">Phân bố sao</div>
    </div>
</div>

<!-- ── Filter bar ─────────────────────────────────────────────────────────── -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
    <span style="font-size:13px;font-weight:600;color:#4a5568">Lọc:</span>
    <?php
    $baseQ = http_build_query(array_filter(['resp' => $filterResp]));
    ?>
    <a href="/hotel/reviews.php<?= $filterResp ? '?resp='.$filterResp : '' ?>"
       class="btn btn-sm <?= $filterRating == 0 ? 'btn-primary' : 'btn-outline' ?>">Tất cả</a>
    <?php foreach ([5,4,3,2,1] as $s): ?>
    <a href="/hotel/reviews.php?rating=<?= $s ?><?= $filterResp ? '&resp='.$filterResp : '' ?>"
       class="btn btn-sm <?= $filterRating == $s ? 'btn-primary' : 'btn-outline' ?>"><?= $s ?>★</a>
    <?php endforeach; ?>
    <a href="/hotel/reviews.php?resp=no<?= $filterRating ? '&rating='.$filterRating : '' ?>"
       class="btn btn-sm <?= $filterResp == 'no' ? 'btn-primary' : 'btn-outline' ?>">Chưa phản hồi</a>
    <?php if ($filterRating || $filterResp): ?>
    <a href="/hotel/reviews.php" class="btn btn-sm" style="color:#e53e3e;border-color:#e53e3e">✕ Xóa lọc</a>
    <?php endif; ?>
    <span style="margin-left:auto;font-size:13px;color:#a0aec0"><?= count($reviews) ?> kết quả</span>
</div>

<!-- ── Review list ────────────────────────────────────────────────────────── -->
<?php if (empty($reviews)): ?>
<div class="card">
    <div style="padding:60px;text-align:center;color:#a0aec0">
        <div style="font-size:48px;margin-bottom:12px">⭐</div>
        <div style="font-size:15px;font-weight:600">Chưa có đánh giá nào</div>
    </div>
</div>
<?php endif; ?>

<?php foreach ($reviews as $rv):
    $hasResp = !empty($rv['hotel_response']);
    $rColor  = ratingColor((int)$rv['rating']);
?>
<div class="card" style="margin-bottom:14px" id="rv<?= $rv['id'] ?>">
    <div style="padding:16px 20px">

        <!-- Header: user + rating + date -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#3182ce,#63b3ed);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px;flex-shrink:0">
                    <?= strtoupper(mb_substr($rv['user_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:14px;color:#1a365d"><?= htmlspecialchars($rv['user_name']) ?></div>
                    <div style="font-size:12px;color:#a0aec0">
                        <?= $rv['room_name'] ?> · <?= date('d/m/Y', strtotime($rv['check_in'])) ?> – <?= date('d/m/Y', strtotime($rv['check_out'])) ?>
                        · #<?= htmlspecialchars($rv['order_code']) ?>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
                <div style="background:<?= $rColor ?>15;border-radius:8px;padding:4px 10px;display:flex;align-items:center;gap:4px">
                    <span style="font-size:18px;font-weight:800;color:<?= $rColor ?>"><?= $rv['rating'] ?></span>
                    <span style="color:#f6c90e;font-size:14px">★</span>
                </div>
                <?= stars((int)$rv['rating'], true) ?>
                <span style="font-size:12px;color:#a0aec0"><?= date('d/m/Y', strtotime($rv['created_at'])) ?></span>
            </div>
        </div>

        <!-- Comment -->
        <div style="background:#f7fafc;border-radius:10px;padding:12px 16px;font-size:13.5px;color:#2d3748;line-height:1.6;margin-bottom:14px;border-left:3px solid <?= $rColor ?>">
            "<?= nl2br(htmlspecialchars($rv['comment'])) ?>"
        </div>

        <!-- Response section -->
        <?php if ($hasResp): ?>
        <div style="background:#f0fff4;border-radius:10px;padding:12px 16px;border:1px solid #9ae6b4;margin-bottom:10px">
            <div style="font-size:12px;font-weight:700;color:#276749;margin-bottom:6px">
                🏨 Phản hồi của khách sạn
                <span style="font-weight:400;color:#68d391;margin-left:6px"><?= date('d/m/Y', strtotime($rv['response_at'])) ?></span>
            </div>
            <div style="font-size:13.5px;color:#276749;line-height:1.6"><?= nl2br(htmlspecialchars($rv['hotel_response'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Toggle form button -->
        <button onclick="toggleResp(<?= $rv['id'] ?>)"
                class="btn btn-sm <?= $hasResp ? 'btn-outline' : 'btn-primary' ?>"
                style="font-size:12.5px">
            <?= $hasResp ? '✏️ Sửa phản hồi' : '💬 Viết phản hồi' ?>
        </button>

        <!-- Response form (hidden) -->
        <div id="form<?= $rv['id'] ?>" style="display:none;margin-top:12px">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                <input type="hidden" name="save_response" value="1">
                <textarea name="response"
                    style="width:100%;border:1.5px solid #3182ce;border-radius:10px;padding:10px 14px;font-size:13.5px;font-family:inherit;resize:vertical;min-height:90px;outline:none;box-sizing:border-box"
                    placeholder="Viết phản hồi chuyên nghiệp... Cảm ơn khách, ghi nhận ý kiến, mời quay lại."
                    ><?= htmlspecialchars($rv['hotel_response'] ?? '') ?></textarea>
                <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
                    <button type="submit" class="btn btn-success btn-sm">💾 Lưu phản hồi</button>
                    <button type="button" onclick="toggleResp(<?= $rv['id'] ?>)" class="btn btn-outline btn-sm">Hủy</button>
                    <span style="font-size:11.5px;color:#a0aec0;margin-left:auto">Tối đa 1000 ký tự · Công khai với khách</span>
                </div>
            </form>
        </div>

    </div>
</div>
<?php endforeach; ?>

<script>
function toggleResp(id) {
    const f = document.getElementById('form' + id);
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
    if (f.style.display === 'block') f.querySelector('textarea').focus();
}
// Auto-open form nếu vừa lưu lỗi
<?php if ($error): ?>
document.querySelectorAll('[id^="form"]').forEach(f => f.style.display = 'block');
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/hotel_footer.php'; ?>
