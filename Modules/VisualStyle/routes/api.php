<?php

use Illuminate\Support\Facades\Route;

/*
 * Route API module VisualStyle — prefix /api/v1/admin/visual-style
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * Endpoint nghiệp vụ sẽ khai báo trong group auth:sanctum bên dưới.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/visual-style')->name('visual-style.')->group(function () {
    // Route::get('options', [...])->name('options');
});