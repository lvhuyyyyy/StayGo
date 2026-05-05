console.log("Payment JS loaded");

document.addEventListener("DOMContentLoaded", function() {

    const form = document.querySelector(".payment-form");
    if (!form) return; // nếu không có form thì dừng
    const radios = document.querySelectorAll('input[name="payment_method"]');

    // Reset radio khi load
    radios.forEach(r => r.checked = false);

    // Ẩn tất cả QR khi load
    document.querySelectorAll(".qr-box").forEach(box => {
        box.style.display = "none";
    });

    form.addEventListener("submit", function(e) {

        e.preventDefault(); // chặn submit để test hiển thị QR

        // Ẩn tất cả QR trước
        document.querySelectorAll(".qr-box").forEach(box => {
            box.style.display = "none";
        });

        // Tìm phương thức được chọn
        let selectedMethod = null;

        radios.forEach(radio => {
            if (radio.checked) {
                selectedMethod = radio.value;
            }
        });

        if (!selectedMethod) {
            alert("Vui lòng chọn phương thức thanh toán!");
            return;
        }

        // Hiển thị QR tương ứng
        const qrBox = document.getElementById("qr-" + selectedMethod);

        if (qrBox) {
            qrBox.style.display = "block";
        }

    });

});

/* ------------------------------------------
PAYMENT (payment.php)
------------------------------------------ */

function calcPrice() {
    var ci  = document.getElementById('checkin') ? document.getElementById('checkin').value : '';
    var co  = document.getElementById('checkout') ? document.getElementById('checkout').value : '';
    var sel = document.getElementById('roomSelect');
    var opt = sel ? sel.options[sel.selectedIndex] : null;
    var box = document.getElementById('priceBox');
    if (!ci || !co || !opt || !opt.dataset.price) { if (box) box.style.display = 'none'; return; }
    var nights   = Math.max(1, Math.round((new Date(co) - new Date(ci)) / 86400000));
    var price    = parseInt(opt.dataset.price);
    var subtotal = nights * price;
    var discount = (typeof HAS_DISCOUNT !== 'undefined' && HAS_DISCOUNT && DISCOUNT_PCT > 0)
                   ? Math.round(subtotal * DISCOUNT_PCT / 100) : 0;
    var total    = subtotal - discount;
    document.getElementById('nightCount').textContent    = nights + ' đêm';
    document.getElementById('pricePerNight').textContent = price.toLocaleString('vi-VN') + ' VNĐ';
    if (typeof HAS_DISCOUNT !== 'undefined' && HAS_DISCOUNT) {
        var elO = document.getElementById('originalPrice');
        var elD = document.getElementById('discountAmount');
        if (elO) elO.textContent = subtotal.toLocaleString('vi-VN') + ' VNĐ';
        if (elD) elD.textContent = '-' + discount.toLocaleString('vi-VN') + ' VNĐ';
    }
    document.getElementById('totalPrice').textContent = total.toLocaleString('vi-VN') + ' VNĐ';
    box.style.display = 'block';
}

function selectMethod(radio) {
    document.querySelectorAll('.method-card').forEach(function(c) { c.classList.remove('selected'); });
    radio.closest('.method-card').classList.add('selected');
}

var allPanels = ['bank','momo','vnpay','hotel','card'];
function showMethodDetail(type) {
    allPanels.forEach(function(p) {
        var el = document.getElementById('detail_' + p);
        if (el) el.style.display = (p === type) ? 'block' : 'none';
    });
    checkCardSubmitState();
}

var cardLinked = false;
function checkCardSubmitState() {
    var btn = document.getElementById('btnConfirm');
    if (!btn) return;
    var cardRadio = document.querySelector('input[name="payment_method"][value="card"]');
    if (cardRadio && cardRadio.checked && !cardLinked) {
        btn.disabled = true;
        btn.title = 'Vui lòng liên kết thẻ trước khi xác nhận';
    } else {
        btn.disabled = false;
        btn.title = '';
    }
}

function copySTK() {
    navigator.clipboard.writeText('107645394761').then(function() {
        var btn = document.getElementById('copyBtnText');
        btn.textContent = '✓ Đã sao chép!';
        setTimeout(function() { btn.textContent = 'Sao chép'; }, 2000);
    });
}

function copyMoMo() {
    var phone = document.getElementById('momoPhone').textContent.replace(/\s/g, '');
    navigator.clipboard.writeText(phone).then(function() {
        var btn = document.getElementById('momoCopyText');
        btn.textContent = '✓ Đã sao chép!';
        setTimeout(function() { btn.textContent = 'Sao chép'; }, 2000);
    });
}

var selectedCardType = '';
var cardLogos  = { visa:'VISA', mastercard:'MC', jcb:'JCB', amex:'AMEX' };
var cardColors = {
    visa:       'linear-gradient(135deg,#1a1f71,#2563eb)',
    mastercard: 'linear-gradient(135deg,#eb001b,#f79e1b)',
    jcb:        'linear-gradient(135deg,#003087,#009f6b)',
    amex:       'linear-gradient(135deg,#007bc1,#00aeef)'
};

function selectCardType(type) {
    selectedCardType = type;
    document.querySelectorAll('.card-type-item').forEach(function(el) { el.classList.remove('active'); });
    var typeMap = ['visa','mastercard','jcb','amex'];
    var idx = typeMap.indexOf(type);
    var items = document.querySelectorAll('.card-type-item');
    if (idx >= 0 && items[idx]) items[idx].classList.add('active');
    document.getElementById('cardStep1').style.display = 'none';
    document.getElementById('cardStep2').style.display = 'block';
    var preview = document.getElementById('cardPreview');
    if (cardColors[type]) preview.style.background = cardColors[type];
    document.getElementById('cardTypeLogoPreview').textContent = cardLogos[type] || type.toUpperCase();
}

function backToCardTypes() {
    document.getElementById('cardStep1').style.display = 'block';
    document.getElementById('cardStep2').style.display = 'none';
    document.getElementById('cardStep3').style.display = 'none';
    cardLinked = false;
    checkCardSubmitState();
}

function formatCardNumber(input) {
    var val = input.value.replace(/\D/g, '').substring(0, 16);
    var formatted = val.match(/.{1,4}/g);
    input.value = formatted ? formatted.join(' ') : val;
    var preview = document.getElementById('cardNumPreview');
    var padded = val.padEnd(16, '•');
    var parts = padded.match(/.{1,4}/g);
    if (preview) preview.textContent = parts ? parts.join(' ') : padded;
}

function formatExpiry(input) {
    var val = input.value.replace(/\D/g, '');
    if (val.length >= 2) val = val.substring(0,2) + '/' + val.substring(2,4);
    input.value = val;
    var exp = document.getElementById('cardExpirePreview');
    if (exp) exp.textContent = input.value || 'MM/YY';
}

function linkCard() {
    var num    = document.getElementById('cardNumber').value.replace(/\s/g,'');
    var holder = document.getElementById('cardHolder').value.trim();
    var expiry = document.getElementById('cardExpiry').value.trim();
    var cvv    = document.getElementById('cardCVV').value.trim();
    if (num.length < 16)   { alert('Vui lòng nhập đủ 16 số thẻ.'); return; }
    if (!holder)            { alert('Vui lòng nhập tên chủ thẻ.'); return; }
    if (expiry.length < 5) { alert('Vui lòng nhập ngày hết hạn.'); return; }
    if (cvv.length < 3)    { alert('Vui lòng nhập CVV.'); return; }
    var parts = expiry.split('/');
    var expM  = parseInt(parts[0]), expY = parseInt('20'+parts[1]);
    var now   = new Date();
    if (expY < now.getFullYear() || (expY === now.getFullYear() && expM < now.getMonth()+1)) {
        alert('Thẻ đã hết hạn. Vui lòng kiểm tra lại.'); return;
    }
    cardLinked = true;
    var masked = '**** **** **** ' + num.slice(-4);
    document.getElementById('cardStep2').style.display = 'none';
    document.getElementById('cardStep3').style.display = 'block';
    document.getElementById('linkedCardInfo').textContent =
        (selectedCardType.toUpperCase()) + ' • ' + masked + ' • ' + holder.toUpperCase();
    checkCardSubmitState();
}

/* -- Init event listeners -- */
document.addEventListener('DOMContentLoaded', function() {
    var ci = document.getElementById('checkin');
    var co = document.getElementById('checkout');
    if (ci) ci.addEventListener('change', calcPrice);
    if (co) co.addEventListener('change', calcPrice);
    document.querySelectorAll('input[name="payment_method"]').forEach(function(r) {
        r.addEventListener('change', checkCardSubmitState);
    });
});