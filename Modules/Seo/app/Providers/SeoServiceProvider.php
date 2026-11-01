<?php

namespace Modules\Seo\Providers;

use Modules\Identity\Registries\PermissionRegistry;
use Modules\Media\Models\MediaAsset;
use Modules\Seo\Registries\SeoModelRegistry;
use Modules\Seo\Services\SeoMetadataService;
use Modules\Seo\Settings\SeoSettings;
use Modules\Seo\Traits\HasSeoMeta;
use Modules\Settings\Registries\SettingsRegistry;
use Modules\Taxonomy\Models\Category;
use Modules\Taxonomy\Models\Tag;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Seo (Tầng 1).
 * =====================================================================
 *
 * Siêu dữ liệu SEO đa hình qua trait HasSeoMeta (title, description,
 * OpenGraph, Canonical). Registry model whitelist chặn IDOR trên /seo/meta.
 *
 * - register(): singleton SeoModelRegistry + bind SeoMetadataService
 *   (try/catch MissingSettings -> SeoSettings::defaults()).
 * - boot()    : đăng ký whitelist SEO + SeoSettings vào SettingsRegistry +
 *   nhóm quyền 'seo' vào PermissionRegistry.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): binding container.
 * - boot()    : seed registry + permission + whitelist.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình module từ module.json.
 * - OUTPUT: binding + registry đã nạp whitelist + quyền.
 * =====================================================================
 */
class SeoServiceProvider extends ModuleServiceProvider
{
    /** The name of the module. */
    protected string $name = 'Seo';

    /** The lowercase version of the module name. */
    protected string $nameLower = 'seo';

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
     * CHỨC NĂNG: Bind SeoMetadataService an toàn (không crash khi DB
     * settings chưa seed) + singleton SeoModelRegistry.
     * =====================================================================
     * - SeoMetadataService nhận mảng config 'seo' (test dễ truyền).
     * - Khi DB chưa có row group 'seo' (MissingSettings) -> fallback
     *   SeoSettings::defaults() — không crash API SEO.
     * - SeoModelRegistry lưu whitelist model được phép SEO.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: singleton registry + bind service dùng defaults fallback.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(SeoModelRegistry::class, fn () => new SeoModelRegistry());

        $this->app->bind(SeoMetadataService::class, function ($app) {
            try {
                $config = $app->make(SeoSettings::class)->toArray();
            } catch (MissingSettings $e) {
                // DB chưa seed group 'seo' -> dùng defaults để không crash API.
                $config = SeoSettings::defaults();
            }

            return new SeoMetadataService($config);
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký whitelist SEO + SeoSettings + nhóm quyền 'seo'
     * =====================================================================
     * - SeoModelRegistry nhận các model mặc định được phép SEO.
     * - SettingsRegistry nhận SeoSettings (group 'seo').
     * - PermissionRegistry thêm 'seo.view'/'seo.manage'.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: ghi vào 3 registry singleton.
     * =====================================================================
     */
    public function boot(): void
    {
        /** @var SeoModelRegistry $seoModels */
        $seoModels = $this->app->make(SeoModelRegistry::class);

        // Whitelist model nội dung công khai được phép SEO mặc định.
        // Module nghiệp vụ tự register() thêm (Post, VisualStyle...) khi có.
        foreach ([
            Category::class,
            Tag::class,
            \Modules\Identity\Models\ChannelGroup::class,
            MediaAsset::class,
        ] as $modelClass) {
            $seoModels->register($modelClass);
        }

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
}
