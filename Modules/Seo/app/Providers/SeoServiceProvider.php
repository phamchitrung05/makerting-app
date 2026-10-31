<?php

namespace Modules\Seo\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Identity\Registries\PermissionRegistry;
use Modules\Seo\Settings\SeoSettings;
use Modules\Settings\Registries\SettingsRegistry;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Seo (Tầng 1).
 * =====================================================================
 *
 * Siêu dữ liệu SEO đa hình qua trait HasSeoMeta (title, description, OpenGraph, Canonical).
 *
 * Provider do nwidart/laravel-modules sinh và quản lý: gom các provider con
 * (Event, Route) và là nơi đăng ký binding, registry, event listener của module.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): đăng ký binding container của module
 * - provides(): danh sách service provider cung cấp
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình module từ module.json và protected $name/$nameLower
 * - OUTPUT: đăng ký providers con và binding vào container khi boot
 * =====================================================================
 */
class SeoServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Seo';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'seo';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký SeoSettings vào SettingsRegistry + nhóm quyền 'seo'.
     * =====================================================================
     * - SettingsRegistry nhận SeoSettings (group 'seo') để trang Settings
     *   đọc/ghi qua ModularSettingsService.
     * - PermissionRegistry thêm module 'seo' với quyền seo.view/seo.manage
     *   để ma trận phân quyền hiển thị; siêu admin qua Gate::before.
     * INPUT: không. OUTPUT: void. SIDE EFFECT: ghi vào 2 registry singleton.
     */
    public function boot(): void
    {
        /** @var SettingsRegistry $settings */
        $settings = $this->app->make(SettingsRegistry::class);
        $settings->register(SeoSettings::class);

        /** @var PermissionRegistry $permissions */
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->registerModule('seo', [
            'label' => 'SEO & Meta',
            'permissions' => [
                'seo.view' => 'Xem siêu dữ liệu SEO',
                'seo.manage' => 'Sửa siêu dữ liệu SEO',
            ],
        ]);
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
