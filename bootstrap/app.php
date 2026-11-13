<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đăng ký alias middleware của spatie/laravel-permission để route
        // dùng được 'permission:xxx', 'role:xxx', 'role_or_permission:xxx'.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Tránh lỗi "Route [login] not defined": app là SPA — không có route
        // Laravel tên 'login' (đăng nhập là route Vue /login). Request API
        // (hoặc expectsJson) trả null -> Authenticate ném AuthenticationException
        // không redirect -> exception handler render 401 JSON "Chưa xác thực"
        // và $api.forceLogout tự điều hướng Vue Router sang /login phía client.
        // Request web thường (vào thẳng /admin/... chưa đăng nhập) vẫn cần
        // redirect về trang đăng nhập SPA.
        $middleware->redirectGuestsTo(fn (Request $request) => (
            $request->is('api/*') || $request->expectsJson()
        ) ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render mọi exception của route /api/* thành envelope BaseResponse chuẩn
        // (success/message/data/errors/meta) thay vì HTML mặc định của Laravel.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // để Laravel xử lý mặc định cho route web
            }

            return \Modules\Core\Http\Responses\BaseResponse::fromException($e);
        });
    })->create();
