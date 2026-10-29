<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seed dữ liệu nền Identity — role, permission, super-admin.
 * =====================================================================
 * Đồng bộ toàn bộ permission đã đăng ký trong PermissionRegistry vào bảng
 * permissions (guard admin), tạo role super-admin gán mọi quyền và một tài
 * khoản admin mặc định. Chạy: php artisan db:seed --class=... hoặc module:seed.
 * =====================================================================
 */
class IdentityDefaultSeeder extends Seeder
{
    /**
     * Đồng bộ permission + tạo super-admin.
     * INPUT: không có tham số.
     * OUTPUT: void.
     * SIDE EFFECT: INSERT permissions/roles/users + gán quyền.
     */
    public function run(): void
    {
        // Reset cache permission của Spatie trước khi đồng bộ.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $registry = app(\Modules\Identity\Registries\PermissionRegistry::class);
        $names = $registry->allPermissionNames();

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        $admin = Role::findOrCreate('super-admin', 'admin');
        $admin->syncPermissions($names);

        $editor = Role::findOrCreate('editor', 'admin');
        $reviewer = Role::findOrCreate('reviewer', 'admin');

        // Tài khoản admin mặc định (đổi mật khẩu ngay sau khi deploy).
        $user = User::firstOrCreate(
            ['email' => 'admin@makerting.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles(['super-admin']);

        $this->command?->info('IdentityDefaultSeeder: super-admin ' . $user->email . ' sẵn sàng.');
    }
}
