</div><!-- /.content -->
</div><!-- /.main -->
</div><!-- /.admin-wrapper -->

<!-- Admin Confirm Modal -->
<div id="adminConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:18px;padding:36px 32px 28px;max-width:400px;width:92%;box-shadow:0 24px 64px rgba(0,0,0,.22);text-align:center;animation:acmIn .18s ease">
    <div id="adminConfirmIcon" style="font-size:44px;margin-bottom:10px">⚠️</div>
    <div id="adminConfirmMsg" style="font-size:16px;font-weight:700;color:#2d3748;margin-bottom:26px;line-height:1.5"></div>
    <div style="display:flex;gap:12px;justify-content:center">
      <button onclick="closeAdminConfirm()" style="padding:11px 28px;border-radius:10px;border:1.5px solid #e2e8f0;background:#edf2f7;color:#4a5568;font-weight:600;font-size:14px;cursor:pointer;transition:.15s" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#edf2f7'">Hủy</button>
      <a id="adminConfirmBtn" href="#" style="padding:11px 28px;border-radius:10px;background:linear-gradient(135deg,#e53e3e,#c53030);color:#fff;font-weight:700;font-size:14px;text-decoration:none;display:inline-flex;align-items:center">Xác nhận</a>
    </div>
  </div>
</div>
<style>
@keyframes acmIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
</style>
<script>
function adminConfirm(msg, href, icon) {
    document.getElementById('adminConfirmMsg').textContent = msg;
    document.getElementById('adminConfirmIcon').textContent = icon || '⚠️';
    const btn = document.getElementById('adminConfirmBtn');
    btn.href = href;
    btn.onclick = null;
    const m = document.getElementById('adminConfirmModal');
    m.style.display = 'flex';
    m.onclick = function(e){ if(e.target===this) closeAdminConfirm(); };
}
function adminConfirmPost(msg, formId, icon) {
    document.getElementById('adminConfirmMsg').textContent = msg;
    document.getElementById('adminConfirmIcon').textContent = icon || '⚠️';
    const btn = document.getElementById('adminConfirmBtn');
    btn.href = 'javascript:void(0)';
    btn.onclick = function(e) {
        e.preventDefault();
        document.getElementById(formId).submit();
    };
    const m = document.getElementById('adminConfirmModal');
    m.style.display = 'flex';
    m.onclick = function(e){ if(e.target===this) closeAdminConfirm(); };
}
function closeAdminConfirm() {
    document.getElementById('adminConfirmModal').style.display = 'none';
    const btn = document.getElementById('adminConfirmBtn');
    btn.onclick = null;
    btn.href = '#';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAdminConfirm(); });
</script>

</body>
</html>

