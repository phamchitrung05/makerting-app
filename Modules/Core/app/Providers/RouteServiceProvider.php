<?php

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Đăng ký route web và api cho module Core.
 * =====================================================================
 *
 * Ánh xạ routes/web.php vào middleware 'web' và routes/api.php vào
 * middleware 'api' prefix 'api/v1/admin' theo chuẩn PLAN; gọi khi module boot.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - boot(): khởi tạo sau đăng ký route
 * - map(): điều phối mapApi/mapWeb
 * - mapWebRoutes(): gắn routes/web.php vào middleware 'web'
 * - mapApiRoutes(): gắn routes/api.php vào middleware 'api' + prefix v1/admin
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : file routes/web.php và routes/api.php trong module
 * - OUTPUT: route đăng ký vào router khi boot
 * =====================================================================
 */
class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Core';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Điều phối đăng ký toàn bộ route của module Core
     * =====================================================================
     *
     * INPUT:
     * - không có tham số
     *
     * OUTPUT:
     * - void — gọi mapApiRoutes() và mapWebRoutes()
     *
     * SIDE EFFECT:
     * - đăng ký route vào Laravel router
     * =====================================================================
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký routes/web.php của module Core vào middleware 'web'
     * =====================================================================
     *
     * INPUT:
     * - không có tham số
     *
     * OUTPUT:
     * - void — route web gắn session/CSRF
     *
     * SIDE EFFECT:
     * - Route::middleware('web')->group nạp routes/web.php
     * =====================================================================
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký routes/api.php của module Core vào middleware 'api'
     * =====================================================================
     *
     * INPUT:
     * - không có tham số
     *
     * OUTPUT:
     * - void — api stateless prefix api/v1/admin name api.
     *
     * SIDE EFFECT:
     * - Route::middleware('api')->prefix('api')->name('api.')->group
     * =====================================================================
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
