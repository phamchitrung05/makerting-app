<?php

namespace Modules\Media\Actions;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\Exceptions\AiGatewayException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bước 1 — Gọi AI văn bản/vision gợi ý prompt tạo ảnh.
 * =====================================================================
 *
 * Nhận mô tả ý tưởng (`description`) và/hoặc ảnh tham chiếu (`referenceImage`)
 * kèm phong cách (`style`) + tỉ lệ khung hình (`aspectRatio`), nạp prompt nền
 * từ `config('media.prompt.ai_image_suggest')`, chuẩn hóa ảnh qua
 * PrepareAiReferenceImageAction rồi gọi `CoreAiGatewayContract::executeStructured()`
 * để sinh prompt chi tiết tối ưu cho mô hình tạo ảnh ở Bước 2.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : ?string $description, ?UploadedFile $referenceImage, ?string $style, ?string $aspectRatio.
 * - OUTPUT: array{prompt:string, model:?string, provider:?string, has_reference_image:bool}.
 * =====================================================================
 */
class SuggestAiImagePromptAction
{
    public function __construct(
        private readonly CoreAiGatewayContract $gateway,
        private readonly PrepareAiReferenceImageAction $prepareReference,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Phân tích mô tả + ảnh tham chiếu qua AI văn bản để lấy prompt.
     * =====================================================================
     * INPUT: ?string $description, ?UploadedFile $referenceImage, ?string $style, ?string $aspectRatio.
     * OUTPUT: array{prompt:string, model:?string, provider:?string, has_reference_image:bool}.
     * EXCEPTION: InvalidArgumentException | AiGatewayException.
     * =====================================================================
     */
    public function execute(
        ?string $description,
        ?UploadedFile $referenceImage = null,
        ?string $style = null,
        ?string $aspectRatio = null,
    ): array {
        $cleanDescription = is_string($description) ? trim($description) : '';
        if ($cleanDescription === '' && $referenceImage === null) {
            throw new InvalidArgumentException('Vui lòng nhập mô tả hình ảnh hoặc chọn ảnh tham chiếu để AI gợi ý prompt.');
        }

        $attachments = [];
        if ($referenceImage !== null) {
            $prepared = $this->prepareReference->scanAndRead(
                $referenceImage,
                maxInputBytes: 10 * 1024 * 1024,
                maxDimension: 1536,
                maxOutputBytes: 3_500_000,
                allowedOutputMimes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            );
            $attachments[] = [
                'mime' => $prepared['mime'],
                'data' => base64_encode($prepared['binary']),
            ];
        }

        $promptConfig = (array) config('media.prompt.ai_image_suggest', []);
        $limits = (array) ($promptConfig['limits'] ?? []);
        $options = (array) ($promptConfig['options'] ?? []);

        $schemaMin = max(1, (int) ($limits['schema_prompt_min_length'] ?? 10));
        $schemaMax = max($schemaMin, (int) ($limits['schema_prompt_max_length'] ?? 1800));
        $promptMax = max(1, (int) ($limits['prompt_max_length'] ?? 2000));

        $schema = [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'minLength' => $schemaMin,
                    'maxLength' => $schemaMax,
                ],
            ],
            'required' => ['prompt'],
            'additionalProperties' => false,
        ];

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($promptConfig),
            ],
            [
                'role' => 'user',
                'content' => $this->userPrompt($promptConfig, $cleanDescription, $attachments !== [], $style, $aspectRatio),
            ],
        ];

        $response = $this->gateway->executeStructured(new AiStructuredRequest(
            messages: $messages,
            schema: $schema,
            attachments: $attachments,
            options: [
                'temperature' => (float) ($options['temperature'] ?? 0.4),
                'max_tokens' => max(100, (int) ($options['max_tokens'] ?? 700)),
            ],
            module: 'media',
            operation: 'ai_image_prompt_suggest',
        ));

        $suggestedPrompt = $this->extractPrompt($response->data['prompt'] ?? null, $response->text);
        if ($suggestedPrompt === '') {
            throw AiGatewayException::providerError(
                'Model AI không trả về prompt hợp lệ.',
                ['provider' => $response->providerKey, 'model' => $response->remoteModelId]
            );
        }

        return [
            'prompt' => mb_substr($suggestedPrompt, 0, $promptMax),
            'model' => $response->remoteModelId,
            'provider' => $response->providerKey,
            'has_reference_image' => $attachments !== [],
        ];
    }

    /**
     * CHỨC NĂNG: Lấy system prompt dạng chuỗi Nowdoc từ config/prompt.php.
     * INPUT: array $promptConfig.
     * OUTPUT: string.
     */
    private function systemPrompt(array $promptConfig): string
    {
        $rawSystem = $promptConfig['system'] ?? null;
        $normalized = is_string($rawSystem) ? str_replace(["\r\n", "\r"], "\n", $rawSystem) : '';
        $system = trim($normalized);

        if ($system === '') {
            throw AiGatewayException::validation('Thiếu cấu hình media.prompt.ai_image_suggest.system.');
        }

        return $system;
    }

    /**
     * CHỨC NĂNG: Đóng gói đầu vào thành JSON sạch gửi trong user message (chống nối chuỗi/prompt injection).
     * INPUT: array $promptConfig, string $description, bool $hasImage, ?string $style, ?string $aspectRatio.
     * OUTPUT: string JSON.
     */
    private function userPrompt(
        array $promptConfig,
        string $description,
        bool $hasImage,
        ?string $style,
        ?string $aspectRatio,
    ): string {
        $styles = (array) ($promptConfig['styles'] ?? []);
        $normalizedStyle = ($style !== null && isset($styles[$style])) ? $style : null;
        $normalizedRatio = ($aspectRatio !== null && trim($aspectRatio) !== '') ? trim($aspectRatio) : null;

        $payload = [
            'task' => 'suggest_image_generation_prompt',
            'has_reference_image' => $hasImage,
            'description' => $description !== '' ? $description : null,
            'style' => $normalizedStyle,
            'style_guidance' => $normalizedStyle !== null ? (string) $styles[$normalizedStyle] : null,
            'aspect_ratio' => $normalizedRatio,
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    private function extractPrompt(mixed $structuredPrompt, string $fallbackText): string
    {
        $candidate = is_string($structuredPrompt) ? trim($structuredPrompt) : '';

        if ($candidate === '' && trim($fallbackText) !== '') {
            $decoded = json_decode(trim($fallbackText), true);
            if (is_array($decoded) && is_string($decoded['prompt'] ?? null)) {
                $candidate = trim($decoded['prompt']);
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', $candidate));
    }
}
