<?php

use Illuminate\Support\Facades\Route;
use Modules\Media\Http\Controllers\MediaAiImageController;
use Modules\Media\Http\Controllers\MediaAssetController;

/*
 * =====================================================================
 * Route API module Media — prefix /api/v1/admin/media
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * =====================================================================
 * - GET    /assets + /assets/options + /assets/folders + /assets/{asset} (view)
 * - POST   /assets (upload file) | PUT /assets/{asset} | DELETE /assets/{asset} (manage)
 * - GET    /assets/{asset}/stream (view — stream file vật lý)
 * - GET    /slots (view — metadata slot registry)
 * - POST   /usages | GET /usages | DELETE /usages (manage — gán/gỡ media vào model)
 *
 * 'media.view' cho đọc, 'media.manage' cho ghi — siêu admin qua
 * Gate::before của Identity.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/media')->name('media.')->group(function () {
    // Metadata slot + folder — đặt trước để tránh nuốt bởi {asset}.
    Route::get('slots', [MediaAssetController::class, 'slots'])
        ->middleware('permission:media.view')->name('slots');
    Route::get('assets/options', [MediaAssetController::class, 'options'])
        ->middleware('permission:media.view')->name('assets.options');
    Route::get('assets/folders', [MediaAssetController::class, 'folders'])
        ->middleware('permission:media.view')->name('assets.folders');

    // CRUD assets.
    Route::apiResource('assets', MediaAssetController::class)
        ->middlewareFor('index', 'permission:media.view')
        ->middlewareFor('show', 'permission:media.view')
        ->middlewareFor('store', 'permission:media.manage')
        ->middlewareFor('update', 'permission:media.manage')
        ->middlewareFor('destroy', 'permission:media.manage')
        ->parameters(['assets' => 'asset']);

    // Stream file vật lý (video private / preview).
    Route::get('assets/{asset}/stream', [MediaAssetController::class, 'stream'])
        ->middleware('permission:media.view')->name('assets.stream');

    // Gán/gỡ media vào Domain Model theo slot.
    Route::get('usages', [MediaAssetController::class, 'usages'])
        ->middleware('permission:media.view')->name('usages.list');
    Route::post('usages', [MediaAssetController::class, 'attach'])
        ->middleware('permission:media.manage')->name('usages.attach');
    Route::delete('usages', [MediaAssetController::class, 'detach'])
        ->middleware('permission:media.manage')->name('usages.detach');

    // Sinh ảnh preview AI cho dialog & lưu chọn lọc vào Media Library.
    Route::get('ai-image/models', [MediaAiImageController::class, 'models'])
        ->middleware('permission:media.manage')->name('ai-image.models');
    Route::post('ai-image/generate', [MediaAiImageController::class, 'generate'])
        ->middleware('permission:media.manage')->name('ai-image.generate');
    Route::get('ai-image/jobs/{jobId}', [MediaAiImageController::class, 'status'])
        ->middleware('permission:media.manage')->name('ai-image.status');
    Route::post('ai-image/save', [MediaAiImageController::class, 'save'])
        ->middleware('permission:media.manage')->name('ai-image.save');
});

// Stream ảnh preview AI tạm qua relative signed URL (dùng được cho <img src> và nút tải về).
Route::get('v1/admin/media/ai-image/previews/{previewId}', [MediaAiImageController::class, 'preview'])
    ->middleware('signed:relative')
    ->name('media.ai-image.preview');
