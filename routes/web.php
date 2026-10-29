<?php

use Illuminate\Support\Facades\Route;

/*
 * Catch-all SPA Vuexy: trả về view application cho mọi route KHÔNG phải /api.
 * Ngoại trừ prefix api/* để các API route (module) được Laravel xử lý JSON.
 */
Route::get('{any?}', function () {
    return view('application');
})->where('any', '^(?!api).*$');
