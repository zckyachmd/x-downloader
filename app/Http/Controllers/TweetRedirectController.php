<?php

namespace App\Http\Controllers;

use App\Models\Tweet;
use App\Utils\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TweetRedirectController extends Controller
{
    public function handle(Request $request, string $prefix, string $tweetId)
    {
        $userAgent   = $request->userAgent();
        $isSocialBot = UserAgent::isSocialMediaBot($request->userAgent());
        $tweetUrl    = "https://x.com/{$prefix}/status/{$tweetId}";

        $tweet = Tweet::where('tweet_id', $tweetId)->first()
            ?? Tweet::where('related_tweet_id', $tweetId)->first();

        if (!$tweet) {
            Log::warning('[Redirect] Tweet not found', [
                'tweet_id' => $tweetId,
                'agent'    => $userAgent,
                'ip'       => $request->ip(),
            ]);

            $meta = [
                'tweetId'  => $tweetId,
                'username' => $prefix,
                'videoUrl' => null,
                'preview'  => null,
                'media'    => null,
            ];
        } else {
            $media = collect($tweet->media)->firstWhere('type', 'video');
            $video = $media['video'] ?? [];
            $best  = collect($video)->sortByDesc('bitrate')->first();

            $meta = [
                'tweetId'  => $tweet->tweet_id,
                'username' => $tweet->username,
                'media'    => $media,
                'videoUrl' => $best['url'] ?? null,
                'preview'  => $media['preview_url'] ?? null,
            ];
        }

        $query = ['tweet' => $tweetUrl];

        if (!$isSocialBot) {
            $signature = bin2hex(random_bytes(4));
            session()->put("sig:{$signature}", true);
            $query['src'] = "xdl_{$signature}";
        }

        return response()->view('meta.preview', array_merge($meta, [
            'isSocialBot' => $isSocialBot,
            'redirectUrl' => route('home', $query),
        ]));
    }
}
