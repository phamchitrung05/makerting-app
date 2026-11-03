<?php

use Illuminate\Support\Facades\Route;
use Modules\WritingStyle\Http\Controllers\WritingAnalyzeController;
use Modules\WritingStyle\Http\Controllers\WritingProfileController;
use Modules\WritingStyle\Http\Controllers\WritingSandboxController;

/*
 * =====================================================================
 * Route API module WritingStyle — prefix /api/v1/admin/writing-style
 * =====================================================================
 * - GET/POST/PUT/DELETE /writing-profiles   CRUD + /options
 * - POST /writing-profiles/analyze          giải mã DNA (202 + dispatch job)
 * - POST /writing-profiles/{id}/sandbox     DNA Sandbox viết thử
 *
 * 'writing.profile.view' đọc; 'writing.profile.create' tạo/sửa;
 * 'writing.evaluate' analyze + sandbox; 'writing.profile.manage' xóa.
 * Route tĩnh (options/analyze) khai báo TRƯỚC apiResource để không bị
 * route model binding nuốt.
 * =====================================================================
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/writing-style')->name('writing-style.')->group(function () {
    // Static routes trước resource (tránh {writing_profile} nuốt).
    Route::get('writing-profiles/options', [WritingProfileController::class, 'options'])
        ->middleware('permission:writing.profile.view')->name('writing-profiles.options');
    Route::post('writing-profiles/analyze', [WritingAnalyzeController::class, 'store'])
        ->middleware('permission:writing.evaluate')->name('writing-profiles.analyze');

    Route::apiResource('writing-profiles', WritingProfileController::class)
        ->parameters(['writing-profiles' => 'writing_profile'])
        ->middlewareFor('index', 'permission:writing.profile.view')
        ->middlewareFor('show', 'permission:writing.profile.view')
        ->middlewareFor('store', 'permission:writing.profile.create')
        ->middlewareFor('update', 'permission:writing.profile.create')
        ->middlewareFor('destroy', 'permission:writing.profile.manage');

    Route::post('writing-profiles/{writing_profile}/sandbox', [WritingSandboxController::class, 'store'])
        ->middleware('permission:writing.evaluate')->name('writing-profiles.sandbox');
});
