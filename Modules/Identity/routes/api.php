<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\AuthController;
use Modules\Identity\Http\Controllers\ChannelGroupController;
use Modules\Identity\Http\Controllers\RolePermissionController;

/*
 * Route API module Identity — prefix /api/v1/admin/identity
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * Login là endpoint public; phần còn lại yêu cầu auth:sanctum guard admin.
 */

Route::prefix('v1/admin/identity')->name('identity.')->group(function () {
    // Public: đăng nhập admin.
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware(['auth:sanctum'])->group(function () {
        // Phiên đăng nhập hiện tại.
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('auth/password', [AuthController::class, 'updatePassword'])->name('auth.password');

        // Ma trận quyền + CRUD vai trò.
        Route::get('permissions/matrix', [RolePermissionController::class, 'matrix'])->name('permissions.matrix');
        Route::apiResource('roles', RolePermissionController::class)->only(['index', 'store', 'update', 'destroy']);

        // Nhóm kênh (ChannelGroup).
        Route::get('channel-groups/options', [ChannelGroupController::class, 'options'])->name('channel-groups.options');
        Route::apiResource('channel-groups', ChannelGroupController::class);
    });
});
