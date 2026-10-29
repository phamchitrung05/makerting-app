<?php

use Illuminate\Support\Facades\Route;
use Modules\TaskCenter\Http\Controllers\TaskCenterController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('taskcenters', TaskCenterController::class)->names('taskcenter');
});
