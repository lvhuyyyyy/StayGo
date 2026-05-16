<?php
include "../config/database.php";

// Xử lý cập nhật trạng thái + ghi chú admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $id         = (int)$_POST['request_id'];
    $status     = in_array($_POST['status'] ?? '', ['pending','processing','resolved'])
                  ? $_POST['status'] : 'pending';
    $admin_note = $conn->real_escape_string(trim($_POST['admin_note'] ?? ''));
    $conn->query("UPDATE support_requests SET status='$status', admin_note='$admin_note', updated_at=NOW() WHERE id=$id");
    header("Location: support_requests.php?success=1&filter=$status");
    exit;
}

$page_title    = 'Yêu cầu hỗ trợ';
$page_subtitle = 'Quản lý yêu cầu hỗ trợ từ người dùng';
include "../includes/admin_header.php";

$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending','processing','resolved'])) $filter = 'pending';

$rows = $conn->query("
    SELECT sr.*, h.name AS hotel_name
    FROM support_requests sr
    LEFT JOIN hotels h ON sr.hotel_id = h.id
    WHERE sr.status='$filter'
    ORDER BY sr.created_at DESC
");

$cnt_pending    = (int)$conn->query("SELECT COUNT(*) as c FROM support_requests WHERE status='pending'"   )->fetch_assoc()['c'];
$cnt_processing = (int)$conn->query("SELECT COUNT(*) as c FROM support_requests WHERE status='processing'")->fetch_assoc()['c'];
$cnt_resolved   = (int)$conn->query("SELECT COUNT(*) as c FROM support_requests WHERE status='resolved'"  )->fetch_assoc()['c'];

$editing = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$status_cfg = [
    'pending'    => ['⏳ Chờ xử lý',    '#b7791f','#fffbeb','#fef3c7'],
    'processing' => ['🔄 Đang xử lý',   '#1e73be','#ebf8ff','#bee3f8'],
    'resolved'   => ['✅ Đã giải quyết', '#276749','#f0fff4','#9ae6b4'],
];
?>

<?php if(isset($_GET['success'])): ?>
<div style="padding:12px 16px;border-radius:10px;margin-bottom:14px;font-size:13.5px;font-weight:600;color:#276749;background:#f0fff4;border:1px solid #9ae6b4">
    ✅ Cập nhật yêu cầu hỗ trợ thành công!
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="refund-tabs">
    <a href="?filter=pending"    class="refund-tab <?= $filter==='pending'    ?'active':'' ?>">
        ⏳ Chờ xử lý <span class="cnt"><?= $cnt_pending ?></span>
    </a>
    <a href="?filter=processing" class="refund-tab <?= $filter==='processing' ?'active':'' ?>">
        🔄 Đang xử lý <span class="cnt"><?= $cnt_processing ?></span>
    </a>
    <a href="?filter=resolved"   class="refund-tab <?= $filter==='resolved'   ?'active':'' ?>">
        ✅ Đã giải quyết <span class="cnt"><?= $cnt_resolved ?></span>
    </a>
</div>

<?php if(!$rows || $rows->num_rows === 0): ?>
<div class="empty-state">
    <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#cbd5e0" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <p>Không có yêu cầu nào trong mục này.</p>
</div>
<?php else: ?>
<div class="section-card" style="padding:0;overflow:hidden">
<table style="width:100%;border-collapse:collapse;font-size:13.5px">
    <thead>
        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0">
            <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600">#</th>
            <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600">Họ tên</th>
            <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600">SĐT / Email</th>
            <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600">Chủ đề</th>
            <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600">Ngày gửi</th>
            <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600">Trạng thái</th>
            <th style="padding:12px 16px;text-align:center;color:#64748b;font-weight:600">Xử lý</th>
        </tr>
    </thead>
    <tbody>
    <?php while($row = $rows->fetch_assoc()):
        $cfg = $status_cfg[$row['status']] ?? $status_cfg['pending'];
    ?>
        <tr style="border-bottom:1px solid #f1f5f9">
            <td style="padding:12px 16px;color:#94a3b8"><?= $row['id'] ?></td>
            <td style="padding:12px 16px;font-weight:600;color:#1a202c">
                <?= htmlspecialchars($row['full_name']) ?>
                <?php if ($row['type'] === 'hotel'): ?>
                <div style="font-size:11px;font-weight:600;color:#3182ce;margin-top:2px">🏨 <?= htmlspecialchars($row['hotel_name'] ?? 'KS #'.$row['hotel_id']) ?></div>
                <?php endif; ?>
            </td>
            <td style="padding:12px 16px;color:#4a5568">
                <?= htmlspecialchars($row['phone']) ?><br>
                <span style="font-size:12px;color:#94a3b8"><?= htmlspecialchars($row['email'] ?? '') ?></span>
            </td>
            <td style="padding:12px 16px;color:#4a5568;max-width:180px">
                <div style="font-weight:500"><?= htmlspecialchars($row['subject'] ?: '(Không có chủ đề)') ?></div>
                <div style="font-size:12px;color:#94a3b8;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:170px">
                    <?= htmlspecialchars(mb_substr($row['note'], 0, 60)) ?>...
                </div>
            </td>
            <td style="padding:12px 16px;color:#64748b;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
            <td style="padding:12px 16px">
                <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;
                    color:<?= $cfg[1] ?>;background:<?= $cfg[2] ?>;border:1px solid <?= $cfg[3] ?>">
                    <?= $cfg[0] ?>
                </span>
            </td>
            <td style="padding:12px 16px;text-align:center">
                <a href="?filter=<?= $filter ?>&edit=<?= $row['id'] ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:12.5px;font-weight:600;
                          background:#ebf8ff;color:#1e73be;text-decoration:none;border:1px solid #bee3f8">
                    ✏️ Xử lý
                </a>
            </td>
        </tr>

        <?php if($editing === (int)$row['id']): ?>
        <tr style="background:#f8fafc">
            <td colspan="7" style="padding:20px 24px">

                <!-- Nội dung đầy đủ -->
                <div style="margin-bottom:16px;padding:14px 16px;background:#fff;border-radius:10px;border:1px solid #e2e8f0">
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:6px;font-weight:600;text-transform:uppercase">Nội dung yêu cầu</div>
                    <p style="margin:0;color:#1a202c;line-height:1.6"><?= nl2br(htmlspecialchars($row['note'])) ?></p>
                </div>

                <?php if($row['admin_note']): ?>
                <div style="margin-bottom:16px;padding:14px 16px;background:#f0fff4;border-radius:10px;border:1px solid #9ae6b4">
                    <div style="font-size:12px;color:#276749;margin-bottom:6px;font-weight:600;text-transform:uppercase">Ghi chú admin trước đó</div>
                    <p style="margin:0;color:#276749;line-height:1.6"><?= nl2br(htmlspecialchars($row['admin_note'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Form cập nhật -->
                <form method="POST" action="support_requests.php">
                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
                        <div style="flex:1;min-width:200px">
                            <label style="font-size:12.5px;font-weight:600;color:#4a5568;display:block;margin-bottom:6px">Cập nhật trạng thái</label>
                            <select name="status" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;background:#fff">
                                <option value="pending"    <?= $row['status']==='pending'    ?'selected':'' ?>>⏳ Chờ xử lý</option>
                                <option value="processing" <?= $row['status']==='processing' ?'selected':'' ?>>🔄 Đang xử lý</option>
                                <option value="resolved"   <?= $row['status']==='resolved'   ?'selected':'' ?>>✅ Đã giải quyết</option>
                            </select>
                        </div>
                        <div style="flex:3;min-width:280px">
                            <label style="font-size:12.5px;font-weight:600;color:#4a5568;display:block;margin-bottom:6px">Ghi chú phản hồi cho người dùng</label>
                            <textarea name="admin_note" rows="3"
                                style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13.5px;resize:vertical;box-sizing:border-box"
                                placeholder="Nhập nội dung phản hồi, hướng giải quyết..."><?= htmlspecialchars($row['admin_note'] ?? '') ?></textarea>
                        </div>
                        <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:2px">
                            <button type="submit"
                                style="padding:10px 20px;background:#1e73be;color:#fff;border:none;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer">
                                💾 Lưu
                            </button>
                            <a href="?filter=<?= $filter ?>"
                                style="padding:10px 16px;background:#f1f5f9;color:#64748b;border-radius:8px;font-size:13.5px;font-weight:600;text-decoration:none">
                                Hủy
                            </a>
                        </div>
                    </div>
                </form>
            </td>
        </tr>
        <?php endif; ?>

    <?php endwhile; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include "../includes/admin_footer.php"; ?>
