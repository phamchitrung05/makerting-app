<?php

namespace Modules\CoreAi\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\Drivers\AnthropicCompatibleDriver;
use Modules\CoreAi\Drivers\AnthropicDriver;
use Modules\CoreAi\Drivers\GeminiDriver;
use Modules\CoreAi\Drivers\OpenAiCompatibleDriver;
use Modules\CoreAi\Drivers\OpenAiDriver;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Registries\AiDriverRegistry;
use Modules\CoreAi\Services\AiProviderClient;
use Modules\CoreAi\Services\CoreAiGateway;
use Modules\CoreAi\Services\ModelResolver;
use Modules\Identity\Registries\PermissionRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module CoreAi (Tầng 1).
 * =====================================================================
 *
 * Trạm điều phối AI duy nhất (Text/Structured/Vision/Image/Audio), quản lý Providers/Models và AiRun.
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
class CoreAiServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'CoreAi';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'coreai';

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
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: singleton AiDriverRegistry + AiProviderClient.
     * =====================================================================
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(AiDriverRegistry::class, fn () => new AiDriverRegistry());
        $this->app->singleton(AiProviderClient::class, fn () => new AiProviderClient());
        $this->app->singleton(ModelResolver::class, fn () => new ModelResolver());

        // Cổng gọi AI duy nhất — mọi module nghiệp vụ inject contract này.
        $this->app->singleton(
            CoreAiGatewayContract::class,
            fn ($app) => new CoreAiGateway(
                $app->make(ModelResolver::class),
                $app->make(AiDriverRegistry::class),
            )
        );
        $this->app->alias(CoreAiGatewayContract::class, CoreAiGateway::class);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Boot module — đăng ký driver + quyền coreai.
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT:
     * - AiDriverRegistry nhận 5 driver nền (openai, anthropic, gemini,
     *   openai-compatible, anthropic-compatible). ElevenLabs chưa đăng ký
     *   (P4-03) — resolve sẽ ném unsupported cho đến khi có driver.
     * - PermissionRegistry thêm 'coreai.view'/'coreai.manage'/'coreai.execute'.
     * =====================================================================
     */
    public function boot(): void
    {
        parent::boot();

        /** @var AiDriverRegistry $drivers */
        $drivers = $this->app->make(AiDriverRegistry::class);
        $drivers->register(AiProvider::DRIVER_OPENAI, OpenAiDriver::class);
        $drivers->register(AiProvider::DRIVER_ANTHROPIC, AnthropicDriver::class);
        $drivers->register(AiProvider::DRIVER_GEMINI, GeminiDriver::class);
        $drivers->register(AiProvider::DRIVER_OPENAI_COMPATIBLE, OpenAiCompatibleDriver::class);
        $drivers->register(AiProvider::DRIVER_ANTHROPIC_COMPATIBLE, AnthropicCompatibleDriver::class);

        /** @var PermissionRegistry $permissions */
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->registerModule('coreai', [
            'label' => 'Quản lý AI',
            'permissions' => [
                'coreai.view' => 'Xem cấu hình AI providers/models/runs',
                'coreai.manage' => 'Thêm/sửa/xóa AI providers/models',
                'coreai.execute' => 'Chạy tác vụ AI (sinh nội dung/ảnh/audio)',
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
