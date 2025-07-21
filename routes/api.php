<?php

use App\Http\Controllers\API\ExtensionController;
use Illuminate\Support\Facades\Route;

Route::prefix('extension')->middleware(['ajax.only'])->group(function () {
    Route::post('/video', [ExtensionController::class, 'video'])
        ->middleware('throttle:tweet-video');

    Route::post('/track', [ExtensionController::class, 'track'])
        ->middleware('throttle:30,1');
});
