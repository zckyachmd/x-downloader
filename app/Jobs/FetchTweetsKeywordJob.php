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

    protected string $username;
    protected string $keyword;
    protected string $mode;

    public function __construct(string $username, string $keyword, string $mode = 'fresh')
    {
        $this->username = $username;
        $this->keyword  = $keyword;
        $this->mode     = $mode;
    }

    public function handle(): void
    {
        $account = UserTwitter::where('username', $this->username)->first();
        if (!$account || !$account->is_active) {
            Log::warning("[FetchTweetsJob] Inactive or missing @$this->username");

            return;
        }

        $bearer = data_get($account->tokens, 'bearer');
        $csrf   = data_get($account->tokens, 'csrf_token');
        $cookie = is_array($account->cookies)
            ? collect($account->cookies)->map(fn ($v, $k) => "$k=$v")->join('; ')
            : null;

        if (!$bearer || !$csrf || !$cookie || !$account->user_agent) {
            Log::warning("[FetchTweetsJob] Missing credentials @$this->username");

            return;
        }

        $slug      = md5($this->keyword);
        $cursorKey = "cursor:{$this->username}:{$slug}";
        $startKey  = "cursor_start:{$this->username}:{$slug}";
        $depthKey  = "cursor_depth:{$this->username}:{$slug}";

        $cursor   = null;
        $maxDepth = Config::getValue('FETCH_TWEET_MAX_DEPTH', 5);

        if ($this->mode === 'historical') {
            $cursor = Cache::get($cursorKey) ?? Cache::get($startKey);
            $depth  = Cache::increment($depthKey);
            if ($depth > $maxDepth) {
                Cache::forget($cursorKey);
                Cache::forget($depthKey);
                Log::info("[FetchTweetsJob] Max depth reached @$this->username ($this->keyword)");

                return;
            }
        } else {
            Cache::forget($depthKey);
        }

        $query = [
            'bearer'     => $bearer,
            'csrf_token' => $csrf,
            'cookie'     => $cookie,
            'user_agent' => $account->user_agent,
            'keyword'    => $this->keyword,
            'type'       => 'Latest',
        ];
        if ($cursor) {
            $query['cursor'] = $cursor;
        }

        try {
            $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/tweet/search';

            $res = Http::withHeaders(['User-Agent' => $account->user_agent])
                ->timeout(20)
                ->get($endpoint, $query);

            if (!$res->ok()) {
                Log::warning("[FetchTweetsJob] HTTP {$res->status()} @$this->username ($this->keyword)", [
                    'body' => $res->body(),
                ]);

                return;
            }

            $data   = $res->json('data');
            $tweets = $data['tweets'] ?? [];

            if (empty($tweets)) {
                Log::info("[FetchTweetsJob] No tweets @$this->username ($this->keyword)");

                return;
            }

            foreach ($tweets as $tweet) {
                $author = $tweet['author'] ?? null;
                if (!$author || empty($author['id'])) {
                    continue;
                }

                Tweet::updateOrCreate(
                    ['user_id' => $author['id'], 'tweet_id' => $tweet['tweet_id']],
                    [
                        'screen_name'      => $author['username'] ?? 'unknown',
                        'tweet'            => $tweet['text'],
                        'related_tweet_id' => $tweet['related_tweet_id'] ?? null,
                        'urls'             => $tweet['urls'] ?? [],
                        'media'            => $tweet['media'] ?? [],
                        'status'           => 'queue',
                    ],
                );
            }

            if ($next = $data['cursor']['next'] ?? null) {
                $ttl = now()->addHours(3);
                $this->mode === 'fresh'
                    ? Cache::put($startKey, $next, $ttl)
                    : Cache::put($cursorKey, $next, $ttl);
            }

            Log::info("[FetchTweetsJob] ✅ @$this->username ($this->keyword) mode={$this->mode}");
        } catch (\Throwable $e) {
            Log::error("[FetchTweetsJob] ❌ @$this->username ($this->keyword): {$e->getMessage()}");
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
