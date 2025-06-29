<?php

namespace App\Http\Controllers;

use App\Models\Tweet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class TweetRedirectController extends Controller
{
    public function redirect(Request $request, string $prefix, string $tweetId)
    {
        $userAgent = $request->userAgent();
        $ip        = $request->ip();
        $isBot     = app(CrawlerDetect::class)->isCrawler($userAgent);

        Log::info('[Redirect] Tweet triggered', [
            'tweet_id' => $tweetId,
            'agent'    => $userAgent,
            'ip'       => $ip,
            'is_bot'   => $isBot,
        ]);

        if (!$isBot) {
            return redirect()->route('home', ['tweetId' => $tweetId]);
        }

        $cached = Cache::remember("tweet:meta:{$tweetId}", now()->addMinutes(30), function () use ($tweetId) {
            return $this->buildMetaFromTweetId($tweetId);
        });

        if (!$cached) {
            $related = Tweet::where('related_tweet_id', $tweetId)->first();
            if ($related) {
                return $this->renderMeta($related);
            }

            return redirect()->route('home', ['tweetId' => $tweetId]);
        }

        return response()->view('meta.preview', $cached);
    }

    protected function buildMetaFromTweetId(string $tweetId): ?array
    {
        $tweet = Tweet::where('tweet_id', $tweetId)->first();

        if (!$tweet || empty($tweet->media)) {
            return null;
        }

        $media = collect($tweet->media)->firstWhere('type', 'video');
        if (!$media || empty($media['video'])) {
            return null;
        }

        $best = collect($media['video'])->sortByDesc('bitrate')->first();

        return [
            'tweetId'  => $tweetId,
            'media'    => $media,
            'username' => $tweet->username,
            'videoUrl' => $best['url'] ?? null,
            'preview'  => $media['preview_url'] ?? null,
        ];
    }

    protected function renderMeta(Tweet $tweet)
    {
        $media = collect($tweet->media)->firstWhere('type', 'video');
        if (!$media || empty($media['video'])) {
            return redirect()->route('home', ['tweetId' => $tweet->tweet_id]);
        }

        $best = collect($media['video'])->sortByDesc('bitrate')->first();

        return response()->view('meta.preview', [
            'tweetId'  => $tweet->tweet_id,
            'media'    => $media,
            'username' => $tweet->username,
            'videoUrl' => $best['url'] ?? null,
            'preview'  => $media['preview_url'] ?? null,
        ]);
    }
}
