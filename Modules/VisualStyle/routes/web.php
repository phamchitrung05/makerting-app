<?php

use Illuminate\Support\Facades\Route;
use Modules\VisualStyle\Http\Controllers\VisualStyleController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('visualstyles', VisualStyleController::class)->names('visualstyle');
});
