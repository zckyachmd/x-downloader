<?php

namespace App\Http\Controllers;

use App\Models\Tweet;
use App\Utils\UserAgent;
use Illuminate\Http\Request;

class TweetRedirectController extends Controller
{
    private UserAgent $userAgent;

    public function __construct(UserAgent $userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function handle(Request $request, string $prefix, string $tweetId)
    {
        $isSocialBot = $this->userAgent->isSocialMediaBot($request->userAgent(), $request);
        $tweetUrl    = "https://x.com/{$prefix}/status/{$tweetId}";

        $tweet = Tweet::where('tweet_id', $tweetId)->first();
        $media = collect($tweet?->media ?? [])->firstWhere('type', 'video');
        $video = collect($media['video'] ?? [])->sortByDesc('bitrate')->first();

        $meta = [
            'tweetId'   => $tweet->tweet_id ?? $tweetId,
            'username'  => $tweet->username ?? $prefix,
            'media'     => $media ?? null,
            'videoUrl'  => $video['url'] ?? null,
            'thumbnail' => $media['preview_url'] ?? route('tweet.thumbnail', $tweetId),
        ];

        $query = ['tweet' => $tweetUrl];

        if (!$isSocialBot) {
            $signature = bin2hex(random_bytes(4));
            session()->put("sig:{$signature}", true);
            $query['src'] = "xdl_{$signature}";
        }

        return response()->view('meta.preview', [
            ...$meta,
            'isSocialBot' => $isSocialBot,
            'redirectUrl' => route('home', $query),
        ]);
    }
}
