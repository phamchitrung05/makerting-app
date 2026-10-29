<?php

use Illuminate\Support\Facades\Route;
use Modules\YoutubeIntelligence\Http\Controllers\YoutubeIntelligenceController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('youtubeintelligences', YoutubeIntelligenceController::class)->names('youtubeintelligence');
});
