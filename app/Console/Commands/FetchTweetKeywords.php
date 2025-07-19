<?php

namespace App\Console\Commands;

use App\Jobs\FetchTweetsKeywordJob;
use App\Models\UserTwitter;
use App\Traits\HasConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchTweetKeywords extends Command
{
    use HasConfig;

    protected $signature = 'twitter:fetch-tweets
        {--mode= : Mode to fetch: all, fresh, or historical}
        {--limit= : Limit number of accounts}
        {--max-keyword= : Limit keywords from config}
        {--rest= : Max accounts to rest per hour}
        {--force : (bool) Force run even if AUTO_SEARCH_TWEET is false}';

    protected $description = 'Dispatch fetch jobs per keyword per account with staggered delays';

    public function handle()
    {
        if (!$this->option('force') && !$this->getConfig('AUTO_SEARCH_TWEET', true)) {
            $this->warn("⚠️ AUTO_SEARCH_TWEET is disabled. Use --force to override.");

            return self::SUCCESS;
        }

        $modeOption   = $this->getConfig('TWEET_FETCH_MODE', 'all', 'mode');
        $accountLimit = (int) $this->getConfig('TWEET_FETCH_ACCOUNT_LIMIT', 3, 'limit');
        $keywordLimit = (int) $this->getConfig('TWEET_FETCH_KEYWORD_LIMIT', 3, 'max-keyword');
        $restLimit    = (int) $this->getConfig('TWEET_FETCH_REST_LIMIT', 1, 'rest');

        if (!in_array($modeOption, ['all', 'fresh', 'historical'], true)) {
            $this->error("❌ Invalid mode: $modeOption. Use fresh, historical, or all.");

            return self::FAILURE;
        }

        $keywords = collect(explode(';', $this->getConfig('TWEET_SEARCH_KEYWORDS', '')))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->shuffle()
            ->take($keywordLimit)
            ->values();

        $rawAccounts = UserTwitter::query()
            ->where('is_active', true)
            ->where('is_main', false)
            ->orderByRaw('CASE WHEN last_used_at IS NULL THEN 0 ELSE 1 END, last_used_at ASC')
            ->get();

        $accounts = $rawAccounts
            ->groupBy('username')
            ->map(fn ($group) => $group->random())
            ->values()
            ->take($accountLimit);

        if ($keywords->isEmpty() || $accounts->isEmpty()) {
            $this->warn("❌ No keywords or accounts available.");

            return self::FAILURE;
        }

        $this->dispatchJobs($accounts, $keywords, $modeOption, $restLimit);

        return self::SUCCESS;
    }

    protected function dispatchJobs($accounts, $keywords, string $modeOption, int $restLimit): void
    {
        $now              = now();
        $jobIndex         = 0;
        $maxDepth         = (int) $this->getConfig('TWEET_SEARCH_KEYWORDS_MAX_DEPTH', 2);
        $keywordCooldown  = fn () => rand(150, 180);
        $freshToHistDelay = fn () => rand(60, 90);
        $accountStart     = fn ($i) => $i * rand(10, 25);

        $accountList     = $accounts->values();
        $restedUsernames = [];

        foreach ($accountList as $account) {
            $username    = $account->username;
            $restKey     = "fetch-rest-limit:{$username}:" . $now->format('YmdH');
            $currentRest = (int) Cache::get($restKey, 0);

            if ($currentRest >= $restLimit) {
                $restedUsernames[] = $username;
            }
        }

        $this->line('🛌 Accounts resting this hour: ' . implode(', ', $restedUsernames));

        $keywordMap = [];
        foreach ($keywords as $i => $keyword) {
            $index                            = $i % $accountList->count();
            $account                          = $accountList[$index];
            $keywordMap[$account->username][] = $keyword;
        }

        foreach ($accountList as $i => $account) {
            $username = $account->username;

            if (in_array($username, $restedUsernames)) {
                $this->warn("😴 @$username is resting this hour. Skipping.");
                $restKey = "fetch-rest-limit:$username:" . $now->format('YmdH');
                Cache::increment($restKey);
                Cache::put($restKey, Cache::get($restKey), now()->addMinutes(61));
                continue;
            }

            if (empty($keywordMap[$username])) {
                $this->warn("⚠️ @$username has no keywords. Skipping.");
                continue;
            }

            if ($this->isAccountLocked($username)) {
                $this->warn("🔁 @$username is still in progress. Skipping.");
                continue;
            }

            $this->lockAccount($username);
            $current = $now->copy()->addSeconds($accountStart($i));

            foreach ($keywordMap[$username] as $keyword) {
                $modes = $modeOption === 'all' ? ['fresh', 'historical'] : [$modeOption];

                foreach ($modes as $mode) {
                    $repeat = $mode === 'fresh' ? 1 : max(0, $maxDepth - 1);

                    for ($d = 0; $d < $repeat; $d++) {
                        FetchTweetsKeywordJob::dispatch($username, $keyword, $mode)
                            ->onQueue('high')
                            ->delay($current);

                        $this->line("⏳ [$current] @$username → '$keyword' (mode=$mode, depth=$d)");

                        if ($d === 0 && $mode === 'fresh') {
                            $current->addSeconds($freshToHistDelay());
                        }
                    }
                }

                $current->addSeconds($keywordCooldown());
                $jobIndex += ($modeOption === 'all') ? ($maxDepth >= 3 ? 1 + ($maxDepth - 1) : 2) : $maxDepth;
            }

            $account->update(['last_used_at' => now()]);
            $this->info("📨 Dispatched $jobIndex jobs for @$username.");
        }
    }

    protected function isAccountLocked(string $username): bool
    {
        return !Cache::lock("fetch-lock:$username", 40 * 60)->get();
    }

    protected function lockAccount(string $username): void
    {
        Cache::lock("fetch-lock:$username", 40 * 60)->get();
    }
}
