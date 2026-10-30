<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingsController;

/*
 * =====================================================================
 * Route API module Settings — prefix /api/v1/admin/settings
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * =====================================================================
 *
 * - GET    /settings          -> danh sách group + metadata.
 * - GET    /settings/{group}  -> payload một group (quyền settings.{g}.view).
 * - PUT    /settings/{group}  -> cập nhật (quyền settings.{g}.manage + version).
 *
 * 'settings.permission' là alias middleware của ResolveSettingsPermission:
 * đọc {group} trên route -> suy ra quyền '{permission}.view|manage'.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');

    Route::get('{group}', [SettingsController::class, 'show'])
        ->middleware('settings.permission:view')
        ->name('show');

    Route::put('{group}', [SettingsController::class, 'update'])
        ->middleware('settings.permission:manage')
        ->name('update');
});
