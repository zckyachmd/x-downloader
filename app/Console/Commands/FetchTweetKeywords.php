<?php

namespace App\Console\Commands;

use App\Jobs\FetchTweetsKeywordJob;
use App\Models\UserTwitter;
use Illuminate\Console\Command;

class FetchTweetKeywords extends Command
{
    protected $signature   = 'twitter:fetch-tweets {--mode=fresh : Mode historical|fresh} {--limit=10 : Limit accounts per keyword to dispatch}';
    protected $description = 'Dispatch fetch tweet jobs per keyword per account';

    public function handle()
    {
        $keywords = array_filter(array_map('trim', explode(';', env('X_DOWNLOADER_KEYWORDS'))));
        $mode     = $this->option('mode') === 'historical' ? 'historical' : 'fresh';
        $limit    = (int) $this->option('limit', 10);

        $accounts = UserTwitter::where([
            ['is_active', true],
            ['is_main', false],
        ])
            ->select('username')
            ->limit($limit)
            ->get();

        if (empty($keywords) || $accounts->isEmpty()) {
            $this->warn("❌ Missing keywords or accounts.");

            return self::FAILURE;
        }

        foreach ($keywords as $keyword) {
            foreach ($accounts as $account) {
                FetchTweetsKeywordJob::dispatch($account->username, $keyword, $mode)
                    ->onQueue("tweets-{$mode}");
            }
        }

        $this->info("📥 Dispatched fetch jobs (mode: $mode) for " . count($keywords) . " keywords × {$accounts->count()} accounts.");

        return self::SUCCESS;
    }
}
