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
        {--force : Override AUTO_TWEET_REPLY check}';

    protected $description = 'Dispatch fetch jobs per keyword per account with staggered delays';

    public function handle()
    {
        if (!$this->option('force') && !Config::getValue('AUTO_TWEET_REPLY', true)) {
            $this->warn("⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.");

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

        $accounts = UserTwitter::query()
            ->where('is_active', true)
            ->where('is_main', false)
            ->inRandomOrder()
            ->limit($accountLimit)
            ->pluck('username');

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

        foreach ($keywords as $i => $keyword) {
            $accountIndex            = $i % $accountList->count();
            $username                = $accountList[$accountIndex];
            $keywordMap[$username][] = $keyword;
        }

        foreach ($accountList as $accountIndex => $username) {
            if ($this->isAccountLocked($username)) {
                $this->warn("🔁 @$username is still in progress. Skipping.");
                continue;
            }

            $this->lockAccount($username);

            $startTime              = $now->copy()->addSeconds($accountStartOffset($accountIndex));
            $current                = clone $startTime;
            $keywordsForThisAccount = collect($keywordMap[$username] ?? []);

            foreach ($keywordsForThisAccount as $keyword) {
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

            $this->info("📨 Dispatched $jobIndex jobs for @$username.");
        }

        $this->info("📥 Total dispatched jobs: $jobIndex");
    }

    protected function isAccountLocked(string $username): bool
    {
        return Cache::has("fetch-lock:$username");
    }

    protected function lockAccount(string $username): void
    {
        $lockDuration = 40 * 60;
        Cache::put("fetch-lock:$username", true, $lockDuration);
    }
}
