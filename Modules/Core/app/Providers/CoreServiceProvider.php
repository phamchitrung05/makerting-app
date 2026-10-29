<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Contracts\SafeRemoteFetcherContract;
use Modules\Core\Contracts\SlugServiceContract;
use Modules\Core\Registries\SlugRegistry;
use Modules\Core\Services\AccountRateLimiter;
use Modules\Core\Services\CredentialVault;
use Modules\Core\Services\JitterScheduler;
use Modules\Core\Services\SafeRemoteFetcher;
use Modules\Core\Services\SlugService;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service Provider khởi động của module Core (Tầng 0).
 * =====================================================================
 * Hạt nhân nền tảng: BaseResponse, hệ thống Slugs, CredentialVault, OAuthPool,
 * chống SSRF, Idempotency và AccountRateLimiter.
 *
 * Đăng ký binding các contract (SlugService, SafeRemoteFetcher), singleton
 * registry (SlugRegistry) và các service dùng chung cho mọi module.
 * =====================================================================
 */
class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Core';

    protected string $nameLower = 'core';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Đăng ký binding container của module.
     * INPUT: không có tham số.
     * OUTPUT: void — bind contracts + singletons vào container.
     * SIDE EFFECT: đăng ký vào Laravel service container.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(SlugRegistry::class, fn () => new SlugRegistry());

        $this->app->bind(SlugServiceContract::class, SlugService::class);
        $this->app->bind(SafeRemoteFetcherContract::class, SafeRemoteFetcher::class);

        $this->app->singleton(SlugService::class, fn ($app) => new SlugService($app->make(SlugRegistry::class)));
        $this->app->singleton(CredentialVault::class, fn () => new CredentialVault());
        $this->app->singleton(AccountRateLimiter::class, fn () => new AccountRateLimiter());
        $this->app->singleton(JitterScheduler::class, fn () => new JitterScheduler());
    }

    /**
     * Boot module — đăng ký alias middleware idempotency.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: đăng ký middleware alias + morph map slug nếu cần.
     */
    public function boot(): void
    {
        parent::boot();

        $router = $this->app['router'];
        $router->aliasMiddleware('idempotency', \Modules\Core\Http\Middleware\IdempotencyMiddleware::class);
    }
}
