<?php

use Illuminate\Support\Facades\Route;
use Modules\YoutubeIntelligence\Http\Controllers\YoutubeIntelligenceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('youtubeintelligences', YoutubeIntelligenceController::class)->names('youtubeintelligence');
});
