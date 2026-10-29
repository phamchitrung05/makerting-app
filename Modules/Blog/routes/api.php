<?php

use Illuminate\Support\Facades\Route;

/*
 * Route API module Blog — prefix /api/v1/admin/blog
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * Endpoint nghiệp vụ sẽ khai báo trong group auth:sanctum bên dưới.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/blog')->name('blog.')->group(function () {
    // Route::get('options', [...])->name('options');
});