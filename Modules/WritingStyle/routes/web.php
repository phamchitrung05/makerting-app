<?php

use Illuminate\Support\Facades\Route;
use Modules\WritingStyle\Http\Controllers\WritingStyleController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('writingstyles', WritingStyleController::class)->names('writingstyle');
});
