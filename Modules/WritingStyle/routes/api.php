<?php

use Illuminate\Support\Facades\Route;
use Modules\WritingStyle\Http\Controllers\WritingStyleController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('writingstyles', WritingStyleController::class)->names('writingstyle');
});
