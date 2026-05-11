<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/../includes/security.php';

$email = trim($_GET['email'] ?? '');
$msg   = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $otp = trim($_POST['otp'] ?? '');

    if (!$email || !preg_match('/^\d{6}$/', $otp)) {
        $msg   = 'Mã OTP không hợp lệ.';
        $error = true;
    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ? AND otp_code = ? AND otp_expire > NOW()"
        );
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($found) {
            // Xoá OTP đã dùng
            $del = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expire = NULL WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();
            $del->close();

            header("Location: reset_password.php?email=" . urlencode($email));
            exit();
        } else {
            $msg   = 'Mã OTP sai hoặc đã hết hạn. Vui lòng thử lại.';
            $error = true;
        }
    }
}

$page_title = 'Xác nhận OTP - StayGo';
include '../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/auth.css">
<style>
.otp-wrapper{display:flex;align-items:center;justify-content:center;min-height:60vh;padding:40px 16px}
.otp-card{background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.10);padding:48px 40px 40px;max-width:440px;width:100%;text-align:center}
.otp-card .card-icon{width:64px;height:64px;background:#ebf4ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
.otp-card h2{font-size:22px;font-weight:800;color:#1e3a5f;margin:0 0 8px}
.otp-card .sub{font-size:14px;color:#718096;margin:0 0 28px;line-height:1.6}
.otp-inputs{display:flex;gap:10px;justify-content:center;margin-bottom:24px}
.otp-input{width:52px;height:60px;font-size:28px;font-weight:700;text-align:center;border:2px solid #e2e8f0;border-radius:12px;color:#1e3a5f;outline:none;transition:.2s}
.otp-input:focus{border-color:#1e73be;box-shadow:0 0 0 3px rgba(30,115,190,.12)}
.otp-card button[type=submit]{width:100%;padding:14px;background:linear-gradient(135deg,#1e3a5f,#1e73be);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s}
.otp-card button[type=submit]:hover{opacity:.9}
.otp-msg{margin-top:16px;padding:10px 16px;border-radius:8px;font-size:13px}
.otp-msg.error{background:#fff5f5;color:#c53030;border:1px solid #fed7d7}
#timer{font-size:22px;font-weight:800;color:#1e73be;margin-bottom:20px}
#timer.expired{color:#c53030}
.resend-row{margin-top:16px;font-size:13px;color:#718096}
.resend-row a{color:#1e73be;font-weight:600;text-decoration:none}
.back-login{margin-top:20px}
.back-btn{display:inline-flex;align-items:center;gap:6px;color:#718096;font-size:13px;text-decoration:none}
.back-btn:hover{color:#1e73be}
</style>

<div class="otp-wrapper">
    <div class="otp-card">

        <div class="card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="#1e73be" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0l-9.75 6.75L2.25 6.75"/>
            </svg>
        </div>

        <h2>Nhập mã xác nhận</h2>
        <p class="sub">Mã OTP 6 chữ số đã được gửi đến<br><strong><?= htmlspecialchars($email) ?></strong></p>

        <div id="timer">15:00</div>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="otp-inputs">
                <input maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                <input maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]">
                <input maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]">
                <input maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]">
                <input maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]">
                <input maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]">
            </div>
            <input type="hidden" name="otp" id="otp">
            <button type="submit">Xác nhận</button>
        </form>

        <?php if ($msg): ?>
            <div class="otp-msg <?= $error ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="resend-row">
            Chưa nhận được mã? <a href="forgot_password.php">Gửi lại</a>
        </div>

        <div class="back-login">
            <a href="login.php" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Quay lại đăng nhập
            </a>
        </div>

    </div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-input');
inputs.forEach((input, i) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/, '');
        if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
        syncOtp();
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
    });
    input.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...text].slice(0, 6).forEach((ch, j) => { if (inputs[j]) inputs[j].value = ch; });
        syncOtp();
        const next = inputs[Math.min(text.length, 5)];
        if (next) next.focus();
    });
});
function syncOtp() {
    let code = '';
    inputs.forEach(i => code += i.value);
    document.getElementById('otp').value = code;
}

let time = 900;
const timerEl = document.getElementById('timer');
const iv = setInterval(() => {
    time--;
    if (time <= 0) {
        clearInterval(iv);
        timerEl.textContent = 'Hết hạn';
        timerEl.classList.add('expired');
        return;
    }
    const m = Math.floor(time / 60), s = time % 60;
    timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
}, 1000);
</script>

<?php include '../includes/footer.php'; ?>
