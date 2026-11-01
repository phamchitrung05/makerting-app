<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu vào cho lệnh sinh ảnh AI.
 * =====================================================================
 *
 * Truyền qua `CoreAiGatewayContract::generateImage()`. Hỗ trợ prompt
 * chính, negative prompt, tỷ lệ khung hình và ảnh tham chiếu chủ thể/DNA
 * (visual_style.reference) để giữ đặc trưng khi sinh.
 *
 * `aspectRatio` là chuỗi 'W:H' ('16:9','9:16','1:1','4:3','3:4') — driver
 * map sang kích thước cụ thể của provider. `referenceImages` là mảng
 * ['mime','data'|'url'] giống attachments của structured request nhưng
 * dùng cho bối cảnh sinh ảnh (character/DNA preservation).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : prompt, negativePrompt?, aspectRatio?, referenceImages?,
 *          options?, providerKey?, modelId?, module, operation.
 * - OUTPUT: readonly object → driver chuyển thành request sinh ảnh.
 * =====================================================================
 */
final readonly class AiImageRequest
{
    /**
     * CHỨC NĂNG: khởi tạo request sinh ảnh.
     * INPUT: prompt, negativePrompt?, aspectRatio, referenceImages?,
     *        options?, providerKey?, modelId?, module, operation.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $prompt,
        public ?string $negativePrompt = null,
        public string $aspectRatio = '1:1',
        public array $referenceImages = [],
        public array $options = [],
        public ?string $providerKey = null,
        public ?string $modelId = null,
        public string $module = 'core',
        public string $operation = 'image',
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Có ảnh tham chiếu không (DNA/subject preservation).
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasReferences(): bool
    {
        return $this->referenceImages !== [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Capability cần thiết (luôn image_generation).
     * INPUT: không. OUTPUT: string CAP_*.
     * =====================================================================
     */
    public function requiredCapability(): string
    {
        return \Modules\CoreAi\Models\AiModel::CAP_IMAGE_GENERATION;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.input_json (không chứa base64).
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toRunInput(): array
    {
        return [
            'prompt' => $this->prompt,
            'negative_prompt' => $this->negativePrompt,
            'aspect_ratio' => $this->aspectRatio,
            'reference_count' => count($this->referenceImages),
            'options' => $this->options,
            'provider_key' => $this->providerKey,
            'model_id' => $this->modelId,
            'module' => $this->module,
            'operation' => $this->operation,
        ];
    }
}