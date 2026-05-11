<?php
// ═══════════════════════════════════════════════════════════════════
// VNPay — đăng ký sandbox tại https://sandbox.vnpayment.vn/devreg/
// ═══════════════════════════════════════════════════════════════════
define('VNPAY_TMN_CODE',    getenv('VNPAY_TMN_CODE')    ?: 'DEMOV210');
define('VNPAY_HASH_SECRET', getenv('VNPAY_HASH_SECRET') ?: 'RAOEXHYVSDDIIENYWSLDIIZTANXUXZFJ');
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
        'vnp_IpAddr'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'vnp_CreateDate' => date('YmdHis'),
        'vnp_ExpireDate' => date('YmdHis', strtotime('+15 minutes')),
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
