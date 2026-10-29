<?php

use Illuminate\Support\Facades\Route;

/*
 * Route API module CoreAi — prefix /api/v1/admin/core-ai
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * Endpoint nghiệp vụ sẽ khai báo trong group auth:sanctum bên dưới.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/core-ai')->name('core-ai.')->group(function () {
    // Route::get('options', [...])->name('options');
});