    </div><!-- .hotel-content -->
</div><!-- .hotel-main -->
</div><!-- .hotel-wrapper -->

<!-- Hotel Confirm Modal (centered) -->
<div id="hotelConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:18px;padding:36px 32px 28px;max-width:400px;width:92%;box-shadow:0 24px 64px rgba(0,0,0,.22);text-align:center;animation:hcmIn .18s ease">
    <div id="hotelConfirmIcon" style="font-size:44px;margin-bottom:10px">⚠️</div>
    <div id="hotelConfirmMsg" style="font-size:15.5px;font-weight:700;color:#1a365d;margin-bottom:26px;line-height:1.5"></div>
    <div style="display:flex;gap:12px;justify-content:center">
      <button onclick="closeHotelConfirm()" style="padding:11px 28px;border-radius:10px;border:1.5px solid #e2e8f0;background:#edf2f7;color:#4a5568;font-weight:600;font-size:14px;cursor:pointer;transition:.15s" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#edf2f7'">Hủy</button>
      <button id="hotelConfirmBtn" style="padding:11px 28px;border-radius:10px;background:linear-gradient(135deg,#e53e3e,#c53030);color:#fff;font-weight:700;font-size:14px;border:none;cursor:pointer">Xác nhận</button>
    </div>
  </div>
</div>
<style>
@keyframes hcmIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
</style>
<script>
function hotelConfirmPost(msg, formId, icon) {
    document.getElementById('hotelConfirmMsg').textContent = msg;
    document.getElementById('hotelConfirmIcon').textContent = icon || '⚠️';
    document.getElementById('hotelConfirmBtn').onclick = function() {
        closeHotelConfirm();
        document.getElementById(formId).submit();
    };
    const m = document.getElementById('hotelConfirmModal');
    m.style.display = 'flex';
    m.onclick = function(e){ if(e.target===this) closeHotelConfirm(); };
}
function hotelConfirmCallback(msg, cb, icon) {
    document.getElementById('hotelConfirmMsg').textContent = msg;
    document.getElementById('hotelConfirmIcon').textContent = icon || '⚠️';
    document.getElementById('hotelConfirmBtn').onclick = function() { closeHotelConfirm(); cb(); };
    const m = document.getElementById('hotelConfirmModal');
    m.style.display = 'flex';
    m.onclick = function(e){ if(e.target===this) closeHotelConfirm(); };
}
function closeHotelConfirm() {
    document.getElementById('hotelConfirmModal').style.display = 'none';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeHotelConfirm(); });
</script>

</body>
</html>
