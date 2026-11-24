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
    // Metadata slot + folder + thống kê + thao tác hàng loạt — đặt trước để tránh nuốt bởi {asset}.
    Route::get('slots', [MediaAssetController::class, 'slots'])
        ->middleware('permission:media.view')->name('slots');
    Route::get('assets/options', [MediaAssetController::class, 'options'])
        ->middleware('permission:media.view')->name('assets.options');
    Route::get('assets/folders', [MediaAssetController::class, 'folders'])
        ->middleware('permission:media.view')->name('assets.folders');
    Route::get('assets/stats', [MediaAssetController::class, 'stats'])
        ->middleware('permission:media.view')->name('assets.stats');
    Route::post('assets/bulk-folder', [MediaAssetController::class, 'bulkMoveFolder'])
        ->middleware('permission:media.manage')->name('assets.bulk-folder');

    // CRUD assets (show hỗ trợ ?trashed=1 để xem chi tiết trong thùng rác).
    Route::apiResource('assets', MediaAssetController::class)
        ->except(['show'])
        ->middlewareFor('index', 'permission:media.view')
        ->middlewareFor('store', 'permission:media.manage')
        ->middlewareFor('update', 'permission:media.manage')
        ->middlewareFor('destroy', 'permission:media.manage')
        ->parameters(['assets' => 'asset']);
    Route::get('assets/{asset}', [MediaAssetController::class, 'show'])
        ->withTrashed()
        ->middleware('permission:media.view')->name('assets.show');
    Route::post('assets/{asset}/restore', [MediaAssetController::class, 'restore'])
        ->withTrashed()
        ->middleware('permission:media.manage')->name('assets.restore');
    Route::delete('assets/{asset}/force', [MediaAssetController::class, 'forceDestroy'])
        ->withTrashed()
        ->middleware('permission:media.manage')->name('assets.force-destroy');

    // Stream file vật lý (video private / preview; hỗ trợ ?trashed=1 trong thùng rác) & cấp signed share URL tạm thời.
    Route::get('assets/{asset}/stream', [MediaAssetController::class, 'stream'])
        ->withTrashed()
        ->middleware('permission:media.view')->name('assets.stream');
    Route::get('assets/{asset}/share-link', [MediaAssetController::class, 'shareLink'])
        ->middleware('permission:media.view')->name('assets.share-link');

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
    Route::post('ai-image/suggest-prompt', [MediaAiImageController::class, 'suggestPrompt'])
        ->middleware('permission:media.manage')->name('ai-image.suggest-prompt');
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

// Stream asset chia sẻ tạm qua relative signed URL có TTL (dùng cho asset private, không cần Bearer token).
Route::get('v1/admin/media/assets/{asset}/share-stream', [MediaAssetController::class, 'shareStream'])
    ->middleware('signed:relative')
    ->name('media.assets.share-stream');
