<?php

use Illuminate\Support\Facades\Route;
use Modules\VideoStudio\Http\Controllers\VideoStudioController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('videostudios', VideoStudioController::class)->names('videostudio');
});
