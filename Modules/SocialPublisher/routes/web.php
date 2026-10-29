<?php

use Illuminate\Support\Facades\Route;
use Modules\SocialPublisher\Http\Controllers\SocialPublisherController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('socialpublishers', SocialPublisherController::class)->names('socialpublisher');
});
