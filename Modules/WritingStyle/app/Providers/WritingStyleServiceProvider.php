<?php

namespace Modules\WritingStyle\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\Services\WritingProfileSnapshotService;
use Modules\WritingStyle\TaskCenter\WritingProfileAnalyzeAdapter;
use Modules\TaskCenter\Registries\TaskRunAdapterRegistry;
use Modules\Identity\Registries\PermissionRegistry;
use Modules\Core\Registries\SlugRegistry;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Đăng ký compiler, CLI và các registry của module WritingStyle.
 * =====================================================================
 *
 * Phân tích bài mẫu trích xuất 16 quy tắc văn phong, kiểm chứng trích dẫn và xuất snapshot không đạo văn.
 *
 * Provider do nwidart/laravel-modules sinh và quản lý: gom các provider con
 * (Event, Route) và là nơi đăng ký binding, registry, event listener của module.
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(): Đăng ký binding compiler và command đánh giá khi chạy console.
 * - boot(): Đăng ký adapter TaskCenter, alias slug và quyền module.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Container, module config và registry dùng chung.
 * - OUTPUT: Binding compiler, command eval và các adapter/permission/slug registry.
 *
 * BỐI CẢNH SỬ DỤNG: Laravel Modules gọi provider trong quá trình khởi động ứng dụng.
 * =====================================================================
 */
class WritingStyleServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'WritingStyle';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'writingstyle';

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
     * CHỨC NĂNG: Đăng ký binding compiler và command đánh giá khi chạy console
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Đăng ký binding container của module.
     * =====================================================================
     * Bind WritingStyleCompilerContract -> WritingProfileSnapshotService
     * (compiler duy nhất cấp DNA văn phong đã strip evidence cho AI).
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Thay đổi container và danh sách command trong ứng dụng.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi container/provider có thể truyền lên. Không mở transaction.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(WritingStyleCompilerContract::class, WritingProfileSnapshotService::class);
        if ($this->app->runningInConsole()) {
            $this->commands([\Modules\WritingStyle\Console\EvaluateWritingPrompt::class]);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký adapter TaskCenter, alias slug và quyền module
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Đăng ký adapter TaskCenter để popup hiển thị task.
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

        $this->app->make(TaskRunAdapterRegistry::class)->register(new WritingProfileAnalyzeAdapter());

        // Đăng ký alias morph 'writing_profile' -> WritingProfile::class cho SlugRegistry
        // (dùng khi SlugService::preview/suggest resolve sluggable_type sang model thật).
        /** @var SlugRegistry $slugs */
        $slugs = $this->app->make(SlugRegistry::class);
        $slugs->register('writing_profile', WritingProfile::class);

        // Đăng ký quyền module writing-style (writing.profile.* + writing.evaluate).
        $this->app->make(PermissionRegistry::class)->registerModule('writingstyle', [
            'label' => 'DNA Văn phong',
            'permissions' => [
                'writing.profile.view'   => 'Xem văn phong',
                'writing.profile.create' => 'Tạo/sửa văn phong',
                'writing.profile.manage' => 'Xóa văn phong',
                'writing.evaluate'       => 'Giải mã & viết thử DNA',
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
