<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{username}/status/{tweetId}', function ($username, $tweetId) {
    return response()->json([
        'action'       => 'redirect',
        'tweet_id'     => $tweetId,
        'username'     => $username,
        'download_url' => route('tweet.download', ['tweetId' => $tweetId]),
    ]);
})->name('tweet.detail');

Route::get('/download/{tweetId}', function ($tweetId) {
    return response()->json([
        'tweet_id'       => $tweetId,
        'download_ready' => true,
        'video_url'      => "https://video.cdn.fake/twitter/{$tweetId}.mp4",
    ]);
})->name('tweet.download');
