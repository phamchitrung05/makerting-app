<?php

namespace Modules\Identity\Models;

use App\Models\User as BaseUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model User nội bộ của module Identity.
 * =====================================================================
 * Mở rộng App\Models\User, bổ sung Sanctum HasApiTokens + Spatie HasRoles
 * và quan hệ channelGroups(). Đây là model xác thực cho guard `admin`
 * (nhân sự nội bộ — hệ thống KHÔNG có Customer).
 *
 * Lưu ý: để Spatie permission hoạt động đúng, config auth provider users
 * trỏ về model này qua AUTH_MODEL=Modules\Identity\Models\User.
 * =====================================================================
 */
class User extends BaseUser
{
    use HasApiTokens, HasRoles;

    protected $guard_name = 'admin';

    /**
     * Các nhóm kênh mà user này được phân công phụ trách.
     * INPUT: không có tham số.
     * OUTPUT: BelongsToMany qua channel_group_user + pivot role_in_group.
     * SIDE EFFECT: không có.
     */
    public function channelGroups(): BelongsToMany
    {
        return $this->belongsToMany(ChannelGroup::class, 'channel_group_user')
            ->withPivot('role_in_group')
            ->withTimestamps();
    }

    /**
     * Trả mảng CASL abilityRules để frontend phân quyền route/component.
     * - Super-admin (role 'super-admin' hoặc permission '*') -> [{action:manage,subject:all}]
     * - Còn lại map từng permission 'module.action' -> {action, subject}.
     * INPUT: không có tham số.
     * OUTPUT: array<int,array{action:string,subject:string}>.
     * SIDE EFFECT: đọc quan hệ permissions/roles (đã eager load nếu có).
     */
    public function abilityRules(): array
    {
        if ($this->hasRole('super-admin') || $this->isSuperAdmin()) {
            return [['action' => 'manage', 'subject' => 'all']];
        }

        return $this->getAllPermissions()
            ->map(function ($perm) {
                $name = (string) $perm->name;
                if (str_contains($name, '.')) {
                    [$subject, $action] = explode('.', $name, 2);
                    return ['action' => $action, 'subject' => $subject];
                }

                return ['action' => 'read', 'subject' => $name];
            })
            ->values()
            ->all();
    }

    /**
     * Kiểm tra user có quyền wildcard '*' không (an toàn khi quyền chưa tồn tại).
     * INPUT: không có tham số. OUTPUT: bool.
     * SIDE EFFECT: không có.
     */
    public function isSuperAdmin(): bool
    {
        return $this->getAllPermissions()->contains('name', '*');
    }

    /**
     * Trả role chính (tên role đầu tiên) để hiển thị trên UI.
     * INPUT: không có tham số. OUTPUT: string role hoặc 'member'.
     * SIDE EFFECT: không có.
     */
    public function primaryRole(): string
    {
        return (string) ($this->getRoleNames()->first() ?? 'member');
    }
}
