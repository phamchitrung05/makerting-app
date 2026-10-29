<?php

use Illuminate\Support\Facades\Route;

/*
 * Route API module ContentPlanner — prefix /api/v1/admin/content-planner
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * Endpoint nghiệp vụ sẽ khai báo trong group auth:sanctum bên dưới.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/content-planner')->name('content-planner.')->group(function () {
    // Route::get('options', [...])->name('options');
});