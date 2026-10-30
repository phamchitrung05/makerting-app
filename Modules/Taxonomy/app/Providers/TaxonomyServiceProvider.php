<?php

namespace Modules\Taxonomy\Providers;

use Modules\Core\Registries\SlugRegistry;
use Modules\Identity\Registries\PermissionRegistry;
use Modules\Taxonomy\Models\Category;
use Modules\Taxonomy\Models\Tag;
use Modules\Taxonomy\Registries\TaxonomyRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Taxonomy (Tầng 1).
 * =====================================================================
 *
 * Phân loại dùng chung: categories, tags, categorizables, taggables và
 * trait HasTaxonomy.
 *
 * - register(): singleton TaxonomyRegistry.
 * - boot(): đăng ký morph alias 'category'/'tag' vào SlugRegistry (dùng cho
 *   slugs.sluggable_type + morphTo) + nhóm quyền 'taxonomy' vào
 *   PermissionRegistry cho ma trận phân quyền.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): binding container.
 * - boot()    : seed registry + permission.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình module từ module.json.
 * - OUTPUT: binding + registry đã nạp alias + quyền.
 * =====================================================================
 */
class TaxonomyServiceProvider extends ModuleServiceProvider
{
    /** The name of the module. */
    protected string $name = 'Taxonomy';

    /** The lowercase version of the module name. */
    protected string $nameLower = 'taxonomy';

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
     * SIDE EFFECT: singleton TaxonomyRegistry.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(TaxonomyRegistry::class, fn () => new TaxonomyRegistry());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Boot module — morph alias + quyền taxonomy
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT:
     * - SlugRegistry nhận 'category' -> Category, 'tag' -> Tag (dùng cho
     *   morphTo và slugs.sluggable_type).
     * - TaxonomyRegistry nhận alias tương ứng (tra cứu ngược).
     * - PermissionRegistry thêm module 'taxonomy' với 2 quyền view/manage.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        /** @var SlugRegistry $slugs */
        $slugs = $this->app->make(SlugRegistry::class);
        $slugs->register('category', Category::class);
        $slugs->register('tag', Tag::class);

        /** @var TaxonomyRegistry $taxonomy */
        $taxonomy = $this->app->make(TaxonomyRegistry::class);
        $taxonomy->register('category', Category::class);
        $taxonomy->register('tag', Tag::class);

        // Module nghiệp vụ (Post, VisualStyle, Campaign...) tự gọi
        // $taxonomy->register('<alias>', Model::class) trong boot() của chúng
        // để xuất hiện trong tra cứu ngược TaxonomyRegistry::idsOf().

        /** @var PermissionRegistry $permissions */
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->registerModule('taxonomy', [
            'label' => 'Phân loại & Thẻ',
            'permissions' => [
                'taxonomy.view' => 'Xem danh mục & thẻ',
                'taxonomy.manage' => 'Quản lý danh mục & thẻ',
            ],
        ]);
    }
}
