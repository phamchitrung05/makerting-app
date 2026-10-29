<?php

use Illuminate\Support\Facades\Route;
use Modules\SocialPublisher\Http\Controllers\SocialPublisherController;

Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::apiResource('socialpublishers', SocialPublisherController::class)->names('socialpublisher');
});
