<?php

namespace Modules\CoreAi\Services;

use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiTranscribeRequest;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ data mẫu (probe fixtures) cho Test Ping Model.
 * =====================================================================
 * Giữ toàn bộ payload tối thiểu trong bộ nhớ (không đọc file ngoài) cho 6
 * capability — dùng khi bấm "Test ping" trên từng model. Quy tắc "Phương án A
 * (gộp 1 request)": model có text/structured/vision (hoặc capabilities rỗng
 * mới sync về) → gộp vào 1 request structured() duy nhất; model chuyên biệt
 * image/tts/stt → gọi op riêng với data mẫu tương ứng.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - textRequest()      : prompt + (schema nếu structured_out) + (ảnh 1x1 nếu
 *                        vision) gộp vào 1 structured() call.
 * - imageRequest()     : AiImageRequest cho image_generation.
 * - speechRequest()    : AiSpeechRequest cho tts.
 * - transcribeRequest(): AiTranscribeRequest cho stt (WAV 0.1s tạo sẵn).
 * - schema()           : JSON schema tối giản {ok:boolean}.
 * - png1x1()/wav()     : binary mẫu (decode base64) cho vision/stt.
 * - maxTokens()        : 16; null nếu model reasoning (o1/o3/gpt-5-*).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : capabilities[] + remote_model_id (quyết gộp request + tokens).
 * - OUTPUT: Request DTO tương ứng cho driver.
 * =====================================================================
 */
class AiModelProbeFixtures
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Request structured GỘP cho text + structured_output + vision.
     * =====================================================================
     * Phương án A: 1 request duy nhất — luôn prompt text; thêm schema nếu
     * model có structured_output; thêm ảnh 1x1 nếu có vision.
     * INPUT: caps[] + remote_model_id. OUTPUT: AiStructuredRequest.
     * =====================================================================
     */
    public static function textRequest(array $caps, string $modelId): AiStructuredRequest
    {
        $schema = in_array('structured_output', $caps, true) ? self::schema() : null;
        $attachments = in_array('vision', $caps, true)
            ? [['mime' => 'image/png', 'data' => self::png1x1()]]
            : [];

        $messages = [
            ['role' => 'system', 'content' => 'You are a health check probe.'],
            ['role' => 'user', 'content' => 'Reply with ok'],
        ];

        $opts = [];
        $mt = self::maxTokens($modelId);
        if ($mt !== null) {
            $opts['max_tokens'] = $mt;
        }

        return new AiStructuredRequest(
            messages: $messages,
            prompt: 'Reply with ok',
            schema: $schema,
            attachments: $attachments,
            options: $opts,
            module: 'coreai',
            operation: 'probe',
        );
    }

    /** INPUT: modelId. OUTPUT: AiImageRequest cho image_generation probe. */
    public static function imageRequest(string $modelId): AiImageRequest
    {
        return new AiImageRequest(
            prompt: 'A simple white dot on black background',
            aspectRatio: '1:1',
            module: 'coreai',
            operation: 'probe',
        );
    }

    /** INPUT: modelId. OUTPUT: AiSpeechRequest cho tts probe. */
    public static function speechRequest(string $modelId): AiSpeechRequest
    {
        return new AiSpeechRequest(
            text: 'ok',
            format: 'mp3',
            module: 'coreai',
            operation: 'probe',
        );
    }

    /** INPUT: modelId. OUTPUT: AiTranscribeRequest cho stt probe (WAV 0.1s). */
    public static function transcribeRequest(string $modelId): AiTranscribeRequest
    {
        return new AiTranscribeRequest(
            audio: self::wav(),
            mime: 'audio/wav',
            withTimings: false,
            module: 'coreai',
            operation: 'probe',
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: JSON schema tối giản cho structured_output probe.
     * INPUT: không. OUTPUT: array schema {ok:boolean}.
     * =====================================================================
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['ok' => ['type' => 'boolean']],
            'required' => ['ok'],
            'additionalProperties' => false,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ảnh PNG 1x1 hợp lệ cho vision probe (decode base64 ~70B).
     * INPUT: không. OUTPUT: binary PNG.
     * =====================================================================
     */
    public static function png1x1(): string
    {
        static $cache = null;

        return $cache ??= base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Âm thanh WAV PCM mono 8kHz dài 0.1s cho stt probe.
     * INPUT: không. OUTPUT: binary WAV (RIFF chuẩn).
     * =====================================================================
     */
    public static function wav(): string
    {
        static $cache = null;

        return $cache ??= base64_decode('UklGRmQGAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YUAGAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: max_tokens probe; null với model reasoning (o1/o3/gpt-5-*)
     * =====================================================================
     * Các model reasoning không nhận tham số max_tokens -> bỏ trống để
     * provider tự mặc định, tránh lỗi "unsupported parameter" khi ping.
     * INPUT: remote_model_id. OUTPUT: ?int.
     * =====================================================================
     */
    public static function maxTokens(string $modelId): ?int
    {
        return preg_match('/^(o\d|gpt-5)/i', $modelId) ? null : 16;
    }
}
