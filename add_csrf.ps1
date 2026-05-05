# ════════════════════════════════════════════
# Script tự động thêm CSRF protection
# Đặt tại: tour_khach_san_project/
# Chạy: .\apply_csrf.ps1
# ════════════════════════════════════════════

$base = "C:\xampp\htdocs\tour_khach_san_project"

# ── Danh sách file cần thêm CSRF ──
$form_files = @(
    "auth\login.php",
    "auth\register.php",
    "auth\forgot_password.php",
    "auth\reset_password.php",
    "auth\verify_otp.php",
    "admin_lvhuy_kontum\add_hotel.php",
    "admin_lvhuy_kontum\blog_form.php",
    "admin_lvhuy_kontum\edit_room.php",
    "admin_lvhuy_kontum\edit_user.php",
    "admin_lvhuy_kontum\hotel_images.php",
    "admin_lvhuy_kontum\manage_hotel.php",
    "pages\payment.php",
    "pages\edit_profile.php"
)

# ── Danh sách file cần thêm validate upload ──
$upload_files = @(
    "admin_lvhuy_kontum\add_hotel.php",
    "admin_lvhuy_kontum\blog_form.php",
    "admin_lvhuy_kontum\edit_room.php",
    "admin_lvhuy_kontum\hotel_images.php",
    "admin_lvhuy_kontum\manage_hotel.php",
    "admin_lvhuy_kontum\upload_image.php"
)

$fixed = 0
$skipped = 0

Write-Host ""
Write-Host "=== THEM CSRF VAO FORM FILES ===" -ForegroundColor Cyan

foreach ($file in $form_files) {
    $path = Join-Path $base $file
    if (-not (Test-Path $path)) {
        Write-Host "  [SKIP] $file - khong tim thay" -ForegroundColor Yellow
        $skipped++
        continue
    }

    $content = Get-Content $path -Raw -Encoding UTF8

    # 1. Thêm require security.php nếu chưa có
    if ($content -notmatch "security\.php") {
        # Thêm sau dòng <?php đầu tiên hoặc sau session_start()
        if ($content -match "session_start\(\);") {
            $content = $content -replace "(session_start\(\);)", "`$1`nrequire_once __DIR__ . '/../includes/security.php';"
        } elseif ($content -match "require_once.*database\.php") {
            $content = $content -replace "(require_once[^\n]*database\.php[^\n]*)", "`$1`nrequire_once __DIR__ . '/../includes/security.php';"
        } else {
            $content = $content -replace "(<\?php)", "<?php`nrequire_once __DIR__ . '/../includes/security.php';"
        }
    }

    # 2. Thêm csrf_check() sau METHOD POST check nếu chưa có
    if ($content -notmatch "csrf_check\(\)") {
        $content = $content -replace '(\$_SERVER\["REQUEST_METHOD"\]\s*==\s*"POST"\s*\|\|\s*\$_SERVER\[.REQUEST_METHOD.\]\s*===\s*.POST.)', '$1) {
    csrf_check();
} if (true'
        # Cách đơn giản hơn: thêm vào đầu block POST
        if ($content -notmatch "csrf_check\(\)") {
            $content = $content -replace '(\$_SERVER\[.REQUEST_METHOD.\]\s*[=!]=+\s*.POST.\s*\)(\s*\{|\s*\))', '$1$2
    csrf_check();'
        }
    }

    # 3. Thêm csrf_field() vào form nếu chưa có
    if ($content -notmatch "csrf_field\(\)") {
        # Thêm sau <form method="POST" hoặc <form method="post"
        $content = $content -replace '(<form[^>]*method\s*=\s*["\']?POST["\']?[^>]*>)', '$1
            <?= csrf_field() ?>'
    }

    Set-Content $path $content -Encoding UTF8
    Write-Host "  [FIXED] $file" -ForegroundColor Green
    $fixed++
}

Write-Host ""
Write-Host "=== THEM VALIDATE UPLOAD VAO FILES ===" -ForegroundColor Cyan

foreach ($file in $upload_files) {
    $path = Join-Path $base $file
    if (-not (Test-Path $path)) {
        Write-Host "  [SKIP] $file - khong tim thay" -ForegroundColor Yellow
        $skipped++
        continue
    }

    $content = Get-Content $path -Raw -Encoding UTF8

    # Thêm require security.php nếu chưa có
    if ($content -notmatch "security\.php") {
        $content = $content -replace "(require_once[^\n]*database\.php[^\n]*)", "`$1`nrequire_once __DIR__ . '/../includes/security.php';"
    }

    # Thêm validate trước move_uploaded_file nếu chưa có
    if ($content -notmatch "validate_image_upload") {
        $content = $content -replace "(move_uploaded_file\()", '// Validate upload
        $upload_result = validate_image_upload($_FILES[array_key_first($_FILES)]);
        if (!$upload_result["success"]) { die(json_encode(["success"=>false,"message"=>$upload_result["message"]])); }
        $1('
    }

    Set-Content $path $content -Encoding UTF8
    Write-Host "  [FIXED] $file" -ForegroundColor Green
    $fixed++
}

Write-Host ""
Write-Host "════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Da fix: $fixed file" -ForegroundColor Green
Write-Host "  Bo qua: $skipped file" -ForegroundColor Yellow
Write-Host "  XONG! Kiem tra lai bang add_csrf.ps1" -ForegroundColor Green