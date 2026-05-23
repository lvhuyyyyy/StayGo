<?php
// --------------------------------------------------------
// security.php — Đặt tại: includes/security.php
// Nhúng vào đầu mỗi file cần bảo mật:
// require_once __DIR__ . '/../includes/security.php';
// --------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) session_start();

// ========================================================
// CSRF TOKEN — stateful, per-session, 1h expiry, single-use rotation
// ========================================================

function csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || time() > ($_SESSION['csrf_exp'] ?? 0)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_exp']   = time() + 3600;
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type='hidden' name='csrf_token' value='$t'>";
}

function csrf_verify(): bool {
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($submitted)) return false;

    $valid = !empty($_SESSION['csrf_token'])
          && time() <= ($_SESSION['csrf_exp'] ?? 0)
          && hash_equals($_SESSION['csrf_token'], $submitted);

    if ($valid) {
        // Rotate ngay sau khi dùng — token cũ không thể tái sử dụng
        unset($_SESSION['csrf_token'], $_SESSION['csrf_exp']);
    }

    return $valid;
}

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
function clean_int(mixed $input): int {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Làm sạch email
 */
function clean_email(string $input): string {
    return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
}