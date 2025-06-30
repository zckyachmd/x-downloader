<?php

namespace App\Console\Commands;

use App\Jobs\FetchTweetsKeywordJob;
use App\Models\Config;
use App\Models\UserTwitter;
use Illuminate\Console\Command;

class FetchTweetKeywords extends Command
{
    protected $signature = 'twitter:fetch-tweets
        {--mode=all : Mode to fetch: all, fresh, or historical}
        {--limit=3 : Limit accounts per keyword to dispatch}
        {--max-keyword=5 : Max number of keywords to process from config}
        {--force : Force run even if AUTO_TWEET_REPLY is false}';

    protected $description = 'Dispatch fetch tweet jobs per keyword per account (fresh + historical with depth)';

    public function handle()
    {
        if (!$this->option('force') && !Config::getValue('AUTO_TWEET_REPLY', true)) {
            $this->warn("⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.");

            return self::SUCCESS;
        }

        $modeOption   = $this->option('mode');
        $accountLimit = (int) $this->option('limit', 3);
        $keywordLimit = (int) $this->option('max-keyword', 5);

        $validModes = ['all', 'fresh', 'historical'];
        if (!in_array($modeOption, $validModes, true)) {
            $this->error("❌ Invalid mode: $modeOption. Use: fresh, historical, or all.");

            return self::FAILURE;
        }

        $allKeywords = array_filter(array_map('trim', explode(';', Config::getValue('TWEET_SEARCH_KEYWORDS', ''))));
        $keywords    = collect($allKeywords)->shuffle()->take($keywordLimit)->all();

        $accounts = UserTwitter::where('is_active', true)
            ->where('is_main', false)
            ->inRandomOrder()
            ->limit($accountLimit)
            ->pluck('username');

        if (empty($keywords) || $accounts->isEmpty()) {
            $this->warn("❌ Missing keywords or accounts.");

            return self::FAILURE;
        }

        $now      = now();
        $maxDepth = (int) Config::getValue('TWEET_SEARCH_KEYWORDS_MAX_DEPTH', 3);
        $modes    = $modeOption === 'all' ? ['fresh', 'historical'] : [$modeOption];
        $jobIndex = 0;

        foreach ($accounts as $userIndex => $username) {
            foreach ($keywords as $keywordIndex => $keyword) {
                foreach ($modes as $mode) {
                    $repeat = $mode === 'historical' ? $maxDepth : 1;

                    for ($depth = 0; $depth < $repeat; $depth++) {
                        $delaySeconds = ($userIndex * 5) +
                            ($keywordIndex * 20) +
                            ($depth * 15) +
                            ($mode === 'historical' ? 45 : 0) +
                            rand(2, 5);

                        $delay = $now->copy()->addSeconds($delaySeconds);

                        FetchTweetsKeywordJob::dispatch($username, $keyword, $mode)
                            ->delay($delay);

                        $this->line("⏳ [$delay] @$username → '$keyword' (mode=$mode, depth=$depth, delay={$delaySeconds}s)");
                        $jobIndex++;
                    }
                }
            }
        }

        $this->info("📥 Dispatched $jobIndex fetch jobs (mode: $modeOption)");

        return self::SUCCESS;
    }
}
