<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa Role (Spatie) + danh sách permission names.
 * =====================================================================
 */
class RoleResource extends JsonResource
{
    /**
     * INPUT: Request. OUTPUT: array chuẩn cho BaseResponse.data.
     * SIDE EFFECT: không có.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
