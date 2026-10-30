<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\ChannelGroup;
use Modules\Identity\Http\Requests\ChannelGroupStoreRequest;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo mới hoặc cập nhật một ChannelGroup trong transaction.
 * =====================================================================
 *
 * - Chỉ ghi các field THỰC SỰ có trong payload (partial update an toàn —
 *   sửa tên/mô tả không vô tình xóa defaults_json/is_active).
 * - `code` trống + `name` mới -> tự sinh `Str::slug(name)`; để tránh
 *   UniqueConstraintViolationException 500 khi slug đã tồn tại, ta thêm
 *   hậu tố `-1`,`-2`,... cho tới khi duy nhất (giống SlugService).
 * - `name` bắt buộc khi TẠO (store); khi update chỉ ghi nếu payload có.
 *
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
     * EXCEPTION: InvalidArgumentException khi name thiếu ở luồng tạo.
     */
    public function handle(array $data, ?ChannelGroup $group = null, ?int $actorId = null): ChannelGroup
    {
        return DB::transaction(function () use ($data, $group, $actorId) {
            $isNew = $group === null;
            $group = $group ?? new ChannelGroup();

            // ---- Đảm bảo 'name' có khi cần thiết ----
            $name = $data['name'] ?? null;
            if ($isNew && ($name === null || trim((string) $name) === '')) {
                throw new \InvalidArgumentException('Trường name là bắt buộc khi tạo nhóm kênh.');
            }

            // ---- Gán từng field chỉ khi payload thực sự chứa nó ----
            $attrs = [];

            if (array_key_exists('name', $data)) {
                $attrs['name'] = $name;
            }

            if (array_key_exists('code', $data)) {
                $attrs['code'] = $this->resolveCode($data['code'], $name ?? $group->name, $group->id ?? null);
            } elseif ($isNew) {
                // Tạo mới và không gửi code -> tự sinh từ name.
                $attrs['code'] = $this->resolveCode(null, $name, null);
            }

            if (array_key_exists('color', $data)) {
                $attrs['color'] = $data['color'];
            } elseif ($isNew) {
                $attrs['color'] = 'primary';
            }

            if (array_key_exists('description', $data)) {
                $attrs['description'] = $data['description'];
            } elseif ($isNew) {
                $attrs['description'] = null;
            }

            if (array_key_exists('defaults_json', $data)) {
                $attrs['defaults_json'] = $data['defaults_json'];
            } elseif ($isNew) {
                $attrs['defaults_json'] = null;
            }

            if (array_key_exists('is_active', $data)) {
                $attrs['is_active'] = $data['is_active'];
            } elseif ($isNew) {
                $attrs['is_active'] = true;
            }

            $group->fill($attrs);

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

    /**
     * =====================================================================
     * CHỨC NĂNG: Tính code duy nhất cho ChannelGroup
     * =====================================================================
     * INPUT:
     * - $code      ?string — code do client gửi (null/'' -> tự sinh).
     * - $base      string  — chuỗi nguồn slug (name).
     * - $ignoreId  ?int   — id bản ghi hiện tại (update, bỏ qua khi check unique).
     *
     * OUTPUT: string code duy nhất.
     * SIDE EFFECT: SELECT EXISTS trên channel_groups.code.
     * =====================================================================
     */
    private function resolveCode(?string $code, string $base, ?int $ignoreId): string
    {
        $base = trim((string) $base) !== '' ? (string) $base : 'group';
        $candidate = trim((string) $code) !== '' ? trim((string) $code) : Str::slug($base);

        // Đảm bảo không rỗng (slug có thể bỏ hết ký tự đặc biệt).
        if ($candidate === '') {
            $candidate = 'group';
        }

        $suffix = 0;
        $try = $candidate;
        while ($this->codeExists($try, $ignoreId)) {
            $suffix++;
            $try = $candidate . '-' . $suffix;
        }

        return $try;
    }

    /**
     * CHỨC NĂNG: Kiểm tra code đã tồn tại trên bản ghi khác chưa.
     * INPUT: $code, $ignoreId (bỏ qua khi so trùng — update).
     * OUTPUT: bool.
     */
    private function codeExists(string $code, ?int $ignoreId): bool
    {
        $query = ChannelGroup::query()->where('code', $code);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
