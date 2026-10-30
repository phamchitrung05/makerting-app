<?php

namespace Modules\Taxonomy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa Category trả về API (kèm slug + cây).
 * =====================================================================
 */
class CategoryResource extends JsonResource
{
    /**
     * INPUT: Request. OUTPUT: array chuẩn cho BaseResponse.data.
     * SIDE EFFECT: đọc quan hệ parent/children nếu đã eager load.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'scope' => $this->scope,
            'name' => $this->name,
            'slug' => $this->slug_text,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'show_on_menu' => (bool) $this->show_on_menu,
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('descendants')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
