<?php

use Illuminate\Support\Facades\Route;
use Modules\TaskCenter\Http\Controllers\TaskCenterController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('taskcenters', TaskCenterController::class)->names('taskcenter');
});
