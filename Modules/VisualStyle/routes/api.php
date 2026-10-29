<?php

use Illuminate\Support\Facades\Route;
use Modules\VisualStyle\Http\Controllers\VisualStyleController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('visualstyles', VisualStyleController::class)->names('visualstyle');
});
