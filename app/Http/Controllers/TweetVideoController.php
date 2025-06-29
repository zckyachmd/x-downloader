<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TweetVideoController extends Controller
{
    public function detail(Request $request, string $tweetId)
    {
        return view('video', [
            'tweetId' => $tweetId,
        ]);
    }

    public function download(Request $request, string $tweetId, string $bitrate)
    {
        return view('video', [
            'tweetId' => $tweetId,
            'bitrate' => $bitrate,
        ]);
    }
}
