<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa ChannelGroup trả về API.
 * =====================================================================
 */
class ChannelGroupResource extends JsonResource
{
    /**
     * INPUT: Request. OUTPUT: array chuẩn cho BaseResponse.data.
     * SIDE EFFECT: không có (đọc quan hệ members nếu đã eager load).
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'color' => $this->color,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'defaults_json' => $this->defaults_json,
            'created_by' => $this->created_by,
            'members_count' => $this->whenCounted('members'),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
