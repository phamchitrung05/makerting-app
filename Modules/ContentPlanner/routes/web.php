<?php

use Illuminate\Support\Facades\Route;
use Modules\ContentPlanner\Http\Controllers\ContentPlannerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contentplanners', ContentPlannerController::class)->names('contentplanner');
});
