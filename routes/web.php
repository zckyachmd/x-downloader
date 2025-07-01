<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\TweetRedirectController;
use App\Http\Controllers\TweetVideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('{prefix}/status/{tweetId}', [TweetRedirectController::class, 'handle'])
    ->where([
        'prefix'  => '^(i|[A-Za-z0-9_]{1,15})$',
        'tweetId' => '^\d{18,19}$',
    ])
    ->name('tweet.redirect');

Route::prefix('tweet')->group(function () {
    Route::post('/search', [TweetVideoController::class, 'search'])->name('tweet.search');

    Route::get('/{tweetId}/thumbnail', [TweetVideoController::class, 'thumbnail'])
        ->name('tweet.thumbnail');

    Route::get('/{tweetId}/preview', [TweetVideoController::class, 'preview'])
        ->middleware('signed')
        ->name('tweet.preview');

    Route::post('/{tweetId}/{bitrate}/download', [TweetVideoController::class, 'download'])
        ->name('tweet.download');
});

Route::get('/debug-ip', function () {
    return [
        'ip'      => request()->ip(),
        'secure'  => request()->secure(),
        'headers' => request()->headers->all(),
    ];
});

Route::get('/debug-https', function () {
    return [
        'secure' => request()->secure(),
        'scheme' => request()->getScheme(),
        'ip'     => request()->ip(),
    ];
});
