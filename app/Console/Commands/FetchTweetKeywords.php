<?php

namespace App\Console\Commands;

use App\Jobs\FetchTweetsKeywordJob;
use App\Models\Config;
use App\Models\UserTwitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchTweetKeywords extends Command
{
    protected $signature = 'twitter:fetch-tweets
        {--mode=all : Mode to fetch: all, fresh, or historical}
        {--limit=3 : Limit number of accounts}
        {--max-keyword=3 : Limit keywords from config}
        {--rest=1 : Max accounts to rest per hour}
        {--force : Override AUTO_SEARCH_TWEET check}';

    protected $description = 'Dispatch fetch jobs per keyword per account with staggered delays';

    public function handle()
    {
        if (!$this->option('force') && !Config::getValue('AUTO_SEARCH_TWEET', true)) {
            $this->warn("⚠️ AUTO_SEARCH_TWEET is disabled. Use --force to override.");

            return self::SUCCESS;
        }

        $modeOption   = $this->option('mode');
        $accountLimit = (int) $this->option('limit', 3);
        $keywordLimit = (int) $this->option('max-keyword', 3);

        if (!in_array($modeOption, ['all', 'fresh', 'historical'], true)) {
            $this->error("❌ Invalid mode: $modeOption. Use fresh, historical, or all.");

            return self::FAILURE;
        }

        $keywords = collect(explode(';', Config::getValue('TWEET_SEARCH_KEYWORDS', '')))
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

        $this->dispatchJobs($accounts, $keywords, $modeOption);

        return self::SUCCESS;
    }

    protected function dispatchJobs($accounts, $keywords, string $modeOption): void
    {
        $now                = now();
        $jobIndex           = 0;
        $maxDepth           = (int) Config::getValue('TWEET_SEARCH_KEYWORDS_MAX_DEPTH', 2);
        $keywordCooldown    = fn () => rand(150, 180);
        $freshToHistDelay   = fn () => rand(60, 90);
        $accountStartOffset = fn ($i) => $i * rand(10, 25);

        $accountList = $accounts->values();
        $keywordMap  = [];

        $restCount = max(0, (int) $this->option('rest', 0));
        $restKey   = 'fetch-rest-accounts:' . $now->format('YmdH');

        $restedUsernames = Cache::remember($restKey, now()->addMinutes(61), function () use ($accountList, $restCount) {
            $available = $accountList->pluck('username')->toArray();

            if ($restCount >= count($available)) {
                $restCount = count($available) - 1;
            }

            return collect($available)->shuffle()->take($restCount)->values()->all();
        });

        $this->line('🛌 Accounts resting this hour: ' . implode(', ', $restedUsernames));

        foreach ($keywords as $i => $keyword) {
            $accountIndex                     = $i % $accountList->count();
            $account                          = $accountList[$accountIndex];
            $keywordMap[$account->username][] = $keyword;
        }

        foreach ($accountList as $accountIndex => $account) {
            $username = $account->username;

            if (in_array($username, $restedUsernames)) {
                $this->warn("😴 @$username is resting this hour. Skipping.");
                continue;
            }

            if (empty($keywordMap[$username])) {
                $this->warn("⚠️ @$username has no keywords assigned. Skipping.");
                continue;
            }

            if ($this->isAccountLocked($username)) {
                $this->warn("🔁 @$username is still in progress. Skipping.");
                continue;
            }

            $this->lockAccount($username);

            $startTime = $now->copy()->addSeconds($accountStartOffset($accountIndex));
            $current   = clone $startTime;

            foreach ($keywordMap[$username] as $keyword) {
                $modes = $modeOption === 'all' ? ['fresh', 'historical'] : [$modeOption];

                foreach ($modes as $mode) {
                    $repeat = match ($mode) {
                        'fresh'      => 1,
                        'historical' => max(0, $maxDepth - 1),
                    };

                    for ($depth = 0; $depth < $repeat; $depth++) {
                        FetchTweetsKeywordJob::dispatch($username, $keyword, $mode)
                            ->onQueue('high')
                            ->delay($current);

                        $this->line("⏳ [$current] @$username → '$keyword' (mode=$mode, depth=$depth)");

                        if ($depth === 0 && $mode === 'fresh') {
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

        $this->info("📥 Total dispatched jobs: $jobIndex");
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
