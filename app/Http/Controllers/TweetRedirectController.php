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
        $userAgent = $request->userAgent();
        $isBot     = $this->crawler->isCrawler($userAgent);
        $tweetUrl  = "https://x.com/{$prefix}/status/{$tweetId}";

        if (!$isBot) {
            return redirect()->route('home', ['tweet' => $tweetUrl]);
        }

        $tweet = Tweet::where('tweet_id', $tweetId)->first();

        if (!$tweet || empty($tweet->media)) {
            $related = Tweet::where('related_tweet_id', $tweetId)->first();

            if (!$related) {
                Log::warning('[Redirect] Tweet not found', [
                    'tweet_id' => $tweetId,
                    'agent'    => $userAgent,
                    'ip'       => $request->ip(),
                    'fallback' => 'home',
                ]);

                return redirect()->route('home', ['tweet' => $tweetUrl]);
            }

            return $this->renderMeta($related);
        }

        return response()->view('meta.preview', $this->buildMetaFromTweet($tweet));
    }

    protected function buildMetaFromTweet(Tweet $tweet): array
    {
        $media = collect($tweet->media)->firstWhere('type', 'video');
        if (!$media || empty($media['video'])) {
            return [];
        }

        $best = collect($media['video'])->sortByDesc('bitrate')->first();

        return [
            'tweetId'  => $tweet->tweet_id,
            'media'    => $media,
            'username' => $tweet->username,
            'videoUrl' => $best['url'] ?? null,
            'preview'  => $media['preview_url'] ?? null,
        ];
    }

    protected function renderMeta(Tweet $tweet)
    {
        $meta = $this->buildMetaFromTweet($tweet);

        if (empty($meta['videoUrl'])) {
            return redirect()->route('home', ['tweetId' => $tweet->tweet_id]);
        }

        return response()->view('meta.preview', $meta);
    }
}
