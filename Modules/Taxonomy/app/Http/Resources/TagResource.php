<?php

namespace Modules\Taxonomy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa Tag trả về API (kèm slug).
 * =====================================================================
 */
class TagResource extends JsonResource
{
    /**
     * INPUT: Request. OUTPUT: array chuẩn cho BaseResponse.data.
     * SIDE EFFECT: không.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'name' => $this->name,
            'slug' => $this->slug_text,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
