<?php

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa User trả về API (kèm roles/permissions khi cần).
 * =====================================================================
 */
class UserResource extends JsonResource
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
            'fullName' => $this->name, // tương thích Vuexy userData.fullName
            'email' => $this->email,
            'avatar' => $this->avatar ?? null,
            'role' => method_exists($this->resource, 'primaryRole') ? $this->primaryRole() : 'member',
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->getPermissionNames()),
            'role_in_group' => $this->whenPivotLoaded('channel_group_user', fn () => $this->pivot->role_in_group),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
