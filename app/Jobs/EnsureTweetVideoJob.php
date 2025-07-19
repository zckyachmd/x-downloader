<?php

namespace App\Jobs;

use App\Contracts\TweetVideoContract;
use App\Models\Tweet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnsureTweetVideoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $uniqueFor = 30;
    protected $tweetId;

    public function __construct(int $tweetId)
    {
        $this->tweetId = $tweetId;
    }

    public function handle(TweetVideoContract $service): void
    {
        $tweetId  = $this->tweetId;
        $cacheKey = "tweet:$tweetId";

        try {
            $mapped = Tweet::where('tweet_id', $tweetId)
                ->where('status', 'video')
                ->first()?->toArray();

            if (!$mapped) {
                $mapped = $service->fetchFromAPI($tweetId);
            }

            if ($mapped) {
                Cache::put($cacheKey, $mapped, now()->addMinutes(5));
            }
        } catch (\Throwable $e) {
            Log::error('[EnsureTweetVideoJob] Failed to ensure tweet video', [
                'tweet_id' => $tweetId,
                'message'  => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return ['tweet-ensure-video', "tweet:{$this->tweetId}"];
    }
}
