<?php
$page_title    = 'AI Assistant';
$page_subtitle = 'Trợ lý AI quản trị nền tảng';
include "../config/database.php";
include "../includes/admin_header.php";
?>

<style>
/* ── AI Assistant layout ──────────────────────────────────────── */
.ai-wrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 130px);
    gap: 0;
}

/* Mode tabs */
.ai-modes {
    display: flex;
    gap: 8px;
    padding: 0 0 14px;
    flex-wrap: wrap;
}
.ai-mode-btn {
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
.ai-mode-btn:hover { border-color: #667eea; color: #667eea; background: #f0f0ff; }
.ai-mode-btn.active {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    box-shadow: 0 4px 14px rgba(102,126,234,.35);
}
.ai-mode-btn .mode-icon { font-size: 18px; line-height: 1; }

/* Chat card */
.ai-card {
    flex: 1;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

/* Mode description bar */
.ai-mode-desc {
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea15, #764ba215);
    border-bottom: 1px solid #e9ecff;
    font-size: 12.5px;
    color: #5a4fcf;
    font-weight: 500;
}

/* Messages area */
.ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-height: 0;
    scroll-behavior: smooth;
}
.ai-messages::-webkit-scrollbar { width: 5px; }
.ai-messages::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }

/* Message bubbles */
.msg { display: flex; gap: 10px; align-items: flex-start; }
.msg.user { flex-direction: row-reverse; }
.msg-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
    font-weight: 700;
}
.msg.user .msg-avatar  { background: linear-gradient(135deg,#667eea,#764ba2); color:#fff; font-size:12px; }
.msg.ai   .msg-avatar  { background: #f0f0ff; font-size: 18px; }

.msg-bubble {
    max-width: 78%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.65;
    word-break: break-word;
}
.msg.user .msg-bubble {
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.msg.ai .msg-bubble {
    background: #f7f8fc;
    color: #2d3748;
    border-bottom-left-radius: 4px;
    border: 1px solid #eaecf5;
}

/* Markdown-like formatting in AI bubble */
.msg.ai .msg-bubble h1,
.msg.ai .msg-bubble h2,
.msg.ai .msg-bubble h3 { font-size: 14px; font-weight: 700; margin: 10px 0 4px; color: #2d3748; }
.msg.ai .msg-bubble strong { font-weight: 700; color: #1a202c; }
.msg.ai .msg-bubble em { font-style: italic; }
.msg.ai .msg-bubble ul, .msg.ai .msg-bubble ol { padding-left: 18px; margin: 6px 0; }
.msg.ai .msg-bubble li { margin-bottom: 3px; }
.msg.ai .msg-bubble code {
    background: #edf2f7;
    border-radius: 4px;
    padding: 1px 5px;
    font-family: monospace;
    font-size: 12px;
    color: #553c9a;
}
.msg.ai .msg-bubble p { margin: 5px 0; }
.msg.ai .msg-bubble hr { border: none; border-top: 1px solid #e2e8f0; margin: 8px 0; }
.msg.ai .msg-bubble blockquote {
    border-left: 3px solid #667eea;
    padding-left: 10px;
    color: #718096;
    margin: 6px 0;
}

/* Typing indicator */
.typing-dot {
    width: 7px; height: 7px;
    background: #667eea;
    border-radius: 50%;
    display: inline-block;
    animation: typingBounce 1.2s infinite ease-in-out;
}
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes typingBounce {
    0%,60%,100% { transform: translateY(0); opacity:.4; }
    30%          { transform: translateY(-6px); opacity:1; }
}

/* Input area */
.ai-input-area {
    border-top: 1px solid #edf2f7;
    padding: 14px 16px;
    display: flex;
    gap: 10px;
    align-items: flex-end;
    background: #fff;
}
.ai-textarea {
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
.ai-textarea:focus { border-color: #667eea; }
.ai-send-btn {
    width: 44px; height: 44px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg,#667eea,#764ba2);
    color: #fff;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: opacity .15s, transform .1s;
    flex-shrink: 0;
}
.ai-send-btn:hover  { opacity: .88; }
.ai-send-btn:active { transform: scale(.95); }
.ai-send-btn:disabled { opacity: .45; cursor: not-allowed; }
.ai-send-btn svg { width: 18px; height: 18px; }

/* Clear button */
.ai-clear-btn {
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
.ai-clear-btn:hover { border-color: #fc8181; color: #e53e3e; background: #fff5f5; }

/* Welcome state */
.ai-welcome {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 32px;
    gap: 12px;
    color: #a0aec0;
}
.ai-welcome-icon { font-size: 52px; line-height: 1; }
.ai-welcome h3 { font-size: 17px; color: #4a5568; margin: 0; font-weight: 700; }
.ai-welcome p  { font-size: 13px; margin: 0; max-width: 360px; line-height: 1.6; }
.ai-quick-list {
    display: flex; flex-wrap: wrap; gap: 8px;
    justify-content: center; margin-top: 8px;
}
.ai-quick {
    padding: 7px 14px;
    border-radius: 20px;
    border: 1.5px solid #e9ecff;
    background: #f7f8ff;
    color: #667eea;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
}
.ai-quick:hover { background: #667eea; color: #fff; border-color: #667eea; }
</style>

<div class="content">
<div class="ai-wrap">

    <!-- Mode selector -->
    <div class="ai-modes">
        <button class="ai-mode-btn active" data-mode="general" onclick="setMode(this)">
            <span class="mode-icon">🛡️</span> Admin Tổng quan
        </button>
        <button class="ai-mode-btn" data-mode="onboarding" onclick="setMode(this)">
            <span class="mode-icon">🏨</span> Xét duyệt Đối tác
        </button>
        <button class="ai-mode-btn" data-mode="dispute" onclick="setMode(this)">
            <span class="mode-icon">⚖️</span> Xử lý Tranh chấp
        </button>
        <button class="ai-mode-btn" data-mode="analytics" onclick="setMode(this)">
            <span class="mode-icon">📊</span> Phân tích Tài chính
        </button>
    </div>

    <!-- Chat card -->
    <div class="ai-card">
        <div class="ai-mode-desc" id="modeDesc">
            Hỗ trợ 8 nhiệm vụ cốt lõi: quản lý đối tác, người dùng, booking, tài chính, khuyến mãi, nội dung, analytics, cấu hình hệ thống.
        </div>

        <div class="ai-messages" id="aiMessages">
            <!-- Welcome state shown when no messages -->
            <div class="ai-welcome" id="aiWelcome">
                <div class="ai-welcome-icon">🤖</div>
                <h3>Admin AI Assistant</h3>
                <p>Chọn chế độ phù hợp ở trên rồi đặt câu hỏi. Tôi có thể phân tích dữ liệu thực tế từ hệ thống và tư vấn nghiệp vụ OTA.</p>
                <div class="ai-quick-list" id="quickSuggestions">
                    <span class="ai-quick" onclick="sendQuick(this)">Tóm tắt hiệu suất tháng này</span>
                    <span class="ai-quick" onclick="sendQuick(this)">Mức commission 4 sao là bao nhiêu?</span>
                    <span class="ai-quick" onclick="sendQuick(this)">Quy trình xử lý overbooking</span>
                    <span class="ai-quick" onclick="sendQuick(this)">Cảnh báo Take Rate thấp</span>
                </div>
            </div>
        </div>

        <div class="ai-input-area">
            <button class="ai-clear-btn" onclick="clearChat()" title="Xóa lịch sử hội thoại">Xóa</button>
            <textarea
                class="ai-textarea"
                id="aiInput"
                placeholder="Nhập câu hỏi... (Enter gửi, Shift+Enter xuống dòng)"
                rows="1"
            ></textarea>
            <button class="ai-send-btn" id="aiSendBtn" onclick="sendMessage()" title="Gửi">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
                </svg>
            </button>
        </div>
    </div>

</div>
</div>

<script>
const MODE_DESCRIPTIONS = {
    general:    'Hỗ trợ 8 nhiệm vụ cốt lõi: quản lý đối tác, người dùng, booking, tài chính, khuyến mãi, nội dung, analytics, cấu hình hệ thống.',
    onboarding: 'Xét duyệt hồ sơ KYB khách sạn mới với checklist pháp lý, thông tin, hình ảnh, tài chính. Đầu ra: APPROVE / REJECT / REQUIRE_MORE_INFO.',
    dispute:    'Framework 4 bước xử lý 6 loại tranh chấp: No-show, Overbooking, Chất lượng, Phí ẩn, Hoàn tiền chậm, Hành vi không chuyên nghiệp.',
    analytics:  'Phân tích KPI nền tảng với dữ liệu thực tế: GMV, Take Rate, Cancellation Rate, Payout, Disputes. So sánh MoM, đề xuất hành động.',
};
const QUICK_SUGGESTIONS = {
    general:    ['Checklist xử lý KS vi phạm SLA','Quy trình khóa tài khoản gian lận','Cấu hình VAT và phí dịch vụ','Audit log hoạt động hệ thống'],
    onboarding: ['Hướng dẫn xét duyệt hồ sơ 3 sao','Mức commission theo hạng sao','Lý do từ chối hồ sơ phổ biến','Thông tin tài khoản ngân hàng cần kiểm tra'],
    dispute:    ['Xử lý overbooking khẩn cấp','Khách no-show không báo trước','Phòng không đúng mô tả, cần hoàn tiền bao nhiêu?','KS tính phí ẩn, quy trình xử lý'],
    analytics:  ['Báo cáo tóm tắt tháng này','Phân tích Take Rate hiện tại','Cảnh báo KS cần theo dõi','Đề xuất cải thiện Cancellation Rate'],
};

let currentMode = 'general';
let history = [];

function setMode(btn) {
    document.querySelectorAll('.ai-mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentMode = btn.dataset.mode;
    document.getElementById('modeDesc').textContent = MODE_DESCRIPTIONS[currentMode];
    clearChat();
    renderQuickSuggestions();
}

function renderQuickSuggestions() {
    const c = document.getElementById('quickSuggestions');
    c.innerHTML = QUICK_SUGGESTIONS[currentMode]
        .map(q => `<span class="ai-quick" onclick="sendQuick(this)">${q}</span>`)
        .join('');
}

function sendQuick(el) {
    document.getElementById('aiInput').value = el.textContent;
    sendMessage();
}

function clearChat() {
    history = [];
    const m = document.getElementById('aiMessages');
    m.innerHTML = '';
    document.getElementById('aiWelcome') && (void 0); // already removed
    // Re-show welcome
    m.innerHTML = `
        <div class="ai-welcome" id="aiWelcome">
            <div class="ai-welcome-icon">🤖</div>
            <h3>Admin AI Assistant</h3>
            <p>Chọn chế độ phù hợp ở trên rồi đặt câu hỏi. Tôi có thể phân tích dữ liệu thực tế từ hệ thống và tư vấn nghiệp vụ OTA.</p>
            <div class="ai-quick-list" id="quickSuggestions"></div>
        </div>`;
    renderQuickSuggestions();
}

function hideWelcome() {
    const w = document.getElementById('aiWelcome');
    if (w) w.remove();
}

function appendMessage(role, content) {
    const container = document.getElementById('aiMessages');
    hideWelcome();

    const div = document.createElement('div');
    div.className = `msg ${role}`;

    const avatar = document.createElement('div');
    avatar.className = 'msg-avatar';
    avatar.textContent = role === 'user' ? 'ADM' : '🤖';

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';

    if (role === 'ai') {
        bubble.innerHTML = markdownToHtml(content);
    } else {
        bubble.textContent = content;
    }

    div.appendChild(avatar);
    div.appendChild(bubble);
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return div;
}

function appendTyping() {
    const container = document.getElementById('aiMessages');
    hideWelcome();
    const div = document.createElement('div');
    div.className = 'msg ai';
    div.id = 'typingIndicator';
    div.innerHTML = `
        <div class="msg-avatar">🤖</div>
        <div class="msg-bubble" style="padding:14px 18px">
            <span class="typing-dot"></span>
            <span class="typing-dot"></span>
            <span class="typing-dot"></span>
        </div>`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function removeTyping() {
    const t = document.getElementById('typingIndicator');
    if (t) t.remove();
}

async function sendMessage() {
    const input = document.getElementById('aiInput');
    const btn   = document.getElementById('aiSendBtn');
    const text  = input.value.trim();
    if (!text || btn.disabled) return;

    input.value = '';
    input.style.height = 'auto';
    btn.disabled = true;

    appendMessage('user', text);
    appendTyping();

    try {
        const res = await fetch('/api/admin_ai_api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ message: text, mode: currentMode, history }),
        });
        const data = await res.json();
        removeTyping();
        const reply = data.reply || 'Không có phản hồi.';
        appendMessage('ai', reply);
        history.push({ role: 'user', content: text });
        history.push({ role: 'assistant', content: reply });
        if (history.length > 20) history = history.slice(-20);
    } catch (e) {
        removeTyping();
        appendMessage('ai', 'Lỗi kết nối. Vui lòng thử lại.');
    }

    btn.disabled = false;
    input.focus();
}

// Auto-resize textarea
document.getElementById('aiInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
});

// Enter to send (Shift+Enter = newline)
document.getElementById('aiInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// ── Simple Markdown renderer ──────────────────────────────────────────────────
function markdownToHtml(md) {
    let html = md
        // Escape HTML first
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        // Headers
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm,  '<h2>$1</h2>')
        .replace(/^# (.+)$/gm,   '<h1>$1</h1>')
        // Bold & italic
        .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        // Inline code
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        // Horizontal rule
        .replace(/^---$/gm, '<hr>')
        // Blockquote
        .replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>')
        // Unordered list
        .replace(/^[•\-\*] (.+)$/gm, '<li>$1</li>')
        // Ordered list
        .replace(/^\d+\. (.+)$/gm, '<li>$1</li>');

    // Wrap consecutive <li> in <ul>
    html = html.replace(/(<li>.*<\/li>\n?)+/g, m => `<ul>${m}</ul>`);

    // Paragraphs: blank lines → <p> breaks
    html = html.replace(/\n{2,}/g, '</p><p>');
    html = '<p>' + html + '</p>';

    // Single newlines within paragraphs → space
    html = html.replace(/\n/g, ' ');

    // Clean up empty <p>
    html = html.replace(/<p>\s*<\/p>/g, '');
    // Unwrap block elements from <p>
    html = html.replace(/<p>(<(?:h[1-3]|ul|blockquote|hr)[^>]*>)/g, '$1');
    html = html.replace(/(<\/(?:h[1-3]|ul|blockquote)>)<\/p>/g, '$1');

    return html;
}

// Init quick suggestions
renderQuickSuggestions();
</script>

<?php include "../includes/admin_footer.php"; ?>
