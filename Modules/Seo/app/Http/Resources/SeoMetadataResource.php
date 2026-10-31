<?php

namespace Modules\Seo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa SeoMetadata trả về API Admin.
 * =====================================================================
 *
 * Trả các trường thô đã lưu (chỉ phần ghi đè) — KHÔNG chạy fallback;
 * giá trị đầy đủ sau fallback lấy qua SeoMetadataService->resolve() khi
 * render <head> hoặc từ endpoint resolved riêng.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request. OUTPUT: array chuẩn cho BaseResponse.data.
 * =====================================================================
 */
class SeoMetadataResource extends JsonResource
{
    /**
     * CHỨC NĂNG: Chuyển SeoMetadata -> array trả API.
     * INPUT: Request. OUTPUT: array các cột seo_* / og_* / robots_* / schema.
     * SIDE EFFECT: không.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seoable_type' => $this->seoable_type,
            'seoable_id' => $this->seoable_id,
            'focus_keyword' => $this->focus_keyword,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'canonical_url' => $this->canonical_url,
            'robots_index' => (bool) $this->robots_index,
            'robots_follow' => (bool) $this->robots_follow,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image_id' => $this->og_image_id,
            'schema_org_json' => $this->schema_org_json,
            'meta_robots' => $this->metaRobots(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
