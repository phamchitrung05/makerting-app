<?php

use Illuminate\Support\Facades\Route;
use Modules\CoreAi\Http\Controllers\CoreAiController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('coreais', CoreAiController::class)->names('coreai');
});
