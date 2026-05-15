<?php
// ═══════════════════════════════════════════════════════════════════
// Casso — kết nối tài khoản ngân hàng, tự động xác nhận chuyển khoản
// Đăng ký tại: https://casso.vn → Lấy API Token trong Settings
// Webhook URL cấu hình trong Casso: https://yourdomain.com/pages/casso_webhook.php
// ═══════════════════════════════════════════════════════════════════
define('CASSO_API_TOKEN',   getenv('CASSO_API_TOKEN')   ?: '');  // ← Điền token từ Casso dashboard

// Thông tin tài khoản ngân hàng Platform nhận tiền
define('BANK_ID',           getenv('BANK_ID')           ?: 'ICB');            // VietinBank = ICB
define('BANK_ACCOUNT_NO',   getenv('BANK_ACCOUNT_NO')   ?: '107645394761');
define('BANK_ACCOUNT_NAME', getenv('BANK_ACCOUNT_NAME') ?: 'LE VAN HUY');
define('BANK_NAME',         getenv('BANK_NAME')         ?: 'VietinBank');
define('BANK_BRANCH',       getenv('BANK_BRANCH')       ?: 'Kon Tum');

// ═══════════════════════════════════════════════════════════════════
// VNPay — đăng ký sandbox tại https://sandbox.vnpayment.vn/devreg/
// ═══════════════════════════════════════════════════════════════════
define('VNPAY_TMN_CODE',    getenv('VNPAY_TMN_CODE')    ?: '7JMRFWZO');
define('VNPAY_HASH_SECRET', getenv('VNPAY_HASH_SECRET') ?: 'KPR0UD30I0V4HOQ88ELFMOTN53K0QSSL');
define('VNPAY_URL',         'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

// ═══════════════════════════════════════════════════════════════════
// MoMo — thông tin test chính thức: https://developers.momo.vn/
// ═══════════════════════════════════════════════════════════════════
define('MOMO_PARTNER_CODE', getenv('MOMO_PARTNER_CODE') ?: 'MOMO');
define('MOMO_ACCESS_KEY',   getenv('MOMO_ACCESS_KEY')   ?: 'F8BBA842ECF85');
define('MOMO_SECRET_KEY',   getenv('MOMO_SECRET_KEY')   ?: 'K951B6PE1waDMi640xX08PD3vg6EkVlz');
define('MOMO_ENDPOINT',     'https://test-payment.momo.vn/v2/gateway/api/create');

/**
 * Trả về base URL của dự án (tự động detect http/https, host, subfolder).
 * Gọi từ bất kỳ file nào trong project.
 */
function site_url(string $path = ''): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $proto    = $https ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $rel = ($docRoot && strncmp($projRoot, $docRoot, strlen($docRoot)) === 0)
         ? substr($projRoot, strlen($docRoot)) : '';
    return $proto . '://' . $host . rtrim($rel, '/') . '/' . ltrim($path, '/');
}

// ───────────────────────────────────────────────────────────────────
// VNPay: tạo URL thanh toán (browser redirect)
// ───────────────────────────────────────────────────────────────────
function vnpay_build_url(string $order_code, float $amount): string {
    $params = [
        'vnp_Version'    => '2.1.0',
        'vnp_Command'    => 'pay',
        'vnp_TmnCode'    => VNPAY_TMN_CODE,
        'vnp_Amount'     => (int)($amount * 100),
        'vnp_CurrCode'   => 'VND',
        'vnp_TxnRef'     => $order_code,
        'vnp_OrderInfo'  => 'Thanh toan dat phong ' . $order_code,
        'vnp_OrderType'  => 'other',
        'vnp_Locale'     => 'vn',
        'vnp_ReturnUrl'  => site_url('pages/vnpay_return.php'),
        'vnp_IpAddr'     => (($_SERVER['REMOTE_ADDR'] ?? '') === '::1') ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        'vnp_CreateDate' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('YmdHis'),
        'vnp_ExpireDate' => (new DateTime('+15 minutes', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('YmdHis'),
    ];
    ksort($params);

    $hashData = '';
    $query    = '';
    foreach ($params as $k => $v) {
        $hashData .= ($hashData ? '&' : '') . urlencode($k) . '=' . urlencode($v);
        $query    .= urlencode($k) . '=' . urlencode($v) . '&';
    }
    $secureHash = hash_hmac('sha512', $hashData, VNPAY_HASH_SECRET);
    return VNPAY_URL . '?' . $query . 'vnp_SecureHash=' . $secureHash;
}

/**
 * Xác minh chữ ký VNPay từ mảng GET params.
 * Trả về true nếu hợp lệ.
 */
function vnpay_verify_signature(array $params): bool {
    $receivedHash = $params['vnp_SecureHash'] ?? '';
    $inputData = [];
    foreach ($params as $k => $v) {
        if ($k !== 'vnp_SecureHash' && $k !== 'vnp_SecureHashType') {
            $inputData[$k] = $v;
        }
    }
    ksort($inputData);
    $hashData = '';
    foreach ($inputData as $k => $v) {
        $hashData .= ($hashData ? '&' : '') . urlencode($k) . '=' . urlencode($v);
    }
    $computed = hash_hmac('sha512', $hashData, VNPAY_HASH_SECRET);
    return hash_equals($computed, $receivedHash);
}

// ───────────────────────────────────────────────────────────────────
// MoMo: tạo yêu cầu thanh toán qua API, trả về payUrl
// ───────────────────────────────────────────────────────────────────
function momo_create_payment(string $order_code, float $amount, int $booking_id): array {
    $requestId   = $order_code . '_' . time();
    $redirectUrl = site_url('pages/momo_return.php');
    $ipnUrl      = site_url('pages/momo_ipn.php');
    $orderInfo   = 'Thanh toan dat phong ' . $order_code;
    $extraData   = base64_encode(json_encode(['booking_id' => $booking_id]));
    $requestType = 'payWithMethod';

    $rawHash = 'accessKey='   . MOMO_ACCESS_KEY
             . '&amount='      . (int)$amount
             . '&extraData='   . $extraData
             . '&ipnUrl='      . $ipnUrl
             . '&orderId='     . $order_code
             . '&orderInfo='   . $orderInfo
             . '&partnerCode=' . MOMO_PARTNER_CODE
             . '&redirectUrl=' . $redirectUrl
             . '&requestId='   . $requestId
             . '&requestType=' . $requestType;

    $signature = hash_hmac('sha256', $rawHash, MOMO_SECRET_KEY);

    $body = json_encode([
        'partnerCode' => MOMO_PARTNER_CODE,
        'partnerName' => 'StayGo',
        'storeId'     => MOMO_PARTNER_CODE,
        'requestId'   => $requestId,
        'amount'      => (int)$amount,
        'orderId'     => $order_code,
        'orderInfo'   => $orderInfo,
        'redirectUrl' => $redirectUrl,
        'ipnUrl'      => $ipnUrl,
        'lang'        => 'vi',
        'extraData'   => $extraData,
        'requestType' => $requestType,
        'signature'   => $signature,
    ]);

    $ch = curl_init(MOMO_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        return ['success' => false, 'message' => 'Không thể kết nối MoMo: ' . $err];
    }
    $res = json_decode($response, true);
    if (($res['resultCode'] ?? -1) === 0 && !empty($res['payUrl'])) {
        return ['success' => true, 'pay_url' => $res['payUrl']];
    }
    return ['success' => false, 'message' => $res['message'] ?? 'MoMo từ chối yêu cầu. Vui lòng thử lại.'];
}

// ═══════════════════════════════════════════════════════════════════
// PayOS — cổng thanh toán Việt Nam (by VNPay)
// Đăng ký tại: https://payos.vn → Dashboard → Lấy API Keys
// ═══════════════════════════════════════════════════════════════════
define('PAYOS_CLIENT_ID',    getenv('PAYOS_CLIENT_ID')    ?: 'ae5ad459-f562-4513-9ae5-1a66cae70928');
define('PAYOS_API_KEY',      getenv('PAYOS_API_KEY')      ?: '743049b3-0896-4560-a247-d34ce146263d');
define('PAYOS_CHECKSUM_KEY', getenv('PAYOS_CHECKSUM_KEY') ?: '7ea8fef4d5ad3de4bc95128c4d8e193aa91a671801c32a683b0fd6ceb8d7c601');
define('PAYOS_ENDPOINT',     'https://api-merchant.payos.vn/v2/payment-requests');

/**
 * Tạo link thanh toán PayOS và trả về checkoutUrl.
 * @param int    $booking_id  ID booking (dùng làm orderCode, phải là integer dương)
 * @param float  $amount      Số tiền VNĐ
 * @param string $description Mô tả (tối đa 25 ký tự, chỉ ASCII)
 * @return array ['success'=>true,'pay_url'=>'...'] hoặc ['success'=>false,'message'=>'...']
 */
function payos_create_payment(int $booking_id, float $amount, string $description): array {
    $returnUrl = site_url('pages/payos_return.php');
    $cancelUrl = site_url('pages/payos_return.php?cancel=true');

    // Chữ ký HMAC-SHA256: ghép các field theo thứ tự alphabet
    $signData  = 'amount='      . (int)$amount
               . '&cancelUrl='  . $cancelUrl
               . '&description='. $description
               . '&orderCode='  . $booking_id
               . '&returnUrl='  . $returnUrl;
    $signature = hash_hmac('sha256', $signData, PAYOS_CHECKSUM_KEY);

    $body = json_encode([
        'orderCode'   => $booking_id,
        'amount'      => (int)$amount,
        'description' => $description,
        'returnUrl'   => $returnUrl,
        'cancelUrl'   => $cancelUrl,
        'signature'   => $signature,
    ]);

    $ch = curl_init(PAYOS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-client-id: ' . PAYOS_CLIENT_ID,
            'x-api-key: '   . PAYOS_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        return ['success' => false, 'message' => 'Không thể kết nối PayOS: ' . $err];
    }
    $res = json_decode($response, true);
    if (($res['code'] ?? '') === '00' && !empty($res['data']['checkoutUrl'])) {
        return ['success' => true, 'pay_url' => $res['data']['checkoutUrl']];
    }
    return ['success' => false, 'message' => $res['desc'] ?? 'PayOS từ chối yêu cầu. Vui lòng thử lại.'];
}

/**
 * Xác minh chữ ký MoMo (dùng cho cả return URL GET params và IPN POST data).
 */
function momo_verify_signature(array $data): bool {
    $fields = [
        'accessKey', 'amount', 'extraData', 'message',
        'orderId', 'orderInfo', 'orderType', 'partnerCode',
        'payType', 'requestId', 'responseTime', 'resultCode', 'transId',
    ];
    $parts = [];
    foreach ($fields as $f) {
        $parts[] = $f . '=' . ($data[$f] ?? '');
    }
    $rawHash  = implode('&', $parts);
    $computed = hash_hmac('sha256', $rawHash, MOMO_SECRET_KEY);
    return hash_equals($computed, $data['signature'] ?? '');
}
