<?php

namespace App\Jobs;

use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchTweetsKeywordJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    protected string $username;
    protected string $keyword;
    protected string $mode;

    public function __construct(string $username, string $keyword, string $mode = 'fresh')
    {
        $this->username = $username;
        $this->keyword  = $keyword;
        $this->mode     = $mode;
        $this->tries    = (int) env('QUEUE_TRIES', 1);
    }

    public function handle(): void
    {
        $lockKey = sprintf("fetch-lock:%s:%s:%s", $this->mode, $this->username, md5($this->keyword));
        $lock    = Cache::lock($lockKey, 15 * 60);

        if (!$lock->get()) {
            Log::info("[FetchTweetsJob] 🔒 Locked @{$this->username} ({$this->keyword}) mode={$this->mode}");

            return;
        }

        try {
            $account = UserTwitter::where('username', $this->username)->first();
            if (!$account || !$account->is_active) {
                Log::warning("[FetchTweetsJob] Inactive or missing @$this->username");

                return;
            }

            $bearer = data_get($account->tokens, 'bearer_token');
            $csrf   = data_get($account->tokens, 'csrf_token');
            $cookie = is_array($account->cookies)
                ? collect($account->cookies)->map(fn ($v, $k) => "$k=$v")->join('; ')
                : null;
            $agent = $account->user_agent;

            if (!$bearer || !$csrf || !$cookie || !$agent) {
                Log::warning("[FetchTweetsJob] Missing credentials @$this->username");

                return;
            }

            $slug      = md5($this->keyword);
            $cursorKey = "cursor:{$this->username}:{$slug}";
            $startKey  = "cursor_start:{$this->username}:{$slug}";
            $depthKey  = "cursor_depth:{$this->username}:{$slug}";

            $cursor = null;

            if ($this->mode === 'historical') {
                $cursor = Cache::get($cursorKey) ?? Cache::get($startKey);
                if (Cache::increment($depthKey) > Config::getValue('TWEET_SEARCH_KEYWORDS_MAX_DEPTH', 3)) {
                    Cache::forget($cursorKey);
                    Cache::forget($depthKey);
                    Log::info("[FetchTweetsJob] Max depth reached @$this->username ({$this->keyword})");

                    return;
                }
            } else {
                Cache::forget($depthKey);
            }

            $query = [
                'bearer_token' => $bearer,
                'csrf_token'   => $csrf,
                'cookie'       => $cookie,
                'user_agent'   => $agent,
                'keyword'      => $this->keyword,
                'type'         => 'Latest',
                ...($cursor ? ['cursor' => $cursor] : []),
            ];

            $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/tweet/search';
            $res      = Http::timeout(20)->get($endpoint, $query);
            $status   = $res->status();
            $body     = $res->body();
            $json     = rescue(fn () => $res->json(), []);
            $bodyText = strtolower($body);

            if (
                str_contains($bodyText, 'csrf') ||
                str_contains($bodyText, 'expired') ||
                str_contains($bodyText, 'unauth') ||
                str_contains($bodyText, 'invalid')
            ) {
                $account->update(['is_active' => false]);
                Log::warning("[FetchTweetsJob] ❌ Deactivated @$this->username — session invalid", [
                    'status' => $status,
                    'body'   => $body,
                ]);

                return;
            }

            if ($status === 404 || str_contains($json['detail'] ?? '', '404')) {
                Log::info("[FetchTweetsJob] 🔍 Tweet not found @$this->username ({$this->keyword}) mode={$this->mode}");

                return;
            }

            $tweets = $json['data']['tweets'] ?? [];
            $next   = $json['data']['cursor']['next'] ?? null;

            if (empty($tweets)) {
                if ($this->mode === 'historical') {
                    if ($next) {
                        Cache::put($cursorKey, $next, now()->addHours(3));
                        Log::info("[FetchTweetsJob] Empty result but has next cursor @$this->username ({$this->keyword}), continuing...");
                    } else {
                        Cache::forget($cursorKey);
                        Cache::forget($depthKey);
                        Log::info("[FetchTweetsJob] ❌ Empty result and no cursor @$this->username ({$this->keyword}), cache cleared.");
                    }
                }
                Log::info("[FetchTweetsJob] No tweets @$this->username ({$this->keyword})");

                return;
            }

            $excluded = UserTwitter::getExcludedUsernames();

            foreach ($tweets as $tweet) {
                $author = $tweet['author'] ?? null;
                if (!$author || empty($author['id']) || empty($author['username'])) {
                    continue;
                }

                $usernameLower = strtolower($author['username']);
                if ($excluded->has($usernameLower)) {
                    Log::debug("[FetchTweetsJob] Skipped excluded user: @{$author['username']}");
                    continue;
                }

                $existing = Tweet::select('status')->where('tweet_id', $tweet['tweet_id'])->first();
                if ($existing && !in_array($existing->status, ['queue', null], true)) {
                    Log::debug("[FetchTweetsJob] Skipped tweet {$tweet['tweet_id']} (status: {$existing->status})");
                    continue;
                }

                Tweet::updateOrCreate(
                    ['user_id' => $author['id'], 'tweet_id' => $tweet['tweet_id']],
                    [
                        'username'         => $author['username'],
                        'tweet'            => $tweet['text'],
                        'related_tweet_id' => $tweet['related_tweet_id'] ?? null,
                        'urls'             => $tweet['urls'] ?? [],
                        'media'            => $tweet['media'] ?? [],
                        'status'           => 'queue',
                    ],
                );
            }

            if ($next && $this->mode === 'historical') {
                Cache::put($cursorKey, $next, now()->addHours(3));
            } elseif ($next && $this->mode === 'fresh') {
                Cache::put($startKey, $next, now()->addHours(3));
            }

            Log::info("[FetchTweetsJob] ✅ @$this->username ({$this->keyword}) mode={$this->mode}");
        } catch (\Throwable $e) {
            Log::error("[FetchTweetsJob] ❌ @$this->username ({$this->keyword}): {$e->getMessage()}");
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function tags(): array
    {
        return [
            'fetch-tweets',
            "user:{$this->username}",
            "keyword:" . str($this->keyword)->slug(),
            "mode:{$this->mode}",
        ];
    }
}
