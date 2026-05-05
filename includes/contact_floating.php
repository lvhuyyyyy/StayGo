<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/security.php';
?>

<!-- ===== FLOATING CONTACT BUTTONS ===== -->
<div class="contact-floating">

    <!-- Wrapper dieu khien height animation (overflow:hidden de an/hien) -->
    <div class="contact-buttons-wrap" id="contactButtonsWrap">
    <!-- contact-buttons: overflow:visible de shadow khong bi cat -->
    <div class="contact-buttons" id="contactButtons">

        <!-- WHATSAPP -->
        <a href="https://wa.me/84373848395" target="_blank" class="contact-btn whatsapp" title="WhatsApp">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="#25D366">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>

        <!-- TƯ VẤN NHANH -->
        <button class="contact-btn consult-btn" title="Tư vấn ngay" onclick="openSupportPopup()">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#7c3aed" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </button>

        <!-- CALL -->
        <a href="tel:0373848395" class="contact-btn call" title="Gọi ngay">
            <img src="https://www.pngall.com/wp-content/uploads/10/Call-Vector-PNG.png" alt="Call">
        </a>

        <!-- ZALO -->
        <a href="https://zalo.me/0373848395" target="_blank" class="contact-btn zalo" title="Chat Zalo">
            <img src="https://tse3.mm.bing.net/th/id/OIP.-9SqZ8KOOXBxbZ5ptL8TwgHaFq?rs=1&pid=ImgDetMain&o=7&rm=3" alt="Zalo">
        </a>

        <!-- FACEBOOK -->
        <a href="https://facebook.com/" target="_blank" class="contact-btn facebook" title="Facebook">
            <img src="https://static.vecteezy.com/system/resources/previews/018/930/698/original/facebook-logo-facebook-icon-transparent-free-png.png" alt="Facebook">
        </a>

    </div>
    </div><!-- end contact-buttons-wrap -->

    <!-- NUT TOGGLE MO/DONG -->
    <button class="contact-toggle-btn" id="contactToggleBtn" onclick="toggleContactButtons()" title="Lien he voi chung toi">
        <!-- Icon Phone (mac dinh) -->
        <svg class="toggle-icon-open" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
        </svg>
        <!-- Icon X (khi mo) -->
        <svg class="toggle-icon-close" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span class="toggle-label">Liên hệ</span>
    </button>

</div>

<!-- ===== 3. SCROLL TO TOP ===== -->
<button class="scroll-top-btn" id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Lên đầu trang">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
    </svg>
</button>

<!-- ===== 1. LIVE CHAT POPUP ===== -->


<!-- ===== 5. POPUP TƯ VẤN NHANH ===== -->
<div class="support-popup-overlay" id="supportOverlay" onclick="closeSupportPopupOutside(event)">
    <div class="support-popup">
        <button class="sp-close" onclick="closeSupportPopup()">✕</button>
        <h3>🎧 Tư vấn miễn phí</h3>
        <p>Để lại thông tin, chúng tôi sẽ liên hệ trong vòng 15 phút!</p>
        <form id="supportForm" onsubmit="submitSupport(event)">
            <?= csrf_field() ?>
            <label>Họ và tên *</label>
            <input type="text" name="full_name" placeholder="Nhập họ tên của bạn" required>
            <label>Số điện thoại *</label>
            <input type="tel" name="phone" placeholder="Nhập số điện thoại" required>
            <label>Ghi chú</label>
            <textarea name="note" placeholder="Bạn cần tư vấn về điều gì?"></textarea>
            <button type="submit" class="sp-submit">Gửi yêu cầu tư vấn</button>
        </form>
        <div class="sp-msg" id="spMsg"></div>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script>
// --- Scroll to Top ---
window.addEventListener('scroll', function () {
    const btn = document.getElementById('scrollTopBtn');
    btn.classList.toggle('visible', window.scrollY > 300);
});

// --- Contact Buttons Toggle ---
function toggleContactButtons() {
    const wrap   = document.getElementById('contactButtonsWrap');
    const toggle = document.getElementById('contactToggleBtn');
    const isOpen = wrap.classList.toggle('open');
    toggle.classList.toggle('active', isOpen);
}

// --- Live Chat ---
function toggleLiveChat() {
    document.getElementById('livechatPopup').classList.toggle('open');
}

const lcBotReplies = [
    'Cảm ơn bạn đã liên hệ! Chúng tôi sẽ hỗ trợ ngay 😊',
    'Bạn có thể gọi hotline 0373848395 để được tư vấn nhanh hơn!',
    'Chúng tôi có nhiều gói tour hấp dẫn tại Kon Tum và Măng Đen!',
    'Vui lòng để lại số điện thoại, nhân viên sẽ liên hệ bạn ngay!',
];
let lcReplyIndex = 0;

function sendLcMsg() {
    const input = document.getElementById('lcInput');
    const msgs  = document.getElementById('lcMessages');
    const text  = input.value.trim();
    if (!text) return;

    msgs.innerHTML += `<div class="lc-msg user">${escapeHtml(text)}</div>`;
    input.value = '';
    msgs.scrollTop = msgs.scrollHeight;

    setTimeout(() => {
        const reply = lcBotReplies[lcReplyIndex % lcBotReplies.length];
        lcReplyIndex++;
        msgs.innerHTML += `<div class="lc-msg bot">${reply}</div>`;
        msgs.scrollTop = msgs.scrollHeight;
    }, 700);
}

// --- Support Popup ---
function openSupportPopup() {
    document.getElementById('supportOverlay').classList.add('open');
}
function closeSupportPopup() {
    document.getElementById('supportOverlay').classList.remove('open');
}
function closeSupportPopupOutside(e) {
    if (e.target === document.getElementById('supportOverlay')) closeSupportPopup();
}

function submitSupport(e) {
    e.preventDefault();
    const form = document.getElementById('supportForm');
    const msg  = document.getElementById('spMsg');
    const btn  = form.querySelector('.sp-submit');

    btn.disabled = true;
    btn.textContent = 'Đang gửi...';

    const data = new FormData(form);

    fetch('/tour_khach_san_project/api/contact_support.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        msg.style.display = 'block';
        msg.className = 'sp-msg ' + (res.success ? 'success' : 'error');
        msg.textContent = res.message;
        if (res.success) {
            form.reset();
            setTimeout(closeSupportPopup, 2500);
        }
    })
    .catch(() => {
        msg.style.display = 'block';
        msg.className = 'sp-msg error';
        msg.textContent = 'Có lỗi kết nối, vui lòng thử lại!';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Gửi yêu cầu tư vấn';
    });
}

function escapeHtml(t) {
    return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
