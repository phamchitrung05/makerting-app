<?php

namespace Modules\WritingStyle\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Serialize WritingProfile cho API/UI.
 * =====================================================================
 *
 * Chuyển đổi model WritingProfile thành cấu trúc dữ liệu JSON trả về cho FE:
 * - Trả rules_json (16 khóa DNA) và evidence_json (trích dẫn nguyên văn đối chiếu).
 * - Cấp độ độ dài chủ đạo dominant_tier, thông số metadata và style_instructions.
 * - Danh mục (categories) và thẻ phân loại (tags) khi được eager load.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - toArray(Request $request): Serialize thuộc tính model WritingProfile thành mảng.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile model
 * - OUTPUT: Mảng JSON-serializable của WritingProfile
 * =====================================================================
 */
class WritingProfileResource extends JsonResource
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển đổi resource WritingProfile thành mảng array
     * =====================================================================
     *
     * INPUT:
     * - Request $request: HTTP Request hiện hành
     *
     * OUTPUT:
     * - array: Cấu trúc dữ liệu đầy đủ của hồ sơ văn phong
     *
     * SIDE EFFECT:
     * - Không có (chỉ serialize dữ liệu)
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'slug'                  => $this->slug_text,
            'description'           => $this->description,
            'dominant_tier'         => $this->dominant_tier,
            'rules_json'            => $this->rules_json,
            'evidence_json'         => $this->evidence_json,
            'style_instructions'    => $this->style_instructions,
            'origin'                => $this->origin,
            'status'                => $this->status,
            'is_enabled'            => $this->is_enabled,
            'analysis_metadata_json' => $this->analysis_metadata_json,
            'version'               => $this->version,
            'categories'            => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])),
            'tags'                  => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ])),
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
