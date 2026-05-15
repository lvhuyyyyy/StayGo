<?php
/**
 * Ghi nhật ký hành động admin vào bảng activity_log.
 *
 * @param mysqli $conn
 * @param string $action   Ví dụ: 'confirm_booking', 'delete_user'
 * @param string $target   Ví dụ: 'booking', 'user', 'hotel'
 * @param int    $target_id
 * @param string $detail   Mô tả chi tiết (tuỳ chọn)
 */
if (!function_exists('log_activity')):
function log_activity($conn, string $action, string $target = '', int $target_id = 0, string $detail = ''): void {
    if (session_status() === PHP_SESSION_NONE) return;

    $admin_id   = (int)($_SESSION['admin_id']   ?? 0);
    $admin_name = $conn->real_escape_string($_SESSION['admin_name'] ?? 'Admin');
    $action_esc = $conn->real_escape_string($action);
    $target_esc = $conn->real_escape_string($target);
    $detail_esc = $conn->real_escape_string($detail);
    $ip         = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');

    $conn->query("INSERT INTO activity_log (admin_id, admin_name, action, target, target_id, detail, ip)
        VALUES ($admin_id, '$admin_name', '$action_esc', '$target_esc', $target_id, '$detail_esc', '$ip')");
}
endif;
