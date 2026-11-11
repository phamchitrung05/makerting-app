<?php

namespace Modules\WritingStyle\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Serialize hồ sơ văn phong và metadata V3 cho API/UI.
 * =====================================================================
 *
 * Chuyển đổi model WritingProfile thành cấu trúc dữ liệu JSON trả về cho FE:
 * - Trả rules_json (16 khóa DNA) và evidence_json (trích dẫn nguyên văn đối chiếu).
 * - Cấp độ độ dài chủ đạo dominant_tier, thông số metadata, style_instructions,
 *   voice_texture_instructions và voice_texture_sliders_json.
 * - Danh mục (categories) và thẻ phân loại (tags) khi được eager load.
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - toArray(): Serialize hồ sơ, trường V3 và các quan hệ đã được nạp.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile và Request.
 * - OUTPUT: array có DNA, Ô 1–3, slider, style_analysis_json, slider_preferences_json và quan hệ.
 *
 * BỐI CẢNH SỬ DỤNG: Dùng cho response CRUD; relations chỉ xuất khi đã eager load.
 * =====================================================================
 */
class WritingProfileResource extends JsonResource
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize hồ sơ, trường V3 và các quan hệ đã được nạp
     * =====================================================================
     *
     * INPUT:
     * - Request $request
     *
     * OUTPUT:
     * - array profile với giá trị mặc định tương thích bản cũ
     *
     * SIDE EFFECT:
     * - Đọc model và relations khi đã load; không ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction; lỗi serialize có thể truyền lên.
     * =====================================================================
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'name'                       => $this->name,
            'slug'                       => $this->slug_text,
            'description'                => $this->description,
            'dominant_tier'              => $this->dominant_tier,
            'temperature'                => $this->temperature !== null ? (float) $this->temperature : 0.70,
            'rules_json'                 => $this->rules_json,
            'evidence_json'              => $this->evidence_json,
            'style_instructions'         => $this->style_instructions,
            'voice_texture_instructions' => $this->voice_texture_instructions ?? '',
            'voice_texture_sliders_json' => $this->voice_texture_sliders_json ?? [
                'voice_timbre'     => 70,
                'vernacular_flow'  => 65,
                'emotional_nuance' => 75,
                'rhythm_variation' => 70,
                'emphasis_glide'   => 80,
            ],
            'tone_sliders_json'          => $this->tone_sliders_json ?? [
                'humor'         => 50,
                'empathy'       => 50,
                'assertiveness' => 50,
                'formality'     => 50,
                'inspiration'   => 50,
                'candor'        => 50,
                'depth'         => 50,
                'urgency'       => 50,
            ],
            'tone_instructions'          => $this->tone_instructions ?? '',
            'sandbox_samples_json'       => $this->sandbox_samples_json ?? [],
            'origin'                     => $this->origin,
            'status'                     => $this->status,
            'is_enabled'                 => $this->is_enabled,
            'analysis_metadata_json'     => $this->analysis_metadata_json,
            'style_analysis_json'        => $this->style_analysis_json,
            'slider_preferences_json'    => $this->slider_preferences_json,
            'version'                    => $this->version,
            'categories'                 => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])),
            'tags'                       => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])),
            'analyses'                   => $this->whenLoaded('analyses', fn () => $this->analyses->map(fn ($a) => [
                'id'         => $a->id,
                'status'     => $a->status,
                'ai_run_id'  => $a->ai_run_id,
                'created_at' => $a->created_at?->toISOString(),
            ])),
            'created_at'                 => $this->created_at?->toISOString(),
            'updated_at'                 => $this->updated_at?->toISOString(),
        ];
    }
}
