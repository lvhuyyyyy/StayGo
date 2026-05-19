<?php
$page_title    = 'Payment Engine AI';
$page_subtitle = 'Trợ lý AI quản lý luồng thanh toán & tài chính';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.pai-wrap   { display:flex; gap:20px; height:calc(100vh - 160px); min-height:520px; }
.pai-side   { width:220px; flex-shrink:0; display:flex; flex-direction:column; gap:10px; }
.pai-panel  { flex:1; display:flex; flex-direction:column; background:#fff; border-radius:16px;
              box-shadow:0 2px 12px rgba(0,0,0,.08); overflow:hidden; }

/* Mode buttons */
.pai-mode-btn {
    display:flex; align-items:center; gap:10px; padding:13px 14px;
    border-radius:12px; border:2px solid #e2e8f0; background:#fff;
    cursor:pointer; font-size:13px; font-weight:600; color:#4a5568;
    transition:.15s; text-align:left; width:100%;
}
.pai-mode-btn:hover  { border-color:#3182ce; background:#ebf8ff; color:#2b6cb0; }
.pai-mode-btn.active { border-color:#3182ce; background:linear-gradient(135deg,#ebf8ff,#dbeafe); color:#1e40af; }
.pai-mode-icon { font-size:20px; flex-shrink:0; }
.pai-mode-label { font-size:12px; color:#718096; font-weight:400; margin-top:1px; }

/* Info card */
.pai-info {
    background:linear-gradient(135deg,#0f2444,#1e3a5f);
    border-radius:12px; padding:14px; color:#fff; font-size:12px; line-height:1.6;
}
.pai-info h4 { font-size:13px; font-weight:700; margin:0 0 8px; opacity:.9; }
.pai-info li { opacity:.75; margin-bottom:3px; }

/* Chat area */
.pai-chat {
    flex:1; overflow-y:auto; padding:20px; display:flex;
    flex-direction:column; gap:14px; background:#f8fafc;
}
.pai-msg { display:flex; gap:10px; max-width:88%; }
.pai-msg.user  { align-self:flex-end; flex-direction:row-reverse; }
.pai-bubble {
    padding:12px 16px; border-radius:14px; font-size:13.5px; line-height:1.65;
    white-space:pre-wrap; word-break:break-word;
}
.pai-msg.assistant .pai-bubble { background:#fff; border:1px solid #e2e8f0; color:#1a202c; border-radius:4px 14px 14px 14px; }
.pai-msg.user      .pai-bubble { background:linear-gradient(135deg,#1a365d,#2b6cb0); color:#fff; border-radius:14px 4px 14px 14px; }
.pai-avatar {
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700;
}
.pai-msg.assistant .pai-avatar { background:linear-gradient(135deg,#1a365d,#3182ce); color:#fff; }
.pai-msg.user      .pai-avatar { background:linear-gradient(135deg,#718096,#4a5568); color:#fff; }

/* Typing */
.typing-dots { display:inline-flex; gap:4px; align-items:center; padding:4px 0; }
.typing-dots span {
    width:7px; height:7px; border-radius:50%; background:#94a3b8;
    animation:bounce .9s infinite; display:inline-block;
}
.typing-dots span:nth-child(2) { animation-delay:.2s; }
.typing-dots span:nth-child(3) { animation-delay:.4s; }
@keyframes bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-7px)} }

/* Chips */
.pai-chips { display:flex; flex-wrap:wrap; gap:7px; padding:10px 20px 0; }
.pai-chip {
    padding:5px 12px; border-radius:20px; background:#eef2ff; color:#3730a3;
    font-size:12px; font-weight:600; cursor:pointer; border:1.5px solid #c7d2fe;
    transition:.12s; white-space:nowrap;
}
.pai-chip:hover { background:#c7d2fe; }

/* Input bar */
.pai-bar {
    padding:14px 16px; border-top:1px solid #e2e8f0;
    display:flex; gap:10px; align-items:flex-end; background:#fff;
}
.pai-bar textarea {
    flex:1; border:1.5px solid #e2e8f0; border-radius:10px; padding:10px 14px;
    font-size:13.5px; font-family:inherit; resize:none; outline:none;
    min-height:42px; max-height:130px; line-height:1.5; transition:border-color .2s;
}
.pai-bar textarea:focus { border-color:#3182ce; box-shadow:0 0 0 3px rgba(49,130,206,.1); }
.pai-send {
    width:40px; height:40px; border-radius:10px; border:none; cursor:pointer;
    background:linear-gradient(135deg,#1a365d,#3182ce); color:#fff;
    font-size:17px; display:flex; align-items:center; justify-content:center;
    transition:.15s; flex-shrink:0;
}
.pai-send:hover { opacity:.88; transform:scale(1.04); }
.pai-send:disabled { opacity:.45; cursor:not-allowed; transform:none; }

/* Markdown */
.pai-bubble h1,.pai-bubble h2,.pai-bubble h3 { font-weight:700; margin:.6em 0 .3em; }
.pai-bubble h1 { font-size:16px; } .pai-bubble h2 { font-size:15px; } .pai-bubble h3 { font-size:14px; }
.pai-bubble ul,.pai-bubble ol { padding-left:18px; margin:.3em 0; }
.pai-bubble li { margin:.15em 0; }
.pai-bubble code { background:#f1f5f9; padding:1px 5px; border-radius:4px; font-size:12.5px; font-family:monospace; }
.pai-bubble pre  { background:#1e293b; color:#e2e8f0; padding:10px 14px; border-radius:8px; overflow-x:auto; font-size:12px; }
.pai-bubble pre code { background:none; padding:0; color:inherit; }
.pai-bubble blockquote { border-left:3px solid #3182ce; padding-left:10px; color:#718096; margin:.4em 0; }
.pai-bubble strong { font-weight:700; }
.pai-bubble em { font-style:italic; }
.pai-bubble hr { border:none; border-top:1px solid #e2e8f0; margin:.5em 0; }
.pai-msg.user .pai-bubble code { background:rgba(255,255,255,.2); }
</style>

<?php
// Badge: refund pending
$refund_pending = (int)($conn->query("SELECT COUNT(*) c FROM bookings WHERE refund_requested=1")->fetch_assoc()['c'] ?? 0);
?>

<div class="pai-wrap">

    <!-- Sidebar modes -->
    <div class="pai-side">

        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#a0aec0;padding:0 2px;margin-bottom:2px">Chế độ AI</div>

        <button class="pai-mode-btn active" data-mode="engine" onclick="switchMode(this,'engine')">
            <span class="pai-mode-icon">💳</span>
            <div>
                <div>Payment Engine</div>
                <div class="pai-mode-label">Tổng quan hệ thống</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="flow" onclick="switchMode(this,'flow')">
            <span class="pai-mode-icon">🔄</span>
            <div>
                <div>Luồng 4 Bước</div>
                <div class="pai-mode-label">Order → PSP → Ledger</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="coupon" onclick="switchMode(this,'coupon')">
            <span class="pai-mode-icon">🎟️</span>
            <div>
                <div>Coupon & Điểm</div>
                <div class="pai-mode-label">Validate & tính giá</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="fraud" onclick="switchMode(this,'fraud')">
            <span class="pai-mode-icon">🛡️</span>
            <div>
                <div>Fraud Detection</div>
                <div class="pai-mode-label">Scoring & điều tra</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="auth" onclick="switchMode(this,'auth')">
            <span class="pai-mode-icon">🔐</span>
            <div>
                <div>3DS2 & OTP</div>
                <div class="pai-mode-label">Xác thực bảo mật</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="refund" onclick="switchMode(this,'refund')">
            <span class="pai-mode-icon">↩️</span>
            <div>
                <div>Hoàn tiền</div>
                <div class="pai-mode-label">5 chính sách & routing</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="chargeback" onclick="switchMode(this,'chargeback')">
            <span class="pai-mode-icon">⚖️</span>
            <div>
                <div>Chargeback Defense</div>
                <div class="pai-mode-label">Visa/MC evidence package</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="payout" onclick="switchMode(this,'payout')">
            <span class="pai-mode-icon">🏦</span>
            <div>
                <div>Payout Partner</div>
                <div class="pai-mode-label">Kỳ TT & NAPAS 247</div>
            </div>
        </button>

        <button class="pai-mode-btn" data-mode="recon" onclick="switchMode(this,'recon')">
            <span class="pai-mode-icon">📊</span>
            <div>
                <div>EOD Recon & P&L</div>
                <div class="pai-mode-label">3 nguồn & KPI tài chính</div>
            </div>
        </button>

        <div style="margin-top:4px"></div>

        <div class="pai-info">
            <h4>💳 Payment Engine</h4>
            <ul style="padding-left:14px;margin:0">
                <li>PCI DSS tokenization</li>
                <li>Idempotency key</li>
                <li>Double-entry ledger</li>
                <li>HOLDING→READY→PAID</li>
                <li>Coupon fail-fast (7 bước)</li>
                <li>Fraud scoring 100 điểm</li>
                <li>3DS2 frictionless/challenge</li>
            </ul>
        </div>

        <?php if ($refund_pending > 0): ?>
        <div style="background:#fff5f5;border:1.5px solid #feb2b2;border-radius:10px;padding:10px 12px;font-size:12px;color:#c53030;font-weight:600">
            ⚠️ <?= $refund_pending ?> yêu cầu hoàn tiền đang chờ xử lý
        </div>
        <?php endif; ?>
    </div>

    <!-- Chat panel -->
    <div class="pai-panel">

        <!-- Quick chips -->
        <div class="pai-chips" id="chips-engine">
            <span class="pai-chip" onclick="chip(this)">Kiểm tra giao dịch pending hôm nay</span>
            <span class="pai-chip" onclick="chip(this)">Phân tích phương thức thanh toán phổ biến</span>
            <span class="pai-chip" onclick="chip(this)">Tại sao có giao dịch thất bại?</span>
            <span class="pai-chip" onclick="chip(this)">Quy trình xử lý hoàn tiền</span>
        </div>
        <div class="pai-chips" id="chips-flow" style="display:none">
            <span class="pai-chip" onclick="chip(this)">So sánh VNPay vs MoMo vs PayOS</span>
            <span class="pai-chip" onclick="chip(this)">Giải thích idempotency trong thanh toán</span>
            <span class="pai-chip" onclick="chip(this)">Double-entry ledger hoạt động thế nào?</span>
            <span class="pai-chip" onclick="chip(this)">Xử lý khi PSP timeout</span>
        </div>
        <div class="pai-chips" id="chips-coupon" style="display:none">
            <span class="pai-chip" onclick="chip(this)">Validate mã SUMMER25 cho đơn 1,500,000đ</span>
            <span class="pai-chip" onclick="chip(this)">Giải thích 7 bước fail-fast validation</span>
            <span class="pai-chip" onclick="chip(this)">Cách tính điểm thưởng FIFO</span>
            <span class="pai-chip" onclick="chip(this)">Coupon stackable là gì?</span>
        </div>
        <div class="pai-chips" id="chips-fraud" style="display:none">
            <span class="pai-chip" onclick="chip(this)">Tính risk score giao dịch 8tr từ IP Tor</span>
            <span class="pai-chip" onclick="chip(this)">Xử lý khi phát hiện chargeback fraud</span>
            <span class="pai-chip" onclick="chip(this)">Điều tra tài khoản có 3 lần chargeback</span>
            <span class="pai-chip" onclick="chip(this)">False positive là gì và cách xử lý?</span>
        </div>
        <div class="pai-chips" id="chips-auth" style="display:none">
            <span class="pai-chip" onclick="chip(this)">So sánh frictionless vs challenge flow 3DS2</span>
            <span class="pai-chip" onclick="chip(this)">OTP hết hạn 5 phút — thiết kế thế nào?</span>
            <span class="pai-chip" onclick="chip(this)">Khi nào dùng Biometric thay OTP?</span>
            <span class="pai-chip" onclick="chip(this)">Chống replay attack trong OTP</span>
        </div>
        <div class="pai-chips" id="chips-refund" style="display:none">
            <span class="pai-chip" onclick="chip(this)">Tính refund cho booking confirmed hủy trước 2 ngày</span>
            <span class="pai-chip" onclick="chip(this)">Hoàn tiền MoMo vs VNPAY khác nhau thế nào?</span>
            <span class="pai-chip" onclick="chip(this)">NON_REFUNDABLE có ngoại lệ không?</span>
            <span class="pai-chip" onclick="chip(this)">Quy trình approval 4 cấp theo số tiền</span>
        </div>
        <div class="pai-chips" id="chips-chargeback" style="display:none">
            <span class="pai-chip" onclick="chip(this)">Chargeback 4855 — khách nói không nhận dịch vụ</span>
            <span class="pai-chip" onclick="chip(this)">Chuẩn bị evidence package để fight chargeback</span>
            <span class="pai-chip" onclick="chip(this)">Khi nào nên accept thay vì fight?</span>
            <span class="pai-chip" onclick="chip(this)">Chargeback rate vượt 1% thì sao?</span>
        </div>
        <div class="pai-chips" id="chips-payout" style="display:none">
            <span class="pai-chip" onclick="chip(this)">Khách sạn nào đang chờ giải ngân?</span>
            <span class="pai-chip" onclick="chip(this)">6 reconciliation checks trước khi chuyển</span>
            <span class="pai-chip" onclick="chip(this)">Khi nào dùng NAPAS 247 vs SWIFT?</span>
            <span class="pai-chip" onclick="chip(this)">Xử lý khi NAPAS 247 thất bại 3 lần</span>
        </div>
        <div class="pai-chips" id="chips-recon" style="display:none">
            <span class="pai-chip" onclick="chip(this)">EOD hôm nay có chênh lệch không?</span>
            <span class="pai-chip" onclick="chip(this)">Take rate tháng này đang ở mức nào?</span>
            <span class="pai-chip" onclick="chip(this)">Giải thích MYSTERY_TRANSACTION flag</span>
            <span class="pai-chip" onclick="chip(this)">Lập P&L statement tháng này</span>
        </div>

        <!-- Messages -->
        <div class="pai-chat" id="chatBox">
            <div class="pai-msg assistant">
                <div class="pai-avatar">💳</div>
                <div class="pai-bubble">
Xin chào! Tôi là **AI Payment Engine** của nền tảng StayGo.<br><br>
Tôi có thể giúp bạn với 7 chế độ:<br>
• **💳 Payment Engine** — Tổng quan entities, PCI DSS, payout lifecycle, dữ liệu live<br>
• **🔄 Luồng 4 Bước** — Order khởi tạo → PSP → callback verify → double-entry ledger<br>
• **🎟️ Coupon & Điểm** — 7-bước fail-fast validate, tính giá theo thứ tự ưu tiên, FIFO points<br>
• **🛡️ Fraud Detection** — Scoring matrix 100 điểm, Allow/Review/Challenge/Block, điều tra post-fraud<br>
• **🔐 3DS2 & OTP** — Frictionless vs Challenge flow, OTP replay-proof, Biometric mobile<br>
• **↩️ Hoàn tiền** — 5 loại chính sách, routing về PTTT gốc, approval workflow 4 cấp<br>
• **⚖️ Chargeback Defense** — Phân loại Visa/MC reason codes, evidence package, fight/accept strategy<br>
• **🏦 Payout Partner** — Tính kỳ payout, 6 reconciliation checks, NAPAS 247 API, ledger entries<br>
• **📊 EOD Recon & P&L** — Đối soát 3 nguồn (OTA/PSP/Bank), P&L Statement, KPI Take Rate/Cancel/Success<br><br>
Chọn chế độ bên trái và đặt câu hỏi để bắt đầu!
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="pai-bar">
            <textarea id="msgInput" placeholder="Đặt câu hỏi về luồng thanh toán, coupon, payout..." rows="1"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"
                      oninput="autoResize(this)"></textarea>
            <button class="pai-send" id="sendBtn" onclick="sendMsg()" title="Gửi (Enter)">➤</button>
        </div>
    </div>
</div>

<script>
let currentMode = 'engine';
let chatHistory = [];

function switchMode(btn, mode) {
    document.querySelectorAll('.pai-mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentMode = mode;
    chatHistory = [];
    document.querySelectorAll('[id^="chips-"]').forEach(el => el.style.display = 'none');
    document.getElementById('chips-' + mode).style.display = 'flex';
    addMsg('assistant', 'Đã chuyển sang chế độ **' + btn.querySelector('div > div:first-child').textContent + '**. Hãy đặt câu hỏi!');
}

function chip(el) {
    document.getElementById('msgInput').value = el.textContent.trim();
    sendMsg();
}

function autoResize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 130) + 'px';
}

function addMsg(role, text) {
    const box = document.getElementById('chatBox');
    const wrap = document.createElement('div');
    wrap.className = 'pai-msg ' + role;
    const icon = role === 'assistant' ? '💳' : '👤';
    wrap.innerHTML =
        '<div class="pai-avatar">' + icon + '</div>' +
        '<div class="pai-bubble">' + (role === 'assistant' ? renderMd(text) : escHtml(text)) + '</div>';
    box.appendChild(wrap);
    box.scrollTop = box.scrollHeight;
    return wrap;
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function renderMd(text) {
    let h = escHtml(text);
    h = h.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
    h = h.replace(/^#{3} (.+)$/gm, '<h3>$1</h3>');
    h = h.replace(/^#{2} (.+)$/gm, '<h2>$1</h2>');
    h = h.replace(/^#{1} (.+)$/gm, '<h1>$1</h1>');
    h = h.replace(/^[-─━]{3,}$/gm, '<hr>');
    h = h.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    h = h.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    h = h.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
    h = h.replace(/^[•\-\*] (.+)$/gm, '<li>$1</li>');
    h = h.replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>');
    h = h.replace(/<\/ul>\s*<ul>/g, '');
    h = h.replace(/\n/g, '<br>');
    h = h.replace(/<br>(<h[123]|<hr|<ul|<blockquote)/g, '$1');
    h = h.replace(/(<\/h[123]>|<\/ul>|<hr>)<br>/g, '$1');
    return h;
}

function showTyping() {
    const box = document.getElementById('chatBox');
    const wrap = document.createElement('div');
    wrap.className = 'pai-msg assistant';
    wrap.id = 'typing';
    wrap.innerHTML = '<div class="pai-avatar">💳</div><div class="pai-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
    box.appendChild(wrap);
    box.scrollTop = box.scrollHeight;
}

async function sendMsg() {
    const inp = document.getElementById('msgInput');
    const msg = inp.value.trim();
    if (!msg) return;

    inp.value = '';
    inp.style.height = 'auto';
    document.getElementById('sendBtn').disabled = true;

    addMsg('user', msg);
    showTyping();

    const histSnap = chatHistory.slice(-10).map(h => ({role:h.role, content:h.content.slice(0,800)}));

    try {
        const res = await fetch('/api/payment_ai_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ message: msg, mode: currentMode, history: histSnap })
        });
        const data = await res.json();
        const reply = data.reply || 'Không có phản hồi.';

        document.getElementById('typing')?.remove();
        addMsg('assistant', reply);

        chatHistory.push({ role:'user', content: msg });
        chatHistory.push({ role:'assistant', content: reply });
        if (chatHistory.length > 20) chatHistory = chatHistory.slice(-20);
    } catch(e) {
        document.getElementById('typing')?.remove();
        addMsg('assistant', '⚠️ Lỗi kết nối. Vui lòng thử lại.');
    }

    document.getElementById('sendBtn').disabled = false;
    document.getElementById('msgInput').focus();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
