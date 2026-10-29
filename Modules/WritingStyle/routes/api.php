<?php

use Illuminate\Support\Facades\Route;

/*
 * Route API module WritingStyle — prefix /api/v1/admin/writing-style
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * Endpoint nghiệp vụ sẽ khai báo trong group auth:sanctum bên dưới.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/writing-style')->name('writing-style.')->group(function () {
    // Route::get('options', [...])->name('options');
});