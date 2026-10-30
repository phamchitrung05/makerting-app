<?php

namespace Modules\Settings\Providers;

use Modules\Identity\Registries\PermissionRegistry;
use Modules\Settings\Registries\SettingsRegistry;
use Modules\Settings\Services\ModularSettingsService;
use Modules\Settings\Settings\GeneralSettings;
use Modules\Settings\Settings\MailSettings;
use Modules\Settings\Settings\SecuritySettings;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Settings (Tầng 0).
 * =====================================================================
 *
 * - register(): singleton SettingsRegistry + ModularSettingsService.
 * - boot(): đăng ký 3 class settings nền vào SettingsRegistry + mở cổng
 *   cho module khác register() thêm; đồng thời đăng ký nhóm quyền
 *   'settings' vào PermissionRegistry để RolePermissionController dựng
 *   ma trận phân quyền.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): binding container.
 * - boot()    : seed registry + permission.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình module từ module.json.
 * - OUTPUT: binding + registry đã nạp 3 settings nền.
 * =====================================================================
 */
class SettingsServiceProvider extends ModuleServiceProvider
{
    /** The name of the module. */
    protected string $name = 'Settings';

    /** The lowercase version of the module name. */
    protected string $nameLower = 'settings';

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
     * CHỨC NĂNG: Đăng ký binding container của module
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: singleton SettingsRegistry + ModularSettingsService.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(SettingsRegistry::class, fn () => new SettingsRegistry());
        $this->app->singleton(ModularSettingsService::class, fn ($app) => new ModularSettingsService(
            $app->make(SettingsRegistry::class),
        ));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Boot module — nạp 3 settings nền vào registry + đăng ký
     * nhóm quyền 'settings' vào PermissionRegistry
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT:
     * - SettingsRegistry nhận GeneralSettings/SecuritySettings/MailSettings;
     * - PermissionRegistry thêm module 'settings' với các quyền
     *   settings.{site,security,mail}.{view,manage} + settings.view/manage.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        /** @var SettingsRegistry $registry */
        $registry = $this->app->make(SettingsRegistry::class);

        // Ba nhóm cài đặt nền của chính module Settings.
        $registry->register(GeneralSettings::class);
        $registry->register(SecuritySettings::class);
        $registry->register(MailSettings::class);

        // Module khác (CoreAi, Seo, Media...) tự gọi $registry->register()
        // trong ServiceProvider::boot() của chúng — registry giữ singleton.

        // Đăng ký nhóm quyền để UI ma trận phân quyền hiển thị.
        /** @var PermissionRegistry $permissions */
        $permissions = $this->app->make(PermissionRegistry::class);

        $permissions->registerModule('settings', [
            'label' => 'Cài đặt hệ thống',
            'permissions' => [
                'settings.site.view' => 'Xem cài đặt chung',
                'settings.site.manage' => 'Sửa cài đặt chung',
                'settings.security.view' => 'Xem cài đặt bảo mật',
                'settings.security.manage' => 'Sửa cài đặt bảo mật',
                'settings.mail.view' => 'Xem cài đặt email',
                'settings.mail.manage' => 'Sửa cài đặt email',
            ],
        ]);

        // Alias middleware phân quyền động theo {group} trên route.
        $this->app['router']->aliasMiddleware(
            'settings.permission',
            \Modules\Settings\Http\Middleware\ResolveSettingsPermission::class,
        );
    }
}
