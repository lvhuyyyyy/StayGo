<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Tổng số booking
$r1 = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$r1->bind_param("i", $user_id);
$r1->execute();
$total_bookings = $r1->get_result()->fetch_assoc()['total'] ?? 0;

// Booking đang pending/confirmed
$r2 = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND status IN ('pending','confirmed')");
$r2->bind_param("i", $user_id);
$r2->execute();
$active_bookings = $r2->get_result()->fetch_assoc()['total'] ?? 0;

// Tổng chi tiêu
$r3 = $conn->prepare("SELECT SUM(total_price) as total FROM bookings WHERE user_id = ? AND status != 'cancelled'");
$r3->bind_param("i", $user_id);
$r3->execute();
$total_spent = $r3->get_result()->fetch_assoc()['total'] ?? 0;

// Số khách sạn yêu thích
$r_fav = $conn->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
$r_fav->bind_param("i", $user_id);
$r_fav->execute();
$total_favorites = $r_fav->get_result()->fetch_assoc()['total'] ?? 0;

// 3 booking gần nhất
$r4 = $conn->prepare("
    SELECT b.id, b.order_code, b.check_in, b.check_out, b.total_price, b.status,
        b.payment_method, b.created_at,
        r.room_name,
        h.name AS hotel_name, h.image AS hotel_image
    FROM bookings b
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN hotels h ON r.hotel_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
    LIMIT 3
");
$r4->bind_param("i", $user_id);
$r4->execute();
$recent_bookings = $r4->get_result()->fetch_all(MYSQLI_ASSOC);

// Yêu cầu hỗ trợ gần đây
$r5 = $conn->prepare("SELECT id, subject, note, status, admin_note, created_at FROM support_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$r5->bind_param("i", $user_id);
$r5->execute();
$support_requests = $r5->get_result()->fetch_all(MYSQLI_ASSOC);

$first_char = mb_strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1));
$joined = isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A';

$status_map = [
    'pending'   => ['Chờ xác nhận', '#b7791f', '#fffbeb', '#fef3c7'],
    'confirmed' => ['Đã xác nhận',  '#2b6cb0', '#ebf8ff', '#bee3f8'],
    'cancelled' => ['Đã hủy',       '#c53030', '#fff5f5', '#fed7d7'],
    'completed' => ['Hoàn thành',   '#276749', '#f0fff4', '#9ae6b4'],
];
?>

<div class="profile-page-wrapper">
<div class="profile-wrap">

    <!-- SIDEBAR -->
    <aside class="profile-sidebar">

        <div class="sidebar-top">
            <div class="big-avatar"><?= $first_char ?></div>
            <h2 class="sb-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></h2>
            <span class="role-pill"><?= htmlspecialchars($user['role'] ?? 'user') ?></span>
        </div>

        <div class="sidebar-meta">
            <div class="meta-item">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.75L2.25 6.75"/></svg>
                <span><?= htmlspecialchars($user['email'] ?? '') ?></span>
            </div>
            <div class="meta-item">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/></svg>
                <span>Tham gia: <?= $joined ?></span>
            </div>
        </div>

        <div class="sidebar-btns">
            <a href="edit_profile.php" class="sb-btn primary">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                Chỉnh sửa thông tin
            </a>
            <a href="change_password.php" class="sb-btn outline">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                Đổi mật khẩu
            </a>
        </div>

    </aside>

    <!-- MAIN -->
    <main class="profile-main">

        <!-- Stats -->
        <div class="stats-grid">

            <div class="stat-box">
                <div class="stat-ico" style="background:#dbeafe">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#1d4ed8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <div class="stat-val"><?= $total_bookings ?></div>
                    <div class="stat-lbl">Tổng đặt phòng</div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-ico" style="background:#dcfce7">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#15803d" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <div class="stat-val"><?= $active_bookings ?></div>
                    <div class="stat-lbl">Chờ / Đã xác nhận</div>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-ico" style="background:#ede9fe">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#7c3aed" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($total_spent, 0, ',', '.') ?>đ</div>
                    <div class="stat-lbl">Tổng chi tiêu</div>
                </div>
            </div>

            <a href="/pages/my_favorites.php" class="stat-box" style="text-decoration:none;cursor:pointer;" title="Xem danh sách yêu thích">
                <div class="stat-ico" style="background:#fff0f0">
                    <svg width="20" height="20" fill="#e53e3e" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>
                <div>
                    <div class="stat-val"><?= $total_favorites ?></div>
                    <div class="stat-lbl">Khách sạn yêu thích</div>
                </div>
            </a>

        </div>

        <!-- Recent bookings -->
        <div class="section-box">

            <div class="section-hd">
                <div class="section-title">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Đặt phòng gần đây
                </div>
                <a href="my_bookings.php" class="see-all-link">Xem tất cả →</a>
            </div>

            <?php if(empty($recent_bookings)): ?>
                <div class="empty-box">
                    <svg width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                    <p>Bạn chưa có đặt phòng nào</p>
                    <a href="hotels.php" class="sb-btn primary" style="width:auto;margin-top:12px">Khám phá khách sạn</a>
                </div>
            <?php else: ?>
                <div class="bk-list">
                    <?php foreach($recent_bookings as $b):
                        $sm = $status_map[$b['status']] ?? ['Không rõ','#718096','#f7fafc','#e2e8f0'];
                        $ci = $b['check_in']  ? date('d/m/Y', strtotime($b['check_in']))  : '?';
                        $co = $b['check_out'] ? date('d/m/Y', strtotime($b['check_out'])) : '?';
                    ?>
                    <div class="bk-item">
                        <div class="bk-icon">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                        </div>
                        <div class="bk-info">
                            <div class="bk-hotel"><?= htmlspecialchars($b['hotel_name'] ?? 'Khách sạn') ?></div>
                            <div class="bk-sub">
                                <?= htmlspecialchars($b['room_name'] ?? '') ?>
                                &nbsp;·&nbsp;
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25"/></svg>
                                <?= $ci ?> → <?= $co ?>
                                &nbsp;·&nbsp;
                                <strong><?= number_format($b['total_price'], 0, ',', '.') ?>đ</strong>
                            </div>
                            <div class="bk-code"># <?= htmlspecialchars($b['order_code']) ?></div>
                        </div>
                        <span class="bk-status" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;border:1px solid <?= $sm[3] ?>">
                            <?= $sm[0] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- Yêu cầu hỗ trợ -->
        <div class="section-box" style="margin-top:20px">
            <div class="section-hd">
                <div class="section-title">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Yêu cầu hỗ trợ của tôi
                </div>
                <a href="#" onclick="openFooterSupport();return false;" class="see-all-link">+ Gửi yêu cầu mới</a>
            </div>

            <?php
            $sr_status_map = [
                'pending'    => ['⏳ Chờ xử lý',    '#b7791f','#fffbeb','#fef3c7'],
                'processing' => ['🔄 Đang xử lý',   '#1e73be','#ebf8ff','#bee3f8'],
                'resolved'   => ['✅ Đã giải quyết', '#276749','#f0fff4','#9ae6b4'],
            ];
            ?>

            <?php if(empty($support_requests)): ?>
                <div class="empty-box">
                    <svg width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p>Bạn chưa có yêu cầu hỗ trợ nào</p>
                </div>
            <?php else: ?>
                <div class="bk-list">
                <?php foreach($support_requests as $sr):
                    $sm = $sr_status_map[$sr['status']] ?? $sr_status_map['pending'];
                ?>
                    <div class="bk-item">
                        <div class="bk-icon">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="bk-info">
                            <div class="bk-hotel"><?= htmlspecialchars($sr['subject'] ?: 'Yêu cầu hỗ trợ #' . $sr['id']) ?></div>
                            <div class="bk-sub"><?= htmlspecialchars(mb_substr($sr['note'], 0, 80)) ?>...</div>
                            <?php if($sr['admin_note']): ?>
                            <div class="bk-sub" style="color:#276749;margin-top:4px">
                                💬 Phản hồi: <?= htmlspecialchars(mb_substr($sr['admin_note'], 0, 100)) ?>
                            </div>
                            <?php endif; ?>
                            <div class="bk-code">📅 <?= date('d/m/Y H:i', strtotime($sr['created_at'])) ?></div>
                        </div>
                        <span class="bk-status" style="color:<?= $sm[1] ?>;background:<?= $sm[2] ?>;border:1px solid <?= $sm[3] ?>">
                            <?= $sm[0] ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>