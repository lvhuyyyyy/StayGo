<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Lấy danh sách yêu thích kèm thông tin khách sạn
$stmt = $conn->prepare("
    SELECT f.id AS fav_id, f.created_at AS fav_date,
           h.id AS hotel_id, h.name, h.address, h.image, h.rating, h.review_count,
           h.price, h.old_price, h.is_weekend_deal,
           l.name AS location_name,
           (SELECT MIN(price) FROM rooms WHERE hotel_id = h.id) AS min_room_price
    FROM favorites f
    JOIN hotels h ON f.hotel_id = h.id
    LEFT JOIN locations l ON h.location_id = l.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
.fav-page { max-width: 1100px; margin: 40px auto; padding: 0 20px 60px; }
.fav-page-title {
    font-size: 26px; font-weight: 700; color: #1a202c;
    margin-bottom: 6px; display: flex; align-items: center; gap: 10px;
}
.fav-page-sub { color: #718096; font-size: 14px; margin-bottom: 32px; }
.fav-count-badge {
    background: #e53e3e; color: #fff;
    font-size: 13px; font-weight: 700;
    padding: 2px 10px; border-radius: 20px;
}

.fav-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}
@media (max-width: 900px) {
    .fav-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 580px) {
    .fav-grid { grid-template-columns: 1fr; }
}

.fav-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
}
.fav-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 28px rgba(0,0,0,0.13);
}
.fav-card-img {
    position: relative;
    height: 190px;
    overflow: hidden;
}
.fav-card-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.35s;
}
.fav-card:hover .fav-card-img img { transform: scale(1.05); }

.fav-remove-btn {
    position: absolute; top: 10px; right: 10px;
    background: rgba(255,255,255,0.92);
    border: none; border-radius: 50%;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: background 0.2s, transform 0.2s;
    z-index: 2;
}
.fav-remove-btn:hover { background: #fff5f5; transform: scale(1.1); }
.fav-remove-btn svg { color: #e53e3e; }

.fav-deal-tag {
    position: absolute; top: 10px; left: 10px;
    background: #e53e3e; color: #fff;
    font-size: 11px; font-weight: 700;
    padding: 3px 10px; border-radius: 20px;
}

.fav-card-body { padding: 16px 18px 18px; }
.fav-card-loc { font-size: 12px; color: #718096; margin-bottom: 4px; }
.fav-card-name {
    font-size: 16px; font-weight: 700; color: #1a202c;
    margin-bottom: 8px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fav-card-rating {
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 12px;
}
.fav-score {
    background: #0071c2; color: #fff;
    font-size: 12px; font-weight: 700;
    padding: 2px 7px; border-radius: 6px;
}
.fav-score-lbl { font-size: 12px; color: #4a5568; }
.fav-score-cnt { font-size: 11px; color: #a0aec0; }

.fav-card-price { margin-bottom: 14px; }
.fav-price-label { font-size: 11px; color: #a0aec0; }
.fav-price-main { font-size: 20px; font-weight: 800; color: #0071c2; }
.fav-price-unit { font-size: 12px; color: #718096; }
.fav-price-old {
    font-size: 13px; color: #a0aec0;
    text-decoration: line-through; margin-left: 6px;
}

.fav-card-actions { display: flex; gap: 10px; }
.fav-btn-detail {
    flex: 1; text-align: center;
    padding: 9px 0;
    border-radius: 8px;
    font-size: 13px; font-weight: 600;
    border: 1.5px solid #0071c2;
    color: #0071c2;
    background: #fff;
    text-decoration: none;
    transition: background 0.18s, color 0.18s;
}
.fav-btn-detail:hover { background: #ebf4ff; }
.fav-btn-book {
    flex: 1; text-align: center;
    padding: 9px 0;
    border-radius: 8px;
    font-size: 13px; font-weight: 600;
    background: #0071c2; color: #fff;
    text-decoration: none;
    transition: background 0.18s;
}
.fav-btn-book:hover { background: #005fa3; }

.fav-empty {
    text-align: center; padding: 80px 20px;
    color: #718096;
}
.fav-empty-icon { font-size: 64px; margin-bottom: 20px; }
.fav-empty h3 { font-size: 20px; font-weight: 700; color: #2d3748; margin-bottom: 8px; }
.fav-empty p  { font-size: 14px; margin-bottom: 24px; }
.fav-empty-btn {
    display: inline-block;
    background: #0071c2; color: #fff;
    padding: 11px 28px; border-radius: 10px;
    font-weight: 600; font-size: 14px;
    text-decoration: none;
    transition: background 0.18s;
}
.fav-empty-btn:hover { background: #005fa3; }

/* Toast */
.fav-toast {
    position: fixed; bottom: 28px; left: 50%;
    transform: translateX(-50%) translateY(60px);
    background: #2d3748; color: #fff;
    padding: 11px 24px; border-radius: 10px;
    font-size: 14px; font-weight: 500;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    opacity: 0; transition: all 0.3s; z-index: 9999;
    white-space: nowrap;
}
.fav-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>

<div class="fav-page">
    <div class="fav-page-title">
        <svg width="26" height="26" fill="#e53e3e" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        Yêu thích của tôi
        <?php if (count($favorites) > 0): ?>
            <span class="fav-count-badge"><?= count($favorites) ?></span>
        <?php endif; ?>
    </div>
    <div class="fav-page-sub">Những khách sạn bạn đã lưu để xem lại sau</div>

    <?php if (empty($favorites)): ?>
        <div class="fav-empty">
            <div class="fav-empty-icon">🤍</div>
            <h3>Chưa có khách sạn yêu thích</h3>
            <p>Nhấn nút tim trên trang chi tiết khách sạn để lưu vào đây.</p>
            <a href="/tour_khach_san_project/pages/hotels.php" class="fav-empty-btn">Khám phá khách sạn</a>
        </div>
    <?php else: ?>
        <div class="fav-grid" id="favGrid">
        <?php foreach ($favorites as $fav):
            $display_price = $fav['min_room_price'] ?? $fav['price'];
            $old_price = $fav['old_price'] ?? 0;
            $discount = ($old_price > $display_price && $display_price > 0)
                ? round((1 - $display_price / $old_price) * 100) : 0;
            $rating = (float) $fav['rating'];
            $rating_lbl = match(true) {
                $rating >= 9 => 'Tuyệt vời',
                $rating >= 8 => 'Rất tốt',
                $rating >= 7 => 'Tốt',
                default      => 'Bình thường',
            };
        ?>
        <div class="fav-card" id="fav-card-<?= $fav['hotel_id'] ?>">
            <div class="fav-card-img">
                <?php if ($fav['is_weekend_deal']): ?>
                    <span class="fav-deal-tag">🔥 Deal cuối tuần</span>
                <?php endif; ?>
                <img src="/tour_khach_san_project/assets/images/<?= htmlspecialchars($fav['image'] ?? '') ?>"
                     alt="<?= htmlspecialchars($fav['name']) ?>"
                     onerror="this.src='/tour_khach_san_project/assets/images/placeholder.jpg'">
                <button class="fav-remove-btn"
                        onclick="removeFavorite(<?= $fav['hotel_id'] ?>, this)"
                        title="Xóa khỏi yêu thích">
                    <svg width="18" height="18" fill="#e53e3e" viewBox="0 0 24 24">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                </button>
            </div>
            <div class="fav-card-body">
                <div class="fav-card-loc">📍 <?= htmlspecialchars($fav['location_name'] ?? $fav['address']) ?></div>
                <div class="fav-card-name" title="<?= htmlspecialchars($fav['name']) ?>">
                    <?= htmlspecialchars($fav['name']) ?>
                </div>
                <div class="fav-card-rating">
                    <span class="fav-score"><?= number_format($rating, 1) ?></span>
                    <span class="fav-score-lbl"><?= $rating_lbl ?></span>
                    <span class="fav-score-cnt">(<?= number_format($fav['review_count'] ?? 0) ?> đánh giá)</span>
                </div>
                <div class="fav-card-price">
                    <div class="fav-price-label">Giá từ</div>
                    <span class="fav-price-main"><?= number_format($display_price) ?></span>
                    <span class="fav-price-unit">đ/đêm</span>
                    <?php if ($discount > 0): ?>
                        <span class="fav-price-old"><?= number_format($old_price) ?>đ</span>
                    <?php endif; ?>
                </div>
                <div class="fav-card-actions">
                    <a href="/tour_khach_san_project/pages/hotel_detail.php?id=<?= $fav['hotel_id'] ?>" class="fav-btn-detail">Xem chi tiết</a>
                    <a href="/tour_khach_san_project/pages/payment.php?hotel_id=<?= $fav['hotel_id'] ?>" class="fav-btn-book">Đặt ngay</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="fav-toast" id="favToast"></div>

<script>
function showToast(msg) {
    const t = document.getElementById('favToast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}

function removeFavorite(hotelId, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';

    const fd = new FormData();
    fd.append('hotel_id', hotelId);

    fetch('/tour_khach_san_project/pages/toggle_favorite.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('fav-card-' + hotelId);
            card.style.transition = 'opacity 0.35s, transform 0.35s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            setTimeout(() => {
                card.remove();
                // Cập nhật badge số lượng
                const grid = document.getElementById('favGrid');
                const remaining = grid ? grid.querySelectorAll('.fav-card').length : 0;
                const badge = document.querySelector('.fav-count-badge');
                if (badge) {
                    if (remaining > 0) badge.textContent = remaining;
                    else badge.remove();
                }
                if (remaining === 0) {
                    grid.outerHTML = `
                        <div class="fav-empty">
                            <div class="fav-empty-icon">🤍</div>
                            <h3>Chưa có khách sạn yêu thích</h3>
                            <p>Nhấn nút tim trên trang chi tiết khách sạn để lưu vào đây.</p>
                            <a href="/tour_khach_san_project/pages/hotels.php" class="fav-empty-btn">Khám phá khách sạn</a>
                        </div>`;
                }
            }, 350);
            showToast('Đã xóa khỏi danh sách yêu thích');
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            showToast(data.message || 'Có lỗi xảy ra.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.style.opacity = '1';
        showToast('Có lỗi kết nối, thử lại.');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
