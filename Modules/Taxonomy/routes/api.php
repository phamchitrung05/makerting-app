<?php

use Illuminate\Support\Facades\Route;
use Modules\Taxonomy\Http\Controllers\CategoryController;
use Modules\Taxonomy\Http\Controllers\TagController;

/*
 * =====================================================================
 * Route API module Taxonomy — prefix /api/v1/admin/taxonomy
 * (RouteServiceProvider gắn middleware 'api' + prefix 'api' + name 'api.').
 * =====================================================================
 * - GET    /categories + /categories/options + /categories/tree (view)
 * - CRUD   /categories/{category} (manage)
 * - GET    /tags + /tags/options (view)
 * - CRUD   /tags/{tag} (manage)
 *
 * 'taxonomy.view' cho đọc, 'taxonomy.manage' cho ghi — siêu admin qua
 * Gate::before của Identity.
 */

Route::middleware(['auth:sanctum'])->prefix('v1/admin/taxonomy')->name('taxonomy.')->group(function () {
    // Danh mục (cây cha–con + scope).
    Route::get('categories/options', [CategoryController::class, 'options'])
        ->middleware('permission:taxonomy.view')->name('categories.options');
    Route::get('categories/tree', [CategoryController::class, 'tree'])
        ->middleware('permission:taxonomy.view')->name('categories.tree');
    Route::apiResource('categories', CategoryController::class)
        ->middlewareFor('index', 'permission:taxonomy.view')
        ->middlewareFor('show', 'permission:taxonomy.view')
        ->middlewareFor('store', 'permission:taxonomy.manage')
        ->middlewareFor('update', 'permission:taxonomy.manage')
        ->middlewareFor('destroy', 'permission:taxonomy.manage');

    // Thẻ (phẳng + scope).
    Route::get('tags/options', [TagController::class, 'options'])
        ->middleware('permission:taxonomy.view')->name('tags.options');
    Route::apiResource('tags', TagController::class)
        ->middlewareFor('index', 'permission:taxonomy.view')
        ->middlewareFor('show', 'permission:taxonomy.view')
        ->middlewareFor('store', 'permission:taxonomy.manage')
        ->middlewareFor('update', 'permission:taxonomy.manage')
        ->middlewareFor('destroy', 'permission:taxonomy.manage');
});
