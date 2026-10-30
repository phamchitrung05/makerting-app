<?php

namespace Modules\TaskCenter\Providers;

use Modules\Identity\Registries\PermissionRegistry;
use Modules\TaskCenter\Contracts\TaskTrackerContract;
use Modules\TaskCenter\Registries\TaskRunAdapterRegistry;
use Modules\TaskCenter\Services\TaskTrackerService;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module TaskCenter.
 * =====================================================================
 *
 * - register(): singleton TaskRunAdapterRegistry + bind TaskTrackerContract.
 * - boot(): đăng ký nhóm quyền 'task-center' vào PermissionRegistry.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): binding container.
 * - boot()    : đăng ký permission.
 * INPUT/OUTPUT: binding + permission registry.
 * =====================================================================
 */
class TaskCenterServiceProvider extends ModuleServiceProvider
{
    /** The name of the module. */
    protected string $name = 'TaskCenter';

    /** The lowercase version of the module name. */
    protected string $nameLower = 'taskcenter';

    /** Provider classes to register. */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký binding container của module
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: singleton TaskRunAdapterRegistry + bind
     * TaskTrackerContract -> TaskTrackerService.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(TaskRunAdapterRegistry::class, fn () => new TaskRunAdapterRegistry());
        $this->app->bind(TaskTrackerContract::class, TaskTrackerService::class);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Boot module — đăng ký nhóm quyền 'task-center'
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: PermissionRegistry thêm quyền task-center.*.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        /** @var PermissionRegistry $permissions */
        $permissions = $this->app->make(PermissionRegistry::class);

        $permissions->registerModule('task-center', [
            'label' => 'Trung tâm tác vụ',
            'permissions' => [
                'task-center.view' => 'Xem tiến trình & cảnh báo',
                'task-center.manage' => 'Quản lý tiến trình & cảnh báo',
            ],
        ]);
    }
}
