<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\TweetRedirectController;
use App\Http\Controllers\TweetVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('{prefix}/status/{tweetId}', [TweetRedirectController::class, 'handle'])
    ->middleware('throttle:tweet-redirect')
    ->where([
        'prefix'  => '^(i|[A-Za-z0-9_]{1,15})$',
        'tweetId' => '^\d{18,19}$',
    ])
    ->name('tweet.redirect');

Route::prefix('tweet')->group(function () {
    Route::post('/search', [TweetVideoController::class, 'search'])
        ->middleware(['ajax.only', 'throttle:tweet-search'])
        ->name('tweet.search');

    Route::get('/{videoKey}/thumbnail', [TweetVideoController::class, 'thumbnail'])
        ->name('tweet.thumbnail');

    Route::get('/{videoKey}/preview', [TweetVideoController::class, 'preview'])
        ->middleware('signed')
        ->name('tweet.preview');

    Route::get('/{videoKey}/download', [TweetVideoController::class, 'download'])
        ->middleware('throttle:tweet-download')
        ->name('tweet.download');
});
