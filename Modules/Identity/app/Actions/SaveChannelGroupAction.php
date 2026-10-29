<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\ChannelGroup;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo mới hoặc cập nhật một ChannelGroup trong transaction.
 * =====================================================================
 * Chuẩn hóa code (slug) nếu chưa nhập, lưu name/color/description/defaults_json
 * và đồng bộ danh sách thành viên (pivot role_in_group) nguyên tử.
 * =====================================================================
 */
class SaveChannelGroupAction
{
    /**
     * Lưu ChannelGroup (tạo mới khi $group=null, ngược lại cập nhật).
     * INPUT:
     * - $data   array{name,code?,color?,description?,is_active?,defaults_json?,members?}
     * - $group  ?ChannelGroup (null = tạo mới)
     * - $actorId ?int id user tạo (ghi created_by khi tạo mới)
     * OUTPUT: ChannelGroup đã lưu + load members.
     * SIDE EFFECT: INSERT/UPDATE channel_groups + sync channel_group_user.
     * TRANSACTION: toàn bộ trong DB::transaction.
     */
    public function handle(array $data, ?ChannelGroup $group = null, ?int $actorId = null): ChannelGroup
    {
        return DB::transaction(function () use ($data, $group, $actorId) {
            $isNew = $group === null;
            $group = $group ?? new ChannelGroup();

            $group->fill([
                'name' => $data['name'],
                'code' => $data['code'] ?? Str::slug($data['name']),
                'color' => $data['color'] ?? 'primary',
                'description' => $data['description'] ?? null,
                'defaults_json' => $data['defaults_json'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($isNew && $actorId !== null) {
                $group->created_by = $actorId;
            }

            $group->save();

            // Đồng bộ thành viên nếu có truyền: [{user_id, role_in_group},...]
            if (array_key_exists('members', $data) && is_array($data['members'])) {
                $sync = [];
                foreach ($data['members'] as $m) {
                    $sync[(int) $m['user_id']] = ['role_in_group' => $m['role_in_group'] ?? 'editor'];
                }
                $group->members()->sync($sync);
            }

            return $group->load('members');
        });
    }
}
