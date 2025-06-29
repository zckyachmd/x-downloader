<?php

namespace App\Jobs;

use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use App\Utils\Interpolator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplyToQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $tweetId;

    public function __construct(int $tweetId)
    {
        $this->tweetId = $tweetId;
    }

    public function handle(): void
    {
        $tweet = Tweet::find($this->tweetId);
        if (!$tweet || $tweet->status !== 'queue') {
            return;
        }

        $account = UserTwitter::where('is_main', true)->where('is_active', true)->first();
        if (!$account) {
            Log::warning("[ReplyJob] No active account.");

            return;
        }

        $bearer = data_get($account->tokens, 'bearer');
        $csrf   = data_get($account->tokens, 'csrf_token');
        $cookie = is_array($account->cookies)
            ? collect($account->cookies)->map(fn ($v, $k) => "$k=$v")->join('; ')
            : null;
        $agent = $account->user_agent;

        if (!$bearer || !$csrf || !$cookie || !$agent) {
            Log::warning("[ReplyJob] Missing credentials.");

            return;
        }

        $templates   = Config::getValue('TWEET_REPLY_TEMPLATES');
        $rawTemplate = trim($templates[array_rand($templates)]);
        if (!$rawTemplate) {
            Log::warning("[ReplyQueueJob] Empty reply template picked.");

            return;
        }

        $replyText = Interpolator::render("@{{username}} {$rawTemplate}", [
            'username'  => '@' . $tweet->screen_name,
            'tweet_url' => "https://x.com/i/status/{$tweet->related_tweet_id}",
            'link'      => route('tweet.download', ['tweetId' => $tweet->tweet_id]),
        ]);

        $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/tweet/create';

        try {
            $response = Http::withHeaders([
                'User-Agent' => $agent,
            ])->timeout(20)->post($endpoint, [
                'bearer'         => $bearer,
                'csrf_token'     => $csrf,
                'cookie'         => $cookie,
                'user_agent'     => $agent,
                'reply_tweet_id' => $tweet->tweet_id,
                'text'           => $replyText,
            ]);

            if (!$response->ok()) {
                Log::warning("[ReplyQueueJob] Reply failed ({$response->status()}) tweet {$tweet->tweet_id}");
                $tweet->update(['status' => 'queue']);

                return;
            }

            $tweet->update(['status' => 'replied']);
            Log::info("[ReplyQueueJob] ✅ Replied to {$tweet->tweet_id}");
        } catch (\Throwable $e) {
            Log::error("[ReplyQueueJob] ❌ Exception: " . $e->getMessage());
        }
    }

    public function tags(): array
    {
        return ['tweet-queue-reply', "tweet:{$this->tweetId}"];
    }
}
