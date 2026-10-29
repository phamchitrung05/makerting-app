<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\SlugPreviewController;

/*
 * Route API module Core — prefix /api/v1/admin (do RouteServiceProvider gắn
 * middleware 'api' + prefix 'api' + name 'api.', file này thêm 'v1/admin').
 */
Route::middleware(['auth:sanctum'])->prefix('v1/admin/core')->name('core.')->group(function () {
    Route::get('slug-preview', [SlugPreviewController::class, 'show'])->name('slug-preview');
});
