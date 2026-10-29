<?php

use Illuminate\Support\Facades\Route;
use Modules\VideoStudio\Http\Controllers\VideoStudioController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('videostudios', VideoStudioController::class)->names('videostudio');
});
