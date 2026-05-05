<?php include '../includes/header.php'; ?>

<div style="max-width:600px; margin:80px auto; text-align:center; padding:40px; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
    <h2 style="color:#1a3c5e;">Xác nhận xóa dữ liệu</h2>
    <p>Yêu cầu xóa dữ liệu của bạn đã được ghi nhận.</p>
    <?php if(isset($_GET['code'])): ?>
        <p>Mã xác nhận: <strong><?= htmlspecialchars($_GET['code']) ?></strong></p>
    <?php endif; ?>
    <p style="color:#666; font-size:14px;">Dữ liệu của bạn sẽ được xóa trong vòng 30 ngày.</p>
</div>

<?php include '../includes/footer.php'; ?>