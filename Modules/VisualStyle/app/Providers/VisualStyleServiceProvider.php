<?php

namespace Modules\VisualStyle\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\VisualStyle\Models\VisualStyle;
use Modules\VisualStyle\Services\VisualStyleSnapshotService;
use Modules\VisualStyle\TaskCenter\VisualStyleAnalyzeAdapter;
use Modules\TaskCenter\Registries\TaskRunAdapterRegistry;
use Modules\Identity\Registries\PermissionRegistry;
use Modules\Core\Registries\SlugRegistry;
use Modules\Taxonomy\Registries\TaxonomyRegistry;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module VisualStyle (Tầng 2).
 * =====================================================================
 *
 * Giải mã ảnh thành DNA nhiếp ảnh 5 nhóm + góc chụp, ghép prompt 7 lớp
 * sinh ảnh đồng bộ thương hiệu và lưu thẳng vào Modules/Media.
 *
 * Provider do nwidart/laravel-modules sinh và quản lý: gom các provider con
 * (Event, Route) và là nơi đăng ký binding, registry, event listener của module.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): đăng ký binding snapshot service của module.
 * - boot(): đăng ký TaskCenter adapter, slug alias, taxonomy alias và quyền.
 * - provides(): danh sách service provider cung cấp.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : cấu hình module từ module.json và protected $name/$nameLower.
 * - OUTPUT: đăng ký providers con + binding + registry vào container.
 * =====================================================================
 */
class VisualStyleServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'VisualStyle';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'visualstyle';

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
     * CHỨC NĂNG: Đăng ký binding snapshot service cho các module Tầng 3
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * Bind VisualStyleSnapshotService để VideoStudio/SocialPublisher/Blog
     * inject cổng đọc DNA đã lọc trường nội bộ (tách interface ở P2-04+).
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Thay đổi container ứng dụng.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi container/provider có thể truyền lên. Không mở transaction.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(VisualStyleSnapshotService::class);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký TaskCenter adapter, slug alias, taxonomy và quyền
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * - TaskRunAdapterRegistry: popup task queue hiển thị "Giải mã DNA ảnh".
     * - SlugRegistry: alias morph 'visual_style' → VisualStyle::class.
     * - TaxonomyRegistry: alias morph 'visual_style' → VisualStyle::class
     *   cho HasTaxonomy scope='visual_style'.
     * - PermissionRegistry: quyền visual.style.* + visual.generate.
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Cập nhật registry trong bộ nhớ; parent provider khởi tạo tài nguyên module.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi registry/provider có thể truyền lên. Không mở transaction.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        // TaskCenter: nhãn + icon + route chi tiết cho popup queue.
        $this->app->make(TaskRunAdapterRegistry::class)
            ->register(new VisualStyleAnalyzeAdapter());

        // SlugRegistry: alias morph 'visual_style' -> VisualStyle::class
        // (SlugService::preview/suggest resolve sluggable_type sang model).
        /** @var SlugRegistry $slugs */
        $slugs = $this->app->make(SlugRegistry::class);
        $slugs->register('visual_style', VisualStyle::class);

        // TaxonomyRegistry: alias morph 'visual_style' -> VisualStyle::class
        // (categorizables/taggables morph_type resolve đúng model).
        /** @var TaxonomyRegistry $taxonomy */
        $taxonomy = $this->app->make(TaxonomyRegistry::class);
        $taxonomy->register('visual_style', VisualStyle::class);

        // PermissionRegistry: quyền module visual-style (xem/tạo-sửa/xóa + sinh).
        $this->app->make(PermissionRegistry::class)->registerModule('visualstyle', [
            'label' => 'DNA Ảnh',
            'permissions' => [
                'visual.style.view'   => 'Xem DNA ảnh',
                'visual.style.create' => 'Tạo/sửa DNA ảnh',
                'visual.style.manage' => 'Xóa DNA ảnh',
                'visual.generate'     => 'Sinh ảnh từ DNA',
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
