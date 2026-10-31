<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\SeoController;

/*
 * =====================================================================
 * Route API module Seo — prefix /api/v1/admin/seo
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * =====================================================================
 * - GET /meta          : đọc bản ghi seo_metadata + meta đã fallback (preview).
 * - PUT /meta          : upsert ghi đè các trường seo_* và og_* của model.
 *
 * 'seo.view' cho đọc, 'seo.manage' cho ghi — siêu admin qua Gate::before.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/seo')->name('seo.')->group(function () {
    Route::get('meta', [SeoController::class, 'resolve'])
        ->middleware('permission:seo.view')->name('meta.resolve');
    Route::put('meta', [SeoController::class, 'update'])
        ->middleware('permission:seo.manage')->name('meta.update');
});
