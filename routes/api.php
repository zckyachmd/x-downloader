<?php

use App\Http\Controllers\API\TweetVideoController;
use Illuminate\Support\Facades\Route;

Route::post('/tweet/{tweetId}/video', [TweetVideoController::class, 'videoUrl'])
    ->middleware(['ajax.only', 'throttle:tweet-video']);
