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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplyToQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    protected int $tweetId;
    protected int $accountId;

    public function __construct(int $tweetId, int $accountId)
    {
        $this->tweetId   = $tweetId;
        $this->accountId = $accountId;
        $this->tries     = (int) env('QUEUE_TRIES', 3);
    }

    public function handle(): void
    {
        $lockKey = "reply-lock:{$this->tweetId}";
        $lock    = Cache::lock($lockKey, 15 * 60);

        if (!$lock->get()) {
            Log::info("[ReplyQueueJob] 🔒 Locked tweet ID {$this->tweetId}, skipping.");

            return;
        }

        try {
            $tweet = Tweet::find($this->tweetId);

            if (!$tweet) {
                Log::warning("[ReplyQueueJob] Tweet ID {$this->tweetId} not found.");

                return;
            }

            if (!in_array($tweet->status, ['process', 'queue'], true)) {
                Log::info("[ReplyQueueJob] Skipped tweet {$tweet->tweet_id}, status = {$tweet->status}");

                return;
            }

            $account = UserTwitter::find($this->accountId);

            if (!$account || !$account->is_active) {
                Log::warning("[ReplyQueueJob] Account not found or inactive (ID: {$this->accountId})");
                $tweet->update(['status' => 'failed-reply']);

                return;
            }

            $bearer = data_get($account->tokens, 'bearer_token');
            $csrf   = data_get($account->tokens, 'csrf_token');
            $cookie = is_array($account->cookies)
                ? collect($account->cookies)->map(fn ($v, $k) => "$k=$v")->join('; ')
                : null;
            $agent = $account->user_agent;

            if (!$bearer || !$csrf || !$cookie || !$agent) {
                Log::warning("[ReplyQueueJob] Missing credentials for @{$account->username}");
                $tweet->update(['status' => 'failed-reply']);

                return;
            }

            $templateList = Config::getValue('TWEET_REPLY_TEMPLATES');
            $rawTemplate  = trim($templateList[array_rand($templateList)] ?? '');

            if (!$rawTemplate) {
                Log::warning("[ReplyQueueJob] Empty reply template.");
                $tweet->update(['status' => 'failed-reply']);

                return;
            }

            $replyText = Interpolator::render("@{{username}} {$rawTemplate}", [
                'username'  => $tweet->username,
                'tweet_url' => "https://x.com/i/status/{$tweet->related_tweet_id}",
                'link'      => route('tweet.redirect', [
                    'prefix'  => 'i',
                    'tweetId' => $tweet->related_tweet_id,
                ]),
            ]);

            $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/tweet/create';

            $res = Http::timeout(15)->post($endpoint, [
                'bearer_token'   => $bearer,
                'csrf_token'     => $csrf,
                'cookie'         => $cookie,
                'user_agent'     => $agent,
                'reply_tweet_id' => $tweet->tweet_id,
                'text'           => $replyText,
            ]);

            $status    = $res->status();
            $body      = $res->body();
            $json      = rescue(fn () => $res->json(), []);
            $bodyLower = strtolower($body);

            $sessionInvalid = str_contains($bodyLower, 'csrf')
                || str_contains($bodyLower, 'expired')
                || str_contains($bodyLower, 'unauth')
                || str_contains($bodyLower, 'invalid');

            $replySuccess = $res->ok()
                && ($json['success'] ?? false) === true
                && isset($json['data'])
                && !$sessionInvalid
                && !str_contains($bodyLower, 'error');

            if ($replySuccess) {
                $account->forceFill(['last_used_at' => now()])->save();
                $tweet->update(['status' => 'replied']);
                Log::info("[ReplyQueueJob] ✅ Replied to {$tweet->tweet_id}");

                return;
            }

            if ($sessionInvalid || $status === 403 || ($json['success'] ?? true) === false) {
                $account->update(['is_active' => false]);
                Log::warning("[ReplyQueueJob] ❌ Deactivated @{$account->username} — invalid session", [
                    'status' => $status,
                    'body'   => $body,
                ]);
            }

            $tweet->update(['status' => 'failed-reply']);
            Log::warning("[ReplyQueueJob] ❌ Reply failed for {$tweet->tweet_id}", [
                'status' => $status,
                'body'   => $body,
            ]);

            throw new \Exception("Reply failed. HTTP {$status}");
        } catch (\Throwable $e) {
            Log::error("[ReplyQueueJob] ❌ Exception: {$e->getMessage()}");
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $tweet = Tweet::find($this->tweetId);

        if ($tweet && $tweet->status === 'queue') {
            $tweet->update(['status' => 'failed-reply']);
            Log::error("[ReplyQueueJob] ❌ Marked as failed-reply after {$this->tries} tries: {$tweet->tweet_id}");
        }
    }

    public function tags(): array
    {
        return ['tweet-queue-reply', "tweet:{$this->tweetId}", "account:{$this->accountId}"];
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
