<?php

namespace Modules\WritingStyle\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\Services\WritingProfileSnapshotService;
use Modules\WritingStyle\TaskCenter\WritingProfileAnalyzeAdapter;
use Modules\TaskCenter\Registries\TaskRunAdapterRegistry;
use Modules\Identity\Registries\PermissionRegistry;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module WritingStyle (Tầng 2).
 * =====================================================================
 *
 * Phân tích bài mẫu trích xuất 16 quy tắc văn phong, kiểm chứng trích dẫn và xuất snapshot không đạo văn.
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
     * CHỨC NĂNG: Đăng ký binding container của module.
     * =====================================================================
     * Bind WritingStyleCompilerContract -> WritingProfileSnapshotService
     * (compiler duy nhất cấp DNA văn phong đã strip evidence cho AI).
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(WritingStyleCompilerContract::class, WritingProfileSnapshotService::class);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký adapter TaskCenter để popup hiển thị task.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->make(TaskRunAdapterRegistry::class)->register(new WritingProfileAnalyzeAdapter());

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
