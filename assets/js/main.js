console.log('Tour Khach San Ready');

//TIM
function addToFavorite(element) {
    element.classList.toggle("active");

    const toast = document.createElement("div");
    toast.className = "toast";
    toast.innerText = "Đã thêm vào mục yêu thích";

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add("show");
    }, 100);

    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 2000);
}


/* ------------------------------------------
BLOG DETAIL (blog-detail.php)
------------------------------------------ */
function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        const btn = document.querySelector('.bd-share-copy');
        const original = btn.innerHTML;
        btn.innerHTML = 'Đã sao chép!';
        btn.style.background = '#d1fae5';
        btn.style.color = '#065f46';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}

/* ------------------------------------------
BLOG change_password (change_password.php)
------------------------------------------ */
function togglePw(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const txt  = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        {w:'0%',   c:'#e2e8f0', t:''},
        {w:'25%',  c:'#e53e3e', t:'Yếu'},
        {w:'50%',  c:'#e05c1a', t:'Trung bình'},
        {w:'75%',  c:'#d69e2e', t:'Khá mạnh'},
        {w:'100%', c:'#38a169', t:'Mạnh'},
    ];
    const lv = levels[Math.min(score, 4)];
    fill.style.width = lv.w;
    fill.style.background = lv.c;
    txt.textContent = lv.t;
    txt.style.color = lv.c;
}

function checkMatch() {
    const nw  = document.getElementById('new_pw').value;
    const cf  = document.getElementById('conf_pw').value;
    const txt = document.getElementById('matchText');
    if (!cf) { txt.textContent = ''; return; }
    if (nw === cf) {
        txt.textContent = '✓ Mật khẩu khớp'; txt.style.color = '#38a169';
    } else {
        txt.textContent = '✗ Mật khẩu không khớp'; txt.style.color = '#e53e3e';
    }
}

/* ------------------------------------------
hotels
------------------------------------------ */
function calcNights() {
    const ci = document.getElementById('sf_checkin')?.value;
    const co = document.getElementById('sf_checkout')?.value;
    const badge = document.getElementById('nightsBadge');
    const txt   = document.getElementById('nightsText');
    if (!ci || !co) { if(badge) badge.style.display='none'; return; }
    const n = Math.round((new Date(co) - new Date(ci)) / 86400000);
    if (n > 0) { txt.textContent = n + ' Đêm'; badge.style.display = 'flex'; }
    else { badge.style.display = 'none'; }
}
function setPrice(min, max) {
    document.querySelector('input[name=min_price]').value = min || '';
    document.querySelector('input[name=max_price]').value = max || '';
    document.getElementById('searchForm').submit();
}

/* ------------------------------------------
my_bookings
------------------------------------------ */
function showRefundModal(url, amount, fee) {
    document.getElementById('modal-amount').textContent = amount + ' VNĐ';
    document.getElementById('modal-fee').textContent    = '-' + fee + ' VNĐ';
    document.getElementById('modal-confirm-btn').href   = url;
    document.getElementById('refundModal').style.display = 'flex';
}
function closeRefundModal() {
    document.getElementById('refundModal').style.display = 'none';
}

function confirmCancel(bookingId, orderCode) {
    document.getElementById('cancel-order-code').textContent = '#' + orderCode;
    document.getElementById('cancel-confirm-btn').href = 'cancel_booking.php?id=' + bookingId;
    document.getElementById('cancelModal').style.display = 'flex';
}
function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    var refundModal = document.getElementById('refundModal');
    var cancelModal = document.getElementById('cancelModal');
    if (refundModal) {
        refundModal.addEventListener('click', function(e) {
            if (e.target === this) closeRefundModal();
        });
    }
    if (cancelModal) {
        cancelModal.addEventListener('click', function(e) {
            if (e.target === this) closeCancelModal();
        });
    }
});

/* ------------------------------------------
REVIEW SECTION (includes/review_section.php)
------------------------------------------ */
var starLabels = ['', 'Tệ', 'Không tốt', 'Bình thường', 'Tốt', 'Tuyệt vời'];

function toggleReviewForm() {
    const form = document.getElementById('rv-form');
    const btn  = document.querySelector('.rv-write-btn');
    if (!form) return;
    const showing = form.style.display !== 'none';
    form.style.display = showing ? 'none' : 'block';
    btn.innerHTML = showing
        ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg> Viết đánh giá của bạn'
        : '✕ Đóng form';
}

function selectStar(n) {
    document.getElementById('rv-rating').value = n;
    document.getElementById('rv-star-label').textContent = starLabels[n];
    document.querySelectorAll('.rv-star-btn').forEach((btn, i) => {
        btn.classList.toggle('selected', i < n);
        btn.classList.remove('hovered');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.rv-star-btn').forEach((btn, idx) => {
        btn.addEventListener('mouseenter', () => {
            document.querySelectorAll('.rv-star-btn').forEach((b, i) => {
                b.classList.toggle('hovered', i <= idx);
            });
            document.getElementById('rv-star-label').textContent = starLabels[idx + 1];
        });
        btn.addEventListener('mouseleave', () => {
            document.querySelectorAll('.rv-star-btn').forEach(b => b.classList.remove('hovered'));
            const cur = parseInt(document.getElementById('rv-rating').value) || 0;
            document.getElementById('rv-star-label').textContent = cur ? starLabels[cur] : 'Chưa chọn';
        });
    });
});

function submitReview(hotelId) {
    const bookingId = document.getElementById('rv-booking-id').value;
    const rating    = document.getElementById('rv-rating').value;
    const comment   = document.getElementById('rv-comment').value.trim();
    if (!bookingId)             return showRvMsg('Vui lòng chọn lần lưu trú.', 'error');
    if (!rating || rating == 0) return showRvMsg('Vui lòng chọn số sao.', 'error');
    if (comment.length < 10)    return showRvMsg('Nhận xét tối thiểu 10 ký tự.', 'error');
    const submitBtn = document.querySelector('.rv-submit-btn');
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Đang gửi...';
    const fd = new FormData();
    fd.append('action',     'submit');
    fd.append('hotel_id',   hotelId);
    fd.append('booking_id', bookingId);
    fd.append('rating',     rating);
    fd.append('comment',    comment);
    fetch('/tour_khach_san_project/pages/reviews_handler.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showRvMsg(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showRvMsg(data.message, 'error');
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Gửi đánh giá';
            }
        })
        .catch(() => {
            showRvMsg('Có lỗi xảy ra, vui lòng thử lại.', 'error');
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Gửi đánh giá';
        });
}

function showRvMsg(msg, type) {
    const el = document.getElementById('rv-msg');
    if (!el) return;
    el.textContent = msg;
    el.className = 'rv-msg ' + type;
}


/* ------------------------------------------
contact_floating.php
------------------------------------------ */
document.addEventListener("DOMContentLoaded", function() {
    const buttons = document.querySelectorAll(".contact-btn");
    buttons.forEach(btn => {
        btn.addEventListener("click", function() {
            buttons.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
        });
    });
});


/* ===== CHATBOT ===== */
const staffAvatar = "https://img.freepik.com/premium-vector/woman-with-headphones-microphone-with-laptop_1023984-24634.jpg";

function toggleChat() {
    let box = document.getElementById("chatBox");
    let isOpen = box.style.display === "flex";
    box.style.display = isOpen ? "none" : "flex";
    if (!isOpen && !document.getElementById("welcomeLoaded")) loadWelcome();

    // Ẩn floating contact khi chatbot mở, hiện lại khi chatbot đóng
    let floating = document.querySelector(".contact-floating");
    if (floating) floating.style.display = isOpen ? "" : "none";
}

function appendMessage(text, type) {
    let content = document.getElementById("chatContent");
    let msg = document.createElement("div");
    msg.className = "message " + type;
    if (type === "bot") {
        let avatar = document.createElement("img");
        avatar.src = staffAvatar;
        Object.assign(avatar.style, {width:"30px",height:"30px",borderRadius:"50%",marginRight:"8px"});
        msg.appendChild(avatar);
    }
    let bubble = document.createElement("div");
    bubble.className = "bubble";
    bubble.innerHTML = text;
    msg.appendChild(bubble);
    content.appendChild(msg);
    content.scrollTop = content.scrollHeight;
}

function showTyping() {
    let content = document.getElementById("chatContent");
    let typing = document.createElement("div");
    typing.className = "message bot"; typing.id = "typing";
    let avatar = document.createElement("img");
    avatar.src = staffAvatar;
    Object.assign(avatar.style, {width:"30px",height:"30px",borderRadius:"50%",marginRight:"8px"});
    let text = document.createElement("div");
    text.className = "bubble"; text.innerText = "Nhân viên đang trả lời...";
    typing.appendChild(avatar); typing.appendChild(text);
    content.appendChild(typing);
    content.scrollTop = content.scrollHeight;
}

function removeTyping() {
    let t = document.getElementById("typing");
    if (t) t.remove();
}

function sendMessage() {
    let input = document.getElementById("message");
    let message = input.value.trim();
    if (!message) return;
    appendMessage(message, "user");
    input.value = "";
    showTyping();
    let formData = new FormData();
    formData.append("message", message);
    fetch("/tour_khach_san_project/chatbot_api.php", { method:"POST", body:formData })
    .then(r => r.json())
    .then(data => {
        removeTyping();
        appendMessage(data.reply, "bot");
        if (data.quick_replies) showQuickReplies(data.quick_replies);
    });
}

function loadWelcome() {
    appendMessage("Xin chào! 👋<br><br>Mình là <b>Nhân viên tư vấn StayGo</b>.<br>Mình có thể giúp bạn chọn tour, báo giá hoặc tư vấn lịch trình phù hợp.<br><br>Bạn có thể tham khảo nhanh các câu hỏi dưới đây:", "bot");
    showSuggestions();
    let flag = document.createElement("div");
    flag.id = "welcomeLoaded";
    document.body.appendChild(flag);
}

function showQuickReplies(replies) {
    let content = document.getElementById("chatContent");
    let wrap = document.createElement("div");
    wrap.className = "quick-replies";
    wrap.style.cssText = "display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 6px 38px";
    replies.forEach(text => {
        let btn = document.createElement("button");
        btn.textContent = text;
        btn.style.cssText = "background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:5px 12px;border-radius:20px;font-size:12px;cursor:pointer";
        btn.onclick = function() {
            wrap.remove();
            document.getElementById("message").value = text;
            sendMessage();
        };
        wrap.appendChild(btn);
    });
    content.appendChild(wrap);
    content.scrollTop = content.scrollHeight;
}

function showSuggestions() {
    const suggestions = [
        "Tour Măng Đen 3 ngày 2 đêm giá bao nhiêu?",
        "Có tour Quảng Ngãi trọn gói không?",
        "Tư vấn tour phù hợp cho gia đình 4 người"
    ];
    let content = document.getElementById("chatContent");
    suggestions.forEach(text => {
        let btn = document.createElement("div");
        btn.className = "bubble";
        Object.assign(btn.style, {background:"#fff",border:"1px solid #007bff",color:"#007bff",cursor:"pointer",marginBottom:"8px"});
        btn.innerText = text;
        btn.onclick = function() {
            appendMessage(text, "user"); showTyping();
            let formData = new FormData();
            formData.append("message", text);
            fetch("/tour_khach_san_project/chatbot_api.php", { method:"POST", body:formData })
                .then(r => r.json())
                .then(data => {
                    removeTyping();
                    appendMessage(data.reply, "bot");
                    if (data.quick_replies) showQuickReplies(data.quick_replies);
                });
        };
        content.appendChild(btn);
    });
    content.scrollTop = content.scrollHeight;
}

// ===== RESET PASSWORD =====
const confirmPwInput = document.getElementById('confirm_password');
if (confirmPwInput) {
    confirmPwInput.addEventListener('input', function () {
        const pw  = document.getElementById('new_password').value;
        const cpw = this.value;
        const msg = document.getElementById('matchMsg');
        if (cpw === '') { msg.style.display = 'none'; return; }
        if (pw === cpw) {
            msg.style.display = 'block';
            msg.style.color   = 'green';
            msg.textContent   = '✓ Mật khẩu khớp';
        } else {
            msg.style.display = 'block';
            msg.style.color   = 'red';
            msg.textContent   = '✗ Mật khẩu không khớp';
        }
    });
}

const resetForm = document.getElementById('resetForm');
if (resetForm) {
    resetForm.addEventListener('submit', function (e) {
        const old = document.getElementById('old_password').value;
        const pw  = document.getElementById('new_password').value;
        const cpw = document.getElementById('confirm_password').value;
        if (!old)          { e.preventDefault(); alert('Vui lòng nhập mật khẩu cũ!'); return; }
        if (pw.length < 6) { e.preventDefault(); alert('Mật khẩu mới phải có ít nhất 6 ký tự!'); return; }
        if (pw !== cpw)    { e.preventDefault(); alert('Mật khẩu xác nhận không khớp!'); return; }
        if (old === pw)    { e.preventDefault(); alert('Mật khẩu mới không được trùng mật khẩu cũ!'); return; }
    });
}

/* ---------------------------------------------------
PAYMENT PAGE
-------------------------------------------------------- */
(function () {
    if (!document.getElementById('payForm')) return;

    var today    = new Date();
    var maxYear  = today.getFullYear() + 1;
    var todayStr = today.toISOString().split('T')[0];
    var maxDate  = maxYear + '-12-31';

    var checkin  = document.getElementById('checkin');
    var checkout = document.getElementById('checkout');

    if (checkin && checkout) {
        checkin.min  = todayStr;
        checkin.max  = maxDate;
        checkout.min = todayStr;
        checkout.max = maxDate;

        checkin.addEventListener('change', function () {
            if (!this.value) return;
            var cin = new Date(this.value);
            cin.setDate(cin.getDate() + 1);
            var minCheckout = cin.toISOString().split('T')[0];
            checkout.min = minCheckout;
            if (checkout.value && checkout.value <= this.value) {
                checkout.value = minCheckout;
            }
            if (typeof calcPrice === 'function') calcPrice();
        });

        checkout.addEventListener('change', function () {
            if (checkin.value && this.value <= checkin.value) {
                alert('⚠️ Ngày trả phòng phải sau ngày nhận phòng!');
                var cin = new Date(checkin.value);
                cin.setDate(cin.getDate() + 1);
                this.value = cin.toISOString().split('T')[0];
            }
            if (typeof calcPrice === 'function') calcPrice();
        });
    }

    document.getElementById('payForm').addEventListener('submit', function (e) {
        var method    = document.querySelector('input[name="payment_method"]:checked');
        var checkinV  = document.getElementById('checkin')?.value;
        var checkoutV = document.getElementById('checkout')?.value;

        if (!checkinV || !checkoutV) {
            e.preventDefault();
            alert('⚠️ Vui lòng chọn ngày nhận phòng và trả phòng!');
            return false;
        }
        if (checkoutV <= checkinV) {
            e.preventDefault();
            alert('⚠️ Ngày trả phòng phải sau ngày nhận phòng!');
            return false;
        }
        if (!method) {
            e.preventDefault();
            alert('⚠️ Vui lòng chọn phương thức thanh toán!');
            var grid = document.querySelector('.method-grid');
            if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
    });
})();

// -- Hiển thị panel chi tiết phương thức --
function showMethodDetail(method) {
    ['bank', 'momo', 'vnpay', 'hotel', 'card'].forEach(function (m) {
        var el = document.getElementById('detail_' + m);
        if (el) el.style.display = (m === method) ? 'block' : 'none';
    });
}

function selectMethod(radio) {
    document.querySelectorAll('.method-card').forEach(function (c) {
        c.classList.remove('selected');
    });
    if (radio.closest('.method-card')) {
        radio.closest('.method-card').classList.add('selected');
    }
}

// -- Tính giá động --
function calcPrice() {
    var ci   = document.getElementById('checkin')?.value;
    var co   = document.getElementById('checkout')?.value;
    var sel  = document.getElementById('roomSelect');
    var box  = document.getElementById('priceBox');
    if (!ci || !co || !sel || !sel.value) { if (box) box.style.display = 'none'; return; }

    var nights = Math.round((new Date(co) - new Date(ci)) / 86400000);
    if (nights <= 0) { if (box) box.style.display = 'none'; return; }

    var opt   = sel.options[sel.selectedIndex];
    var price = parseInt(opt?.dataset?.price || 0);
    if (!price) { if (box) box.style.display = 'none'; return; }

    var total    = price * nights;
    var discount = 0;
    if (window.HAS_DISCOUNT && window.DISCOUNT_PCT > 0) {
        discount = Math.round(total * window.DISCOUNT_PCT / 100);
    }
    var finalTotal = total - discount;

    document.getElementById('nightCount').textContent    = nights + ' đêm';
    document.getElementById('pricePerNight').textContent  = price.toLocaleString('vi-VN') + 'đ';

    var origEl = document.getElementById('originalPrice');
    var discEl = document.getElementById('discountAmount');
    if (origEl) origEl.textContent = total.toLocaleString('vi-VN') + 'đ';
    if (discEl) discEl.textContent = '-' + discount.toLocaleString('vi-VN') + 'đ';

    document.getElementById('totalPrice').textContent = finalTotal.toLocaleString('vi-VN') + 'đ';
    if (box) box.style.display = 'block';
}

// -- Copy STK ngân hàng --
function copySTK() {
    var stk = document.getElementById('stkText')?.textContent?.trim();
    if (!stk) return;
    navigator.clipboard.writeText(stk).then(function () {
        var btn = document.getElementById('copyBtnText');
        if (btn) { btn.textContent = '✓ Đã chép!'; setTimeout(function () { btn.textContent = 'Sao chép'; }, 2000); }
    });
}

// -- Copy MoMo --
function copyMoMo() {
    var phone = document.getElementById('momoPhone')?.textContent?.trim();
    if (!phone) return;
    navigator.clipboard.writeText(phone).then(function () {
        var btn = document.getElementById('momoCopyText');
        if (btn) { btn.textContent = '✓ Đã chép!'; setTimeout(function () { btn.textContent = 'Sao chép'; }, 2000); }
    });
}

// -- Copy order code --
function copyOrderCode() {
    var code = document.querySelector('.hco-code')?.textContent?.trim();
    if (!code) return;
    navigator.clipboard.writeText(code).then(function () {
        var btn = document.getElementById('hcoCopyBtn');
        if (!btn) return;
        btn.textContent = '✓ Đã chép!';
        btn.style.background = '#276749';
        btn.style.color = 'white';
        setTimeout(function () {
            btn.innerHTML = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/></svg> Sao chép mã';
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}

// -- Thẻ quốc tế --
function selectCardType(type) {
    document.querySelectorAll('.card-type-item').forEach(function (el) { el.classList.remove('active'); });
    event.currentTarget.classList.add('active');

    var labels = { visa:'VISA', mastercard:'MASTERCARD', jcb:'JCB', amex:'AMEX' };
    var colors = { visa:'linear-gradient(135deg,#1434CB,#4169e1)', mastercard:'linear-gradient(135deg,#eb001b,#f79e1b)', jcb:'linear-gradient(135deg,#003087,#009F6B)', amex:'linear-gradient(135deg,#007BC1,#00a8e0)' };

    var preview = document.getElementById('cardPreview');
    if (preview) preview.style.background = colors[type] || 'linear-gradient(135deg,#667eea,#764ba2)';

    var logo = document.getElementById('cardTypeLogoPreview');
    if (logo) logo.textContent = labels[type] || '';

    document.getElementById('cardStep1').style.display = 'none';
    document.getElementById('cardStep2').style.display = 'block';
}

function backToCardTypes() {
    document.getElementById('cardStep1').style.display = 'block';
    document.getElementById('cardStep2').style.display = 'none';
    document.getElementById('cardStep3').style.display = 'none';
}

function formatCardNumber(input) {
    var v = input.value.replace(/\D/g, '').substring(0, 16);
    input.value = v.replace(/(.{4})/g, '$1 ').trim();
    var preview = document.getElementById('cardNumPreview');
    if (preview) preview.textContent = input.value || 'XXXX XXXX XXXX XXXX';
}

function formatExpiry(input) {
    var v = input.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2);
    input.value = v;
    var preview = document.getElementById('cardExpirePreview');
    if (preview) preview.textContent = input.value || 'MM/YY';
}

function linkCard() {
    var num    = document.getElementById('cardNumber')?.value?.trim();
    var holder = document.getElementById('cardHolder')?.value?.trim();
    var expiry = document.getElementById('cardExpiry')?.value?.trim();
    var cvv    = document.getElementById('cardCVV')?.value?.trim();

    if (!num || num.replace(/\s/g,'').length < 16) { alert('⚠️ Vui lòng nhập đủ 16 số thẻ!'); return; }
    if (!holder) { alert('⚠️ Vui lòng nhập tên chủ thẻ!'); return; }
    if (!expiry || expiry.length < 5) { alert('⚠️ Vui lòng nhập ngày hết hạn hợp lệ!'); return; }
    if (!cvv || cvv.length < 3) { alert('⚠️ Vui lòng nhập CVV!'); return; }

    var masked = '**** **** **** ' + num.replace(/\s/g,'').slice(-4);
    var info   = document.getElementById('linkedCardInfo');
    if (info) info.textContent = masked + ' — ' + holder.toUpperCase();

    document.getElementById('cardStep2').style.display = 'none';
    document.getElementById('cardStep3').style.display = 'block';
}

/* ---------------------------------------------------
   [FIX 1] COUNTDOWN TIMER THỰC SỰ — chạy khi trang QR hiển thị
   [FIX 3] AUTO-REFRESH TRẠNG THÁI THANH TOÁN mỗi 5 giây
-------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', function () {
    var countdownEl  = document.getElementById('countdown');
    var progressBar  = document.getElementById('timeProgressBar');
    var expiredOverlay = document.getElementById('expiredOverlay');
    var successOverlay = document.getElementById('successOverlay');
    var liveVal      = document.getElementById('liveVal');
    var liveDot      = document.getElementById('liveDot');
    var statusText   = document.getElementById('statusText');
    var statusIndicator = document.getElementById('statusIndicator');
    var qrExpiredCover  = document.getElementById('qrExpiredCover');
    var countdownPill   = document.getElementById('countdownPill');

    // Chỉ chạy khi có trang QR (không phải trang form)
    if (!countdownEl) return;

    /* -- FIX 1: Countdown thực sự -- */
    var TOTAL_SECS = 15 * 60; // 900 giây
    var timeLeft   = TOTAL_SECS;
    var timerDone  = false;

    var countdownInterval = setInterval(function () {
        if (timerDone) return;
        timeLeft--;

        var mins = Math.floor(timeLeft / 60);
        var secs = timeLeft % 60;
        var display = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
        countdownEl.textContent = display;

        // Thanh progress
        if (progressBar) {
            var pct = (timeLeft / TOTAL_SECS) * 100;
            progressBar.style.width = pct + '%';
            // Đổi màu khi gần hết
            if (pct > 50) {
                progressBar.style.background = 'linear-gradient(90deg,#38a169,#68d391)';
            } else if (pct > 20) {
                progressBar.style.background = 'linear-gradient(90deg,#d69e2e,#f6c23e)';
            } else {
                progressBar.style.background = 'linear-gradient(90deg,#e53e3e,#fc8181)';
            }
        }

        // Đổi màu pill khi còn < 3 phút
        if (timeLeft <= 180 && countdownPill) {
            countdownPill.style.background = '#fff5f5';
            countdownPill.style.color      = '#c53030';
            countdownPill.style.borderColor = '#fc8181';
        }

        // Hết giờ
        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            timerDone = true;
            if (qrExpiredCover) qrExpiredCover.style.display = 'flex';
            if (expiredOverlay) expiredOverlay.style.display = 'flex';
            clearInterval(pollInterval);
        }
    }, 1000);

    /* -- FIX 3: Polling kiểm tra trạng thái thanh toán mỗi 5 giây -- */
    // Lấy booking_id từ data attribute của element (được PHP in ra)
    var bookingIdEl = document.getElementById('bookingIdData');
    var bookingId   = bookingIdEl ? bookingIdEl.dataset.id : null;

    if (!bookingId) return; // Không có booking_id thì không poll

    var pollInterval = setInterval(function () {
        if (timerDone) return;

        fetch('/tour_khach_san_project/pages/check_payment.php?booking_id=' + bookingId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'paid') {
                    // Dừng tất cả
                    clearInterval(countdownInterval);
                    clearInterval(pollInterval);
                    timerDone = true;

                    // Cập nhật UI live status
                    if (liveVal) {
                        liveVal.textContent = '✓ Đã thanh toán thành công!';
                        liveVal.style.color = '#276749';
                    }
                    if (liveDot) {
                        liveDot.style.background = '#38a169';
                        liveDot.style.boxShadow  = '0 0 0 4px rgba(56,161,105,.25)';
                    }
                    if (statusText) statusText.textContent = 'Thanh toán thành công!';
                    if (statusIndicator) {
                        statusIndicator.style.background = '#38a169';
                    }

                    // Hiện overlay thành công
                    if (successOverlay) {
                        var codeEl = document.getElementById('successOrderCode');
                        if (codeEl) codeEl.textContent = data.order_code || '';
                        successOverlay.style.display = 'flex';

                        // Đếm ngược redirect 5 giây
                        var redirectCount = 5;
                        var redirectEl = document.getElementById('redirectCount');
                        var redirectInterval = setInterval(function () {
                            redirectCount--;
                            if (redirectEl) redirectEl.textContent = redirectCount;
                            if (redirectCount <= 0) {
                                clearInterval(redirectInterval);
                                window.location.href = '/tour_khach_san_project/pages/hotels.php';
                            }
                        }, 1000);
                    }
                }
            })
            .catch(function () {
                // Lỗi mạng — bỏ qua, tiếp tục poll lần sau
            });
    }, 5000); // poll mỗi 5 giây
});