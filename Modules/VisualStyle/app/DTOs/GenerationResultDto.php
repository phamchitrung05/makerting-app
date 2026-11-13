<?php

namespace Modules\VisualStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO kết quả sinh ảnh từ DNA — đầu ra GenerateImageFromDnaAction.
 * =====================================================================
 *
 * Báo cho caller (controller, dialog FE, MediaPicker tab AI) biết ảnh đã
 * được lưu vào `media_assets` với id nào + URL preview; kèm prompt đã dùng
 * để hiển thị provenance.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : mediaAssetId, previewUrl, promptUsed, aspectRatio, aiRunId.
 * - OUTPUT: readonly object → serialize ra API response.
 * =====================================================================
 */
final readonly class GenerationResultDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO kết quả sinh ảnh.
     * INPUT: mediaAssetId, previewUrl, promptUsed, aspectRatio, aiRunId.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public int $mediaAssetId,
        public ?string $previewUrl = null,
        public string $promptUsed = '',
        public string $aspectRatio = '16:9',
        public ?string $aiRunId = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ra API response.
     * =====================================================================
     * INPUT: không. OUTPUT: array key snake_case.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'media_asset_id' => $this->mediaAssetId,
            'preview_url'    => $this->previewUrl,
            'prompt_used'    => $this->promptUsed,
            'aspect_ratio'   => $this->aspectRatio,
            'ai_run_id'      => $this->aiRunId,
        ];
    }
}
