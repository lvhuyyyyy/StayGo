<?php
// --------------------------------------------------------
// security.php — Đặt tại: includes/security.php
// Nhúng vào đầu mỗi file cần bảo mật:
// require_once __DIR__ . '/../includes/security.php';
// --------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) session_start();

// ========================================================
// CSRF TOKEN
// ========================================================

// Secret dùng cho stateless CSRF (đổi nếu cần rotate)
define('_CSRF_SECRET', 'staygo_csrf_lvhuy_2024_secure');

/**
 * Tạo CSRF token stateless: HMAC(session_id + ngày).
 * Hoạt động đúng kể cả khi Railway dùng nhiều instance vì
 * session_id lấy từ cookie nên giống nhau trên mọi instance.
 */
function csrf_token(): string {
    $sid = session_id() ?: 'nosid';
    $token = hash_hmac('sha256', $sid . '|' . date('Ymd'), _CSRF_SECRET);
    $_SESSION['csrf_token'] = $token; // giữ cho tương thích
    return $token;
}

/**
 * Xuất hidden input chứa CSRF token
 * Dùng trong form: <?= csrf_field() ?>
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Kiểm tra CSRF token từ POST request (stateless, cross-instance safe)
 */
function csrf_verify(): bool {
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($submitted)) return false;

    $sid = session_id() ?: 'nosid';

    // Chấp nhận token hôm nay và hôm qua (tránh lỗi khi qua 00:00)
    $today     = hash_hmac('sha256', $sid . '|' . date('Ymd'), _CSRF_SECRET);
    $yesterday = hash_hmac('sha256', $sid . '|' . date('Ymd', strtotime('-1 day')), _CSRF_SECRET);

    if (hash_equals($today, $submitted) || hash_equals($yesterday, $submitted)) {
        return true;
    }

    // Fallback: so sánh với token cũ trong session (dev / backward compat)
    if (!empty($_SESSION['csrf_token'])) {
        return hash_equals($_SESSION['csrf_token'], $submitted);
    }

    return false;
}

/**
 * Kiểm tra CSRF và dừng nếu không hợp lệ
 */
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.'
        ]));
    }
}

// ========================================================
// FILE UPLOAD VALIDATION
// ========================================================

/**
 * Validate file ảnh upload
 */
function validate_image_upload(
    array $file,
    int $max_size = 5242880,
    array $allowed_ext = ['jpg','jpeg','png','webp']
): array {

    // 1. Kiểm tra lỗi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File quá lớn (vượt giới hạn server).',
            UPLOAD_ERR_FORM_SIZE  => 'File quá lớn (vượt giới hạn form).',
            UPLOAD_ERR_PARTIAL    => 'File chỉ được upload một phần.',
            UPLOAD_ERR_NO_FILE    => 'Không có file được chọn.',
            UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm.',
            UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file.',
        ];
        return [
            'success' => false,
            'message' => $errors[$file['error']] ?? 'Lỗi upload không xác định.'
        ];
    }

    // 2. Kiểm tra kích thước
    if ($file['size'] > $max_size) {
        $mb = round($max_size / 1048576, 1);
        return [
            'success' => false,
            'message' => "File quá lớn! Tối đa {$mb}MB."
        ];
    }

    // 3. Kiểm tra extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        return [
            'success' => false,
            'message' => "Chỉ chấp nhận: " . implode(', ', $allowed_ext)
        ];
    }

    // 4. Kiểm tra MIME type
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);

    if (!in_array($mime, $allowed_mime)) {
        return [
            'success' => false,
            'message' => 'File không phải ảnh hợp lệ!'
        ];
    }

    // 5. Kiểm tra ảnh thật
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return [
            'success' => false,
            'message' => 'File không phải ảnh hợp lệ!'
        ];
    }

    // 6. Kiểm tra kích thước ảnh
    [$width, $height] = $image_info;
    if ($width < 10 || $height < 10) {
        return [
            'success' => false,
            'message' => 'Ảnh quá nhỏ hoặc không hợp lệ!'
        ];
    }

    return [
        'success' => true,
        'message' => 'OK',
        'ext' => $ext,
        'mime' => $mime
    ];
}

/**
 * Tạo tên file an toàn
 */
function safe_filename(string $prefix, string $ext): string {
    return $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}

// ========================================================
// INPUT SANITIZATION
// ========================================================

/**
 * Làm sạch chuỗi
 */
function clean_input(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Làm sạch số
 */
function clean_int($input): int {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Làm sạch email
 */
function clean_email(string $input): string {
    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
}