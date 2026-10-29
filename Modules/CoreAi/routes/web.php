<?php

use Illuminate\Support\Facades\Route;
use Modules\CoreAi\Http\Controllers\CoreAiController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('coreais', CoreAiController::class)->names('coreai');
});
