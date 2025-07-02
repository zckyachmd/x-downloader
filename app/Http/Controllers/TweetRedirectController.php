<?php

namespace App\Http\Controllers;

use App\Models\Tweet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class TweetRedirectController extends Controller
{
    protected CrawlerDetect $crawler;

    public function __construct(CrawlerDetect $crawler)
    {
        $this->crawler = $crawler;
    }

    public function handle(Request $request, string $prefix, string $tweetId)
    {
        $isBot    = $this->crawler->isCrawler($request->userAgent());
        $tweetUrl = "https://x.com/{$prefix}/status/{$tweetId}";

        $tweet = Tweet::where('tweet_id', $tweetId)->first()
            ?? Tweet::where('related_tweet_id', $tweetId)->first();

        if (!$tweet) {
            Log::warning('[Redirect] Tweet not found', [
                'tweet_id' => $tweetId,
                'agent'    => $request->userAgent(),
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

        if (!$isBot) {
            $signature = bin2hex(random_bytes(4));
            session()->put("sig:{$signature}", true);
            $query['src'] = "xdl_{$signature}";
        }

        return response()->view('meta.preview', array_merge($meta, [
            'isBot'       => $isBot,
            'redirectUrl' => route('home', $query),
        ]));
    }
}
