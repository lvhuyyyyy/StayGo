<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['hotel_id'])) {
    header("Location: /hotel/dashboard.php");
    exit;
}

$error       = null;
$suspend_msg = null;

if (isset($_GET['err']) && $_GET['err'] === 'suspended') {
    $suspend_msg = 'Tài khoản khách sạn của bạn đang bị tạm đình chỉ. Vui lòng liên hệ Admin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (!$email || !$password) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, name, contact_name, partner_password, partner_status
            FROM hotels
            WHERE partner_email = ? AND partner_password IS NOT NULL
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $hotel = $stmt->get_result()->fetch_assoc();

        if (!$hotel || !password_verify($password, $hotel['partner_password'])) {
            $error = 'Email hoặc mật khẩu không đúng.';
        } elseif ($hotel['partner_status'] === 'SUSPENDED') {
            $error = 'Tài khoản khách sạn của bạn đang bị tạm đình chỉ. Vui lòng liên hệ Admin.';
        } elseif ($hotel['partner_status'] === 'REJECTED') {
            $error = 'Tài khoản khách sạn của bạn đã bị từ chối. Vui lòng liên hệ Admin.';
        } elseif ($hotel['partner_status'] !== 'ACTIVE') {
            $error = 'Tài khoản đang chờ duyệt. Admin sẽ kích hoạt sớm.';
        } else {
            $_SESSION['hotel_id']      = (int)$hotel['id'];
            $_SESSION['hotel_name']    = $hotel['name'];
            $_SESSION['hotel_contact'] = $hotel['contact_name'];
            $_SESSION['hotel_status']  = $hotel['partner_status'];
            session_regenerate_id(true);
            header("Location: /hotel/dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hotel Partner Login — StayGo</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

:root {
    --slate-50:#f8fafc;--slate-100:#f1f5f9;--slate-200:#e2e8f0;
    --slate-400:#94a3b8;--slate-500:#64748b;--slate-600:#475569;
    --slate-700:#334155;--slate-800:#1e293b;--slate-900:#0f172a;
    --cyan-400:#22d3ee;--cyan-500:#06b6d4;--cyan-600:#0891b2;
    --blue-900:#1e3a5f;
    --red-50:#fff5f5;--red-200:#fed7d7;--red-700:#c53030;
    --amber-50:#fffbeb;--amber-200:#fde68a;--amber-800:#92400e;
}

body {
    font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
    background:var(--slate-100);
    min-height:100vh;
    display:grid;
    grid-template-columns:1fr 1fr;
}

/* ── LEFT PANEL ──────────────────────────────────────────── */
.panel-left {
    position:relative;
    min-height:100vh;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    padding:40px 48px;
    /* hotel background with dark blue overlay */
    background:
        linear-gradient(160deg,
            rgba(10,25,47,.88) 0%,
            rgba(14,55,100,.80) 45%,
            rgba(6,40,78,.85) 100%),
        linear-gradient(135deg,#0a192f 0%,#0d3b6e 40%,#0a2a5e 70%,#071a3e 100%);
}

/* Decorative circles for depth */
.panel-left::before {
    content:'';
    position:absolute;inset:0;
    background:
        radial-gradient(ellipse 60% 40% at 20% 60%,rgba(34,211,238,.08) 0%,transparent 70%),
        radial-gradient(ellipse 40% 60% at 80% 20%,rgba(99,102,241,.06) 0%,transparent 60%);
    pointer-events:none;
}

/* Subtle grid lines texture */
.panel-left::after {
    content:'';
    position:absolute;inset:0;
    background-image:
        linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);
    background-size:48px 48px;
    pointer-events:none;
}

.left-inner { position:relative;z-index:1;display:flex;flex-direction:column;height:100%; }

.left-logo {
    display:flex;align-items:center;gap:12px;
}
.left-logo-icon {
    width:42px;height:42px;background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:20px;
    backdrop-filter:blur(4px);
}
.left-logo-text { display:flex;flex-direction:column; }
.left-logo-name { font-size:18px;font-weight:800;color:#fff;letter-spacing:-.3px; }
.left-logo-label { font-size:10px;font-weight:700;color:rgba(255,255,255,.5);letter-spacing:1.5px;text-transform:uppercase; }

.left-hero { flex:1;display:flex;flex-direction:column;justify-content:center;padding:48px 0 32px; }
.left-headline {
    font-size:clamp(26px,3vw,36px);font-weight:800;
    color:#fff;line-height:1.2;margin-bottom:16px;letter-spacing:-.5px;
}
.left-headline span { color:var(--cyan-400); }
.left-sub { font-size:14px;color:rgba(255,255,255,.6);line-height:1.7;max-width:340px;margin-bottom:40px; }

/* Feature cards */
.feature-list { display:flex;flex-direction:column;gap:12px; }
.feature-card {
    display:flex;align-items:center;gap:14px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.1);
    border-radius:14px;
    padding:16px 18px;
    backdrop-filter:blur(8px);
    transition:background .2s;
}
.feature-card:hover { background:rgba(255,255,255,.1); }
.feature-icon {
    width:40px;height:40px;min-width:40px;
    background:rgba(34,211,238,.15);border:1px solid rgba(34,211,238,.25);
    border-radius:10px;display:flex;align-items:center;justify-content:center;
}
.feature-icon svg { color:var(--cyan-400); }
.feature-text h4 { font-size:13.5px;font-weight:700;color:#fff;margin-bottom:2px; }
.feature-text p  { font-size:12px;color:rgba(255,255,255,.5); }

.left-footer {
    font-size:11.5px;color:rgba(255,255,255,.35);
    padding-top:24px;border-top:1px solid rgba(255,255,255,.08);
}

/* ── RIGHT PANEL ─────────────────────────────────────────── */
.panel-right {
    display:flex;align-items:center;justify-content:center;
    min-height:100vh;
    background:var(--slate-100);
    padding:40px 32px;
}

.form-wrap { width:100%;max-width:420px; }

.form-card {
    background:#fff;
    border-radius:24px;
    padding:36px 36px 32px;
    box-shadow:0 4px 6px -1px rgba(0,0,0,.05),0 10px 40px -8px rgba(0,0,0,.12);
    border:1px solid var(--slate-200);
}

.form-title {
    font-size:22px;font-weight:800;color:var(--slate-900);
    margin-bottom:6px;letter-spacing:-.4px;
}
.form-sub { font-size:13px;color:var(--slate-500);margin-bottom:28px;line-height:1.5; }

/* Alert */
.alert {
    padding:12px 14px;border-radius:10px;font-size:13px;font-weight:600;
    margin-bottom:20px;display:flex;align-items:flex-start;gap:8px;
}
.alert-err  { background:var(--red-50);border:1px solid var(--red-200);color:var(--red-700); }
.alert-warn { background:var(--amber-50);border:1px solid var(--amber-200);color:var(--amber-800); }

/* Form groups */
.fg { margin-bottom:18px; }
.fg label {
    display:block;font-size:12.5px;font-weight:700;
    color:var(--slate-700);margin-bottom:7px;letter-spacing:.01em;
}
.input-wrap {
    position:relative;display:flex;align-items:center;
}
.input-icon {
    position:absolute;left:13px;top:50%;transform:translateY(-50%);
    color:var(--slate-400);pointer-events:none;display:flex;
}
.fg input[type=email],
.fg input[type=password],
.fg input[type=text] {
    width:100%;padding:11px 42px 11px 40px;
    border:1.5px solid var(--slate-200);border-radius:10px;
    font-size:14px;font-family:inherit;
    background:var(--slate-50);color:var(--slate-800);
    transition:border-color .18s,box-shadow .18s;
}
.fg input:focus {
    outline:none;border-color:var(--cyan-500);
    box-shadow:0 0 0 3px rgba(6,182,212,.12);background:#fff;
}
.fg input::placeholder { color:var(--slate-400); }

/* Password toggle */
.pw-toggle {
    position:absolute;right:12px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;padding:4px;
    color:var(--slate-400);display:flex;border-radius:6px;
    transition:color .15s,background .15s;
}
.pw-toggle:hover { color:var(--slate-600);background:var(--slate-100); }

/* Remember + Forgot row */
.form-meta {
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:22px;margin-top:-4px;
}
.remember {
    display:flex;align-items:center;gap:8px;
    font-size:13px;color:var(--slate-600);cursor:pointer;user-select:none;
}
.remember input[type=checkbox] {
    width:16px;height:16px;border-radius:4px;
    accent-color:var(--cyan-600);cursor:pointer;flex-shrink:0;
}
.forgot-link {
    font-size:13px;font-weight:600;
    color:var(--cyan-600);text-decoration:none;
    transition:color .15s;
}
.forgot-link:hover { color:var(--cyan-400); }

/* Submit button */
.btn-submit {
    width:100%;padding:13px 20px;
    background:var(--slate-900);color:#fff;
    border:none;border-radius:12px;
    font-size:15px;font-weight:700;font-family:inherit;
    cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
    transition:background .18s,transform .1s;position:relative;
    letter-spacing:-.1px;
}
.btn-submit:hover { background:var(--slate-700); }
.btn-submit:active { transform:scale(.99); }
.btn-submit.loading .btn-arrow { display:none; }
.btn-submit .spinner { display:none; }
.btn-submit.loading .spinner {
    display:block;
    width:18px;height:18px;border:2.5px solid rgba(255,255,255,.3);
    border-top-color:#fff;border-radius:50%;
    animation:spin .65s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

/* Footer below card */
.form-footer {
    text-align:center;margin-top:20px;
    font-size:12.5px;color:var(--slate-500);line-height:1.9;
}
.form-footer a { color:var(--cyan-600);text-decoration:none;font-weight:600; }
.form-footer a:hover { color:var(--cyan-400); }

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width:768px) {
    body { grid-template-columns:1fr; }
    .panel-left { display:none; }
    .panel-right { padding:32px 20px;align-items:flex-start;padding-top:48px; }

    /* Show compact logo on mobile above card */
    .panel-right::before {
        content:'';
        display:block;
    }
    .form-wrap { max-width:100%; }
    .mobile-logo {
        display:flex !important;
    }
}

.mobile-logo {
    display:none;
    align-items:center;gap:10px;
    margin-bottom:28px;
}
.mobile-logo-icon {
    width:38px;height:38px;background:var(--slate-900);
    border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;
}
.mobile-logo-name { font-size:20px;font-weight:800;color:var(--slate-900); }
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="panel-left">
<div class="left-inner">

    <!-- Logo -->
    <div class="left-logo">
        <div class="left-logo-icon">🏨</div>
        <div class="left-logo-text">
            <span class="left-logo-name">StayGo</span>
            <span class="left-logo-label">Partner Portal</span>
        </div>
    </div>

    <!-- Hero -->
    <div class="left-hero">
        <h1 class="left-headline">
            Quản lý khách sạn<br><span>thông minh hơn</span>
        </h1>
        <p class="left-sub">
            Nền tảng dành cho đối tác khách sạn — theo dõi đặt phòng, doanh thu và vận hành trong một nơi duy nhất.
        </p>

        <div class="feature-list">
            <div class="feature-card">
                <div class="feature-icon">
                    <!-- Calendar icon -->
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h4>Quản lý đặt phòng</h4>
                    <p>Xem &amp; xử lý đơn theo thời gian thực</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <!-- Bar chart icon -->
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6"  y1="20" x2="6"  y2="14"/>
                        <line x1="2"  y1="20" x2="22" y2="20"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h4>Báo cáo doanh thu</h4>
                    <p>Phân tích chi tiết từng ngày, tháng, năm</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <!-- Shield icon -->
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h4>Bảo mật cao</h4>
                    <p>Dữ liệu được mã hóa và bảo vệ tuyệt đối</p>
                </div>
            </div>
        </div>
    </div>

    <div class="left-footer">© 2026 StayGo. All rights reserved.</div>

</div>
</div>

<!-- RIGHT PANEL -->
<div class="panel-right">
<div class="form-wrap">

    <!-- Mobile logo (hidden on desktop) -->
    <div class="mobile-logo">
        <div class="mobile-logo-icon">🏨</div>
        <span class="mobile-logo-name">StayGo</span>
    </div>

    <div class="form-card">
        <div class="form-title">Đăng nhập đối tác</div>
        <div class="form-sub">Quản lý đặt phòng, doanh thu và thông tin khách sạn của bạn</div>

        <?php if ($suspend_msg): ?>
        <div class="alert alert-warn">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <?= htmlspecialchars($suspend_msg) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-err">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <?= csrf_field() ?>

            <!-- Email -->
            <div class="fg">
                <label for="email">Email đối tác</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email"
                           placeholder="partner@khachsan.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <!-- Password -->
            <div class="fg">
                <label for="password">Mật khẩu</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required>
                    <button type="button" class="pw-toggle" id="pwToggle" aria-label="Hiện/ẩn mật khẩu">
                        <!-- Eye icon (shown when password hidden) -->
                        <svg id="iconEye" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <!-- Eye-off icon (shown when password visible) -->
                        <svg id="iconEyeOff" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                            <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Remember + Forgot -->
            <div class="form-meta">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    Ghi nhớ đăng nhập
                </label>
                <a href="/hotel/forgot_password.php" class="forgot-link">Quên mật khẩu?</a>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-submit" id="btnSubmit">
                <span class="btn-label">Đăng nhập</span>
                <span class="btn-arrow">
                    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </span>
                <span class="spinner"></span>
            </button>
        </form>
    </div>

    <div class="form-footer">
        Chưa có tài khoản? <a href="#">Liên hệ Admin</a> để được cấp quyền truy cập.<br>
        <a href="/">← Về trang chủ StayGo</a>
    </div>

</div>
</div>

<script>
// Show/hide password
(function(){
    var inp = document.getElementById('password');
    var btn = document.getElementById('pwToggle');
    var eye = document.getElementById('iconEye');
    var off = document.getElementById('iconEyeOff');
    btn.addEventListener('click', function(){
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        eye.style.display = show ? 'none'  : '';
        off.style.display = show ? ''      : 'none';
    });
})();

// Loading state on submit
document.getElementById('loginForm').addEventListener('submit', function(){
    var btn = document.getElementById('btnSubmit');
    btn.classList.add('loading');
    btn.disabled = true;
    btn.querySelector('.btn-label').textContent = 'Đang đăng nhập...';
});
</script>
</body>
</html>
