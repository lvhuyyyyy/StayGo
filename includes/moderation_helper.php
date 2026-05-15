<?php
/**
 * moderate_review(string $text): array
 *
 * Gọi Claude API để kiểm duyệt nội dung đánh giá trước khi lưu vào DB.
 * Return: ['status'=>'safe'|'violated'|'uncertain', 'labels'=>[], 'reason'=>'', 'suggestion'=>'', 'confidence'=>0.0]
 *
 * - safe      → lưu với is_active=1
 * - violated  → báo lỗi cho người dùng, không lưu
 * - uncertain → lưu với is_active=0 (admin duyệt thủ công)
 * - Nếu API không khả dụng (key trống / timeout) → trả về safe để không chặn UX
 */
function moderate_review(string $text): array {
    $safe_fallback = ['status' => 'safe', 'labels' => [], 'reason' => '', 'suggestion' => '', 'confidence' => 1.0];

    $api_key = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : getenv('ANTHROPIC_API_KEY');
    if (!$api_key) return $safe_fallback;

    $system_prompt = <<<'PROMPT'
Bạn là hệ thống kiểm duyệt nội dung tự động cho nền tảng đặt phòng khách sạn trực tuyến (OTA). Nhiệm vụ của bạn là phân tích nội dung đánh giá do khách hàng viết và xác định xem nội dung đó có vi phạm tiêu chuẩn cộng đồng hay không trước khi cho phép đăng lên hệ thống.

PHÂN LOẠI VI PHẠM:
1. HATE_SPEECH — Ngôn ngữ kỳ thị, phân biệt vùng miền, dân tộc, tôn giáo, giới tính, hoặc bất kỳ nhóm người nào.
2. PERSONAL_ATTACK — Xúc phạm, lăng mạ trực tiếp cá nhân cụ thể như nhân viên, chủ khách sạn, hoặc bất kỳ người nào được nhắc đến trong đánh giá.
3. PROFANITY — Sử dụng từ tục, chửi thề, ngôn ngữ thô tục, kể cả dạng viết tắt, lách chính tả, hay dùng ký tự thay thế (ví dụ: đ*t, c@c, …).
4. SPAM — Chèn số điện thoại, đường link, địa chỉ email, lời quảng cáo, kêu gọi đặt phòng ngoài hệ thống, hoặc nội dung hoàn toàn không liên quan đến trải nghiệm lưu trú.
5. FAKE_REVIEW — Dấu hiệu review không trung thực: được trả tiền để viết, đổi quà lấy sao, nội dung chung chung không có trải nghiệm thực tế, hoặc rõ ràng là sao chép từ nguồn khác.
6. THREAT — Đe dọa, tống tiền, hoặc gây áp lực theo hướng tiêu cực đối với khách sạn hoặc cá nhân.

LƯU Ý QUAN TRỌNG:
- Đánh giá tiêu cực, phê bình gay gắt về chất lượng phòng, dịch vụ kém, đồ ăn không ngon... là HOÀN TOÀN HỢP LỆ nếu không dùng từ ngữ xúc phạm.
- Phân biệt rõ "phê bình dịch vụ" với "tấn công cá nhân".
- Không bị đánh lừa bởi cách viết lách chính tả cố ý để né lọc. Phân tích theo ngữ nghĩa thực tế.
- Hỗ trợ cả tiếng Việt, tiếng Anh và các ngôn ngữ khác.

ĐẦU RA: Trả về duy nhất một JSON object, không kèm bất kỳ văn bản nào khác, không có markdown, không có dấu backtick:
{"status":"safe"|"violated"|"uncertain","labels":[],"reason":"","suggestion":"","confidence":0.0}
PROMPT;

    $payload = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 300,
        'system'     => $system_prompt,
        'messages'   => [['role' => 'user', 'content' => $text]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        error_log('StayGo moderation error: HTTP ' . $http_code . ' — ' . $response);
        return $safe_fallback;
    }

    $data    = json_decode($response, true);
    $content = $data['content'][0]['text'] ?? '{}';
    $result  = json_decode($content, true);

    if (!$result || !isset($result['status'])) {
        error_log('StayGo moderation: invalid JSON from Claude — ' . $content);
        return ['status' => 'uncertain', 'labels' => [], 'reason' => 'Không thể phân tích nội dung, vui lòng thử lại.', 'suggestion' => '', 'confidence' => 0.5];
    }

    return $result;
}
