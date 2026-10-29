<?php

namespace Modules\Identity\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Identity\Models\ChannelGroup;
use Modules\Identity\Models\User;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tính phạm vi ChannelGroup mà một user được phép truy cập.
 * =====================================================================
 * Nhân sự nội bộ có thể bị giới hạn vào một số nhóm kênh. Super-admin /
 * user có quyền 'channel-groups.manage' thấy tất cả; user thường chỉ thấy
 * nhóm mình được phân công trong channel_group_user.
 * =====================================================================
 */
class ChannelGroupContextService
{
    /**
     * Kiểm tra user có toàn quyền xem mọi ChannelGroup không.
     * INPUT: User.
     * OUTPUT: bool — true nếu super-admin hoặc có quyền quản lý nhóm kênh.
     * SIDE EFFECT: không có.
     */
    public function canSeeAll(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->hasPermissionTo('channel-groups.manage')
            || $user->getAllPermissions()->contains('name', '*');
    }

    /**
     * Lấy Collection các ChannelGroup mà user được phép truy cập.
     * INPUT: User.
     * OUTPUT: Collection<ChannelGroup> — tất cả nếu canSeeAll, ngược lại
     *         chỉ nhóm user là thành viên.
     * SIDE EFFECT: SELECT channel_groups (+ pivot) nếu giới hạn.
     */
    public function forUser(User $user): Collection
    {
        if ($this->canSeeAll($user)) {
            return ChannelGroup::query()->orderBy('name')->get();
        }

        return $user->channelGroups()->where('is_active', true)->get();
    }

    /**
     * Lấy mảng id ChannelGroup user được phép (dùng whereIn lọc tài nguyên).
     * INPUT: User.
     * OUTPUT: int[] danh sách channel_group_id.
     * SIDE EFFECT: SELECT (hoặc null -> toàn quyền, không lọc).
     */
    public function idsForUser(User $user): ?array
    {
        if ($this->canSeeAll($user)) {
            return null; // null = không giới hạn
        }

        return $user->channelGroups()->pluck('channel_groups.id')->all();
    }

    /**
     * Kiểm tra user có quyền vào một ChannelGroup cụ thể không.
     * INPUT: User, channel_group_id.
     * OUTPUT: bool.
     * SIDE EFFECT: có thể SELECT pivot nếu user bị giới hạn.
     */
    public function canAccess(User $user, int $channelGroupId): bool
    {
        if ($this->canSeeAll($user)) {
            return true;
        }

        return $user->channelGroups()
            ->where('channel_groups.id', $channelGroupId)
            ->exists();
    }
}
