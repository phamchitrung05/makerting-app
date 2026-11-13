<?php

namespace Modules\VisualStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO prompt sinh ảnh đã ghép — đầu ra của VisualPromptBuilder.
 * =====================================================================
 *
 * Chứa prompt hoàn chỉnh (đã rải 7 lớp theo thứ tự), negative prompt tổng
 * hợp (master + shot), tỷ lệ khung hình hiệu lực và ảnh tham chiếu DNA
 * (subject preservation) — truyền thẳng vào `AiImageRequest` trong
 * GenerateImageFromDnaAction.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : prompt, negativePrompt, aspectRatio, referenceImages, layers,
 *          purpose.
 * - OUTPUT: readonly object → action chuyển thành AiImageRequest.
 * =====================================================================
 */
final readonly class CompiledImagePromptDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO prompt đã ghép.
     * INPUT: prompt (7 lớp), negativePrompt, aspectRatio ('W:H'),
     *        referenceImages (mảng ['mime','data'|'url'] cho preservation),
     *        layers (7 lớp rời để preview UI), purpose (thumbnail|hero|...).
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $prompt,
        public ?string $negativePrompt,
        public string $aspectRatio,
        public array $referenceImages = [],
        public array $layers = [],
        public ?string $purpose = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào compiled_prompt_preview / debug.
     * =====================================================================
     * INPUT: không. OUTPUT: array (bỏ referenceImages binary — quá dài).
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'prompt'            => $this->prompt,
            'negative_prompt'   => $this->negativePrompt,
            'aspect_ratio'      => $this->aspectRatio,
            'reference_count'   => count($this->referenceImages),
            'layers'            => $this->layers,
            'purpose'           => $this->purpose,
        ];
    }
}
