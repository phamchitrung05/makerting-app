<?php

use Illuminate\Support\Facades\Route;
use Modules\ContentPlanner\Http\Controllers\ContentPlannerController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('contentplanners', ContentPlannerController::class)->names('contentplanner');
});
