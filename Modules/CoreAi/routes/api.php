<?php

use Illuminate\Support\Facades\Route;
use Modules\CoreAi\Http\Controllers\AiModelController;
use Modules\CoreAi\Http\Controllers\AiProviderController;

/*
 * =====================================================================
 * Route API module CoreAi — prefix /api/v1/admin/core-ai
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * =====================================================================
 * - GET    /providers/drivers            (view) — slug driver đã đăng ký
 * - POST   /providers/{ai_provider}/test (manage) — ping kết nối + ghi status
 * - CRUD   /providers                    (view đọc / manage ghi)
 * - GET    /models/options + /models/capabilities (view)
 * - CRUD   /models                       (view đọc / manage ghi)
 *
 * 'coreai.view' cho đọc, 'coreai.manage' cho ghi/test — siêu admin qua
 * Gate::before của Identity. Route tĩnh (drivers/options/capabilities)
 * khai báo TRƯỚC apiResource để không bị route model binding nuốt.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/core-ai')->name('core-ai.')->group(function () {
    // Providers — static routes trước resource.
    Route::get('providers/drivers', [AiProviderController::class, 'drivers'])
        ->middleware('permission:coreai.view')->name('providers.drivers');
    Route::post('providers/{ai_provider}/test', [AiProviderController::class, 'test'])
        ->middleware('permission:coreai.manage')->name('providers.test');
    Route::post('providers/{ai_provider}/sync-models', [AiProviderController::class, 'syncModels'])
        ->middleware('permission:coreai.manage')->name('providers.sync-models');
    Route::apiResource('providers', AiProviderController::class)
        ->parameters(['providers' => 'ai_provider'])
        ->middlewareFor('index', 'permission:coreai.view')
        ->middlewareFor('show', 'permission:coreai.view')
        ->middlewareFor('store', 'permission:coreai.manage')
        ->middlewareFor('update', 'permission:coreai.manage')
        ->middlewareFor('destroy', 'permission:coreai.manage');

    // Models — static routes trước resource.
    Route::get('models/options', [AiModelController::class, 'options'])
        ->middleware('permission:coreai.view')->name('models.options');
    Route::get('models/capabilities', [AiModelController::class, 'capabilities'])
        ->middleware('permission:coreai.view')->name('models.capabilities');
    Route::post('models/{ai_model}/test', [AiModelController::class, 'test'])
        ->middleware('permission:coreai.manage')->name('models.test');
    Route::apiResource('models', AiModelController::class)
        ->parameters(['models' => 'ai_model'])
        ->middlewareFor('index', 'permission:coreai.view')
        ->middlewareFor('show', 'permission:coreai.view')
        ->middlewareFor('store', 'permission:coreai.manage')
        ->middlewareFor('update', 'permission:coreai.manage')
        ->middlewareFor('destroy', 'permission:coreai.manage');
});
