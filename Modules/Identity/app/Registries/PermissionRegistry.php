<?php

namespace Modules\Identity\Registries;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry tập trung khai báo quyền theo từng module.
 * =====================================================================
 * Mỗi module tự đăng ký nhóm quyền của mình trong ServiceProvider::boot()
 * (Registry Pattern — tránh sửa Seeder tổng). Trang Quản lý Phân quyền và
 * seeder đọc registry này để dựng ma trận quyền + đồng bộ bảng permissions.
 *
 * Cấu trúc đăng ký:
 *   registerModule('blog', [
 *       'label' => 'Quản lý Blog',
 *       'permissions' => ['blog.view' => 'Xem bài viết', 'blog.edit' => 'Sửa'],
 *   ]);
 * =====================================================================
 */
class PermissionRegistry
{
    /**
     * module => ['label' => string, 'permissions' => [name => label]].
     *
     * @var array<string, array{label:string,permissions:array<string,string>}>
     */
    protected array $modules = [];

    /**
     * Đăng ký nhóm quyền của một module.
     * INPUT: $module (slug), $definition {label, permissions{name=>label}}.
     * OUTPUT: void — merge vào registry.
     * SIDE EFFECT: thay đổi trạng thái registry (in-memory).
     */
    public function registerModule(string $module, array $definition): void
    {
        $label = $definition['label'] ?? $module;
        $perms = $definition['permissions'] ?? [];

        $existing = $this->modules[$module]['permissions'] ?? [];
        $this->modules[$module] = [
            'label' => $label,
            'permissions' => array_merge($existing, $perms),
        ];
    }

    /**
     * Lấy toàn bộ module đã đăng ký.
     * INPUT: không có tham số.
     * OUTPUT: array module => {label, permissions}.
     * SIDE EFFECT: không có.
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Lấy danh sách phẳng tất cả tên quyền của mọi module.
     * INPUT: không có tham số.
     * OUTPUT: string[] danh sách permission name.
     * SIDE EFFECT: không có.
     */
    public function allPermissionNames(): array
    {
        $names = [];
        foreach ($this->modules as $def) {
            foreach (array_keys($def['permissions']) as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Kiểm tra một permission name đã được module nào đó khai báo chưa.
     * INPUT: $name. OUTPUT: bool.
     * SIDE EFFECT: không có.
     */
    public function has(string $name): bool
    {
        foreach ($this->modules as $def) {
            if (array_key_exists($name, $def['permissions'])) {
                return true;
            }
        }

        return false;
    }
}
