<?php

namespace Modules\WritingStyle\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

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
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
