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

Route::get('/search/{tweetId}', [TweetVideoController::class, 'detail'])->name('tweet.detail');

Route::get('/download/{tweetId}/{bitrate}', [TweetVideoController::class, 'download'])->name('tweet.download');
