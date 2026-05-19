<?php
$page_title    = 'AI Assistant';
$page_subtitle = 'Trợ lý AI quản lý khách sạn';
require_once __DIR__ . '/../includes/hotel_header.php';
?>

<style>
/* ── Hotel AI layout ─────────────────────────────────────────────────────── */
.hai-wrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 130px);
}

/* Mode tabs */
.hai-modes {
    display: flex;
    gap: 8px;
    padding-bottom: 14px;
    flex-wrap: wrap;
}
.hai-mode-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    transition: all .18s;
}
.hai-mode-btn:hover { border-color: #3182ce; color: #3182ce; background: #ebf8ff; }
.hai-mode-btn.active {
    border-color: #3182ce;
    background: linear-gradient(135deg, #1a365d, #2c5282);
    color: #fff;
    box-shadow: 0 4px 14px rgba(49,130,206,.35);
}
.hai-mode-btn .mode-icon { font-size: 17px; line-height: 1; }

/* Chat card */
.hai-card {
    flex: 1;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}
.hai-mode-desc {
    padding: 10px 20px;
    background: linear-gradient(135deg, #ebf8ff, #e6fffa);
    border-bottom: 1px solid #bee3f8;
    font-size: 12.5px;
    color: #2b6cb0;
    font-weight: 500;
}

/* Messages */
.hai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
    scroll-behavior: smooth;
}
.hai-messages::-webkit-scrollbar { width: 5px; }
.hai-messages::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }

.hmsg { display: flex; gap: 10px; align-items: flex-start; }
.hmsg.user { flex-direction: row-reverse; }
.hmsg-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0; font-weight: 700;
}
.hmsg.user .hmsg-avatar { background: linear-gradient(135deg,#1a365d,#3182ce); color:#fff; font-size:11px; }
.hmsg.ai   .hmsg-avatar { background: #ebf8ff; font-size: 18px; }
.hmsg-bubble {
    max-width: 78%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.65;
    word-break: break-word;
}
.hmsg.user .hmsg-bubble {
    background: linear-gradient(135deg,#1a365d,#2c5282);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.hmsg.ai .hmsg-bubble {
    background: #f7fafc;
    color: #2d3748;
    border-bottom-left-radius: 4px;
    border: 1px solid #e2e8f0;
}

/* Markdown in AI bubble */
.hmsg.ai .hmsg-bubble h1,
.hmsg.ai .hmsg-bubble h2,
.hmsg.ai .hmsg-bubble h3 { font-size: 14px; font-weight: 700; margin: 10px 0 4px; color: #1a365d; }
.hmsg.ai .hmsg-bubble strong { font-weight: 700; color: #1a202c; }
.hmsg.ai .hmsg-bubble em { font-style: italic; }
.hmsg.ai .hmsg-bubble ul, .hmsg.ai .hmsg-bubble ol { padding-left: 18px; margin: 6px 0; }
.hmsg.ai .hmsg-bubble li { margin-bottom: 3px; }
.hmsg.ai .hmsg-bubble code { background:#ebf8ff; border-radius:4px; padding:1px 5px; font-family:monospace; font-size:12px; color:#2b6cb0; }
.hmsg.ai .hmsg-bubble p { margin: 5px 0; }
.hmsg.ai .hmsg-bubble hr { border:none; border-top:1px solid #e2e8f0; margin:8px 0; }
.hmsg.ai .hmsg-bubble blockquote { border-left:3px solid #3182ce; padding-left:10px; color:#718096; margin:6px 0; }

/* Typing */
.tdot { width:7px; height:7px; background:#3182ce; border-radius:50%; display:inline-block; animation:tdotB 1.2s infinite ease-in-out; }
.tdot:nth-child(2){animation-delay:.2s} .tdot:nth-child(3){animation-delay:.4s}
@keyframes tdotB { 0%,60%,100%{transform:translateY(0);opacity:.4} 30%{transform:translateY(-6px);opacity:1} }

/* Input area */
.hai-input-area {
    border-top: 1px solid #edf2f7;
    padding: 14px 16px;
    display: flex;
    gap: 10px;
    align-items: flex-end;
    background: #fff;
}
.hai-textarea {
    flex: 1;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 13.5px;
    resize: none;
    min-height: 44px;
    max-height: 140px;
    line-height: 1.5;
    font-family: inherit;
    outline: none;
    transition: border-color .15s;
    overflow-y: auto;
}
.hai-textarea:focus { border-color: #3182ce; }
.hai-send-btn {
    width: 44px; height: 44px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg,#1a365d,#3182ce);
    color: #fff;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: opacity .15s, transform .1s;
    flex-shrink: 0;
}
.hai-send-btn:hover  { opacity: .88; }
.hai-send-btn:active { transform: scale(.95); }
.hai-send-btn:disabled { opacity: .45; cursor: not-allowed; }
.hai-send-btn svg { width: 18px; height: 18px; }
.hai-clear-btn {
    background: none;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 0 12px;
    height: 44px;
    color: #718096;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
}
.hai-clear-btn:hover { border-color: #fc8181; color: #e53e3e; background: #fff5f5; }

/* Welcome */
.hai-welcome {
    flex: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 32px; gap: 12px; color: #a0aec0;
}
.hai-welcome-icon { font-size: 52px; line-height: 1; }
.hai-welcome h3 { font-size: 17px; color: #1a365d; margin: 0; font-weight: 700; }
.hai-welcome p { font-size: 13px; margin: 0; max-width: 360px; line-height: 1.6; }
.hai-quick-list { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 8px; }
.hai-quick {
    padding: 7px 14px; border-radius: 20px; border: 1.5px solid #bee3f8;
    background: #ebf8ff; color: #2b6cb0; font-size: 12.5px; font-weight: 600;
    cursor: pointer; transition: all .15s;
}
.hai-quick:hover { background: #3182ce; color: #fff; border-color: #3182ce; }
</style>

<div class="hai-wrap">

    <div class="hai-modes">
        <button class="hai-mode-btn active" data-mode="general" onclick="setMode(this)">
            <span class="mode-icon">🏨</span> Quản lý Tổng quan
        </button>
        <button class="hai-mode-btn" data-mode="pricing" onclick="setMode(this)">
            <span class="mode-icon">💰</span> Tư vấn Định giá
        </button>
        <button class="hai-mode-btn" data-mode="reviews" onclick="setMode(this)">
            <span class="mode-icon">⭐</span> Quản lý Đánh giá
        </button>
        <button class="hai-mode-btn" data-mode="report" onclick="setMode(this)">
            <span class="mode-icon">📊</span> Báo cáo Tháng
        </button>
    </div>

    <div class="hai-card">
        <div class="hai-mode-desc" id="modeDesc">
            Hỗ trợ 9 module: phòng, giá, booking, chính sách, giao tiếp, đánh giá, analytics, tài chính, hồ sơ.
        </div>

        <div class="hai-messages" id="haiMessages">
            <div class="hai-welcome" id="haiWelcome">
                <div class="hai-welcome-icon">🤖</div>
                <h3>Hotel AI Assistant</h3>
                <p>Tôi có thể phân tích dữ liệu thực tế của khách sạn bạn và đưa ra tư vấn về giá, review, báo cáo hiệu suất.</p>
                <div class="hai-quick-list" id="quickSuggestions"></div>
            </div>
        </div>

        <div class="hai-input-area">
            <button class="hai-clear-btn" onclick="clearChat()">Xóa</button>
            <textarea class="hai-textarea" id="haiInput"
                placeholder="Nhập câu hỏi... (Enter gửi, Shift+Enter xuống dòng)" rows="1"></textarea>
            <button class="hai-send-btn" id="haiSendBtn" onclick="sendMessage()">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
                </svg>
            </button>
        </div>
    </div>

</div>

<script>
const MODE_DESC = {
    general: 'Hỗ trợ 9 module: phòng, giá, booking, chính sách, giao tiếp, đánh giá, analytics, tài chính, hồ sơ.',
    pricing: 'Tư vấn chiến lược dynamic pricing theo demand, lead time và rate plan. Dùng dữ liệu thực tế của khách sạn.',
    reviews: 'Viết phản hồi đánh giá chuyên nghiệp (1-5 sao) và phân tích xu hướng feedback.',
    report:  'Báo cáo hiệu suất tháng với ADR, RevPAR, Occupancy. Tự động lấy dữ liệu thực từ hệ thống.',
};
const MODE_QUICK = {
    general: ['Cách tăng tỷ lệ lấp đầy phòng','Chính sách hủy nào phù hợp nhất?','Quy trình xử lý no-show','Tiêu chí để được badge "Được yêu thích"'],
    pricing: ['Phân tích giá phòng hiện tại của tôi','Gợi ý giá cho dịp lễ Quốc khánh 2/9','Nên tạo Early Bird hay Last Minute deal?','Rate plan nào tôi đang thiếu?'],
    reviews: ['Viết phản hồi cho đánh giá 1 sao','Template cảm ơn đánh giá 5 sao','Phân tích điểm mạnh/yếu từ review gần đây','Cách giảm tỷ lệ đánh giá thấp'],
    report:  ['Tạo báo cáo tháng này','Phân tích Occupancy và RevPAR','So sánh doanh thu tháng này với tháng trước','Kế hoạch cải thiện tháng tới'],
};

let currentMode = 'general';
let history = [];

function setMode(btn) {
    document.querySelectorAll('.hai-mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentMode = btn.dataset.mode;
    document.getElementById('modeDesc').textContent = MODE_DESC[currentMode];
    clearChat();
}

function clearChat() {
    history = [];
    document.getElementById('haiMessages').innerHTML = `
        <div class="hai-welcome" id="haiWelcome">
            <div class="hai-welcome-icon">🤖</div>
            <h3>Hotel AI Assistant</h3>
            <p>Tôi có thể phân tích dữ liệu thực tế của khách sạn bạn và đưa ra tư vấn về giá, review, báo cáo hiệu suất.</p>
            <div class="hai-quick-list" id="quickSuggestions"></div>
        </div>`;
    renderQuick();
}

function renderQuick() {
    const c = document.getElementById('quickSuggestions');
    if (!c) return;
    c.innerHTML = MODE_QUICK[currentMode]
        .map(q => `<span class="hai-quick" onclick="sendQuick(this)">${q}</span>`)
        .join('');
}

function sendQuick(el) {
    document.getElementById('haiInput').value = el.textContent;
    sendMessage();
}

function hideWelcome() {
    const w = document.getElementById('haiWelcome');
    if (w) w.remove();
}

function appendMsg(role, content) {
    const c = document.getElementById('haiMessages');
    hideWelcome();
    const div = document.createElement('div');
    div.className = `hmsg ${role}`;
    const av = document.createElement('div');
    av.className = 'hmsg-avatar';
    av.textContent = role === 'user' ? 'BẠN' : '🤖';
    const bub = document.createElement('div');
    bub.className = 'hmsg-bubble';
    bub.innerHTML = role === 'ai' ? mdToHtml(content) : escHtml(content);
    div.appendChild(av); div.appendChild(bub);
    c.appendChild(div);
    c.scrollTop = c.scrollHeight;
}

function appendTyping() {
    const c = document.getElementById('haiMessages');
    hideWelcome();
    const div = document.createElement('div');
    div.className = 'hmsg ai'; div.id = 'typingInd';
    div.innerHTML = `<div class="hmsg-avatar">🤖</div><div class="hmsg-bubble" style="padding:14px 18px"><span class="tdot"></span><span class="tdot"></span><span class="tdot"></span></div>`;
    c.appendChild(div); c.scrollTop = c.scrollHeight;
}

function removeTyping() { document.getElementById('typingInd')?.remove(); }

async function sendMessage() {
    const input = document.getElementById('haiInput');
    const btn   = document.getElementById('haiSendBtn');
    const text  = input.value.trim();
    if (!text || btn.disabled) return;
    input.value = ''; input.style.height = 'auto'; btn.disabled = true;
    appendMsg('user', text);
    appendTyping();
    try {
        const res  = await fetch('/api/hotel_ai_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text, mode: currentMode, history }),
        });
        const data = await res.json();
        removeTyping();
        const reply = data.reply || 'Không có phản hồi.';
        appendMsg('ai', reply);
        history.push({ role:'user', content:text });
        history.push({ role:'assistant', content:reply });
        if (history.length > 20) history = history.slice(-20);
    } catch(e) {
        removeTyping();
        appendMsg('ai', 'Lỗi kết nối. Vui lòng thử lại.');
    }
    btn.disabled = false; input.focus();
}

document.getElementById('haiInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
});
document.getElementById('haiInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function mdToHtml(md) {
    let h = md
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/^### (.+)$/gm,'<h3>$1</h3>').replace(/^## (.+)$/gm,'<h2>$1</h2>').replace(/^# (.+)$/gm,'<h1>$1</h1>')
        .replace(/\*\*\*(.+?)\*\*\*/g,'<strong><em>$1</em></strong>')
        .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\*(.+?)\*/g,'<em>$1</em>')
        .replace(/`([^`]+)`/g,'<code>$1</code>').replace(/^---$/gm,'<hr>')
        .replace(/^&gt; (.+)$/gm,'<blockquote>$1</blockquote>')
        .replace(/^[•\-\*] (.+)$/gm,'<li>$1</li>').replace(/^\d+\. (.+)$/gm,'<li>$1</li>');
    h = h.replace(/(<li>.*<\/li>\n?)+/g, m => `<ul>${m}</ul>`);
    h = h.replace(/\n{2,}/g,'</p><p>');
    h = '<p>' + h + '</p>';
    h = h.replace(/\n/g,' ').replace(/<p>\s*<\/p>/g,'');
    h = h.replace(/<p>(<(?:h[1-3]|ul|blockquote|hr)[^>]*>)/g,'$1');
    h = h.replace(/(<\/(?:h[1-3]|ul|blockquote)>)<\/p>/g,'$1');
    return h;
}

renderQuick();
</script>

<?php
// hotel_footer inline (hotel portal không có separate footer file)
echo '</div></div></div></body></html>';
?>
