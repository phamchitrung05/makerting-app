<?php

namespace Modules\Identity\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Identity\Registries\PermissionRegistry;
use Modules\Identity\Services\ChannelGroupContextService;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Identity (Tầng 0).
 * =====================================================================
 * Xác thực nhân sự nội bộ (Sanctum guard admin), phân quyền động
 * (PermissionRegistry) và quản lý Nhóm kênh ChannelGroup.
 * =====================================================================
 */
class IdentityServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Identity';

    protected string $nameLower = 'identity';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Đăng ký binding container của module.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: singleton PermissionRegistry + ChannelGroupContextService.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(PermissionRegistry::class, fn () => new PermissionRegistry());
        $this->app->singleton(ChannelGroupContextService::class, fn () => new ChannelGroupContextService());
    }

    /**
     * Boot module: đăng ký nhóm quyền nền của chính Identity vào registry.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: ghi định nghĩa quyền vào PermissionRegistry.
     */
    public function boot(): void
    {
        parent::boot();

        /** @var PermissionRegistry $registry */
        $registry = $this->app->make(PermissionRegistry::class);

        $registry->registerModule('identity', [
            'label' => 'Nhân sự & Phân quyền',
            'permissions' => [
                'users.view' => 'Xem danh sách nhân sự',
                'users.manage' => 'Tạo/sửa/xóa nhân sự',
                'roles.view' => 'Xem vai trò & quyền',
                'roles.manage' => 'Quản lý vai trò & gán quyền',
                'channel-groups.view' => 'Xem nhóm kênh',
                'channel-groups.manage' => 'Quản lý nhóm kênh',
            ],
        ]);
    }
}
