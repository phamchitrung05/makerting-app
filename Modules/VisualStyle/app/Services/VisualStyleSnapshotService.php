<?php

namespace Modules\VisualStyle\Services;

use Modules\VisualStyle\DTOs\VisualStyleSnapshotDto;
use Modules\VisualStyle\Models\VisualStyle;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cổng đọc DNA ảnh đã lọc trường nội bộ cho module Tầng 3.
 * =====================================================================
 *
 * Mọi consumer bên ngoài (VideoStudio, SocialPublisher, Blog, MediaPicker)
 * cần DNA ảnh để sinh/gắn prompt phải gọi service này thay vì đọc trực tiếp
 * `visual_styles.dna_json` — service strip `compiled_prompt_preview`,
 * `negative_prompt` riêng và chỉ giữ 5 nhóm + rules + taxonomy_suggestion.
 *
 * Tương tự `WritingProfileSnapshotService` — cổng một chiều DTO.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - snapshot(): Trả VisualStyleSnapshotDto của style đã usable.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : VisualStyle model.
 * - OUTPUT: VisualStyleSnapshotDto đã lọc trường nội bộ.
 * =====================================================================
 */
class VisualStyleSnapshotService
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Đóng gói DNA ảnh của style thành snapshot đọc công khai
     * =====================================================================
     *
     * INPUT:
     * - VisualStyle $style — style đã usable (analyzed/active + enabled).
     *
     * OUTPUT:
     * - VisualStyleSnapshotDto — DNA + shots + reference_media_id.
     *
     * SIDE EFFECT:
     * - Đọc model + relations; không ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    public function snapshot(VisualStyle $style): VisualStyleSnapshotDto
    {
        $dna = is_array($style->dna_json) ? $style->dna_json : [];

        $shots = $style->shots()->active()->get()->map(static fn ($shot) => [
            'code'                    => $shot->code,
            'name'                    => $shot->name,
            'description'             => $shot->description,
            'suggested_aspect_ratio'  => $shot->suggested_aspect_ratio,
            'composition_override'    => $shot->composition_override_json,
            'camera_override'         => $shot->camera_override_json,
            'prompt_suffix'           => $shot->prompt_suffix,
        ])->all();

        return new VisualStyleSnapshotDto(
            id:                    $style->id,
            name:                  $style->name,
            schemaVersion:         $style->schema_version,
            dnaJson:               $dna,
            shots:                 $shots,
            referenceMediaId:      $style->reference_media_id,
            negativePrompt:        $style->negative_prompt,
            status:                $style->status,
        );
    }
}
