<?php

namespace App\Console\Commands;

use App\Jobs\RefreshTwitterAccountJob;
use App\Models\UserTwitter;
use Illuminate\Console\Command;

class RefreshInactiveTwitterAccounts extends Command
{
    protected $signature = 'twitter:refresh-inactive
        {--limit=3 : Max accounts to refresh}
        {--mode=light : Mode of refresh: light|deep}';

    protected $description = 'Dispatch jobs to refresh inactive or stale Twitter accounts';

    public function handle()
    {
        $limit = (int) $this->option('limit', 3);
        $mode  = in_array($this->option('mode'), ['light', 'deep']) ? $this->option('mode') : 'light';

        $minDays = $mode === 'deep' ? rand(2, 3) : rand(5, 7);
        $now     = now();

        $accounts = UserTwitter::select(['username'])
            ->where(function ($q) use ($minDays, $now) {
                $q->where('is_active', false)
                    ->orWhere('updated_at', '<=', $now->subDays($minDays));
            })
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($accounts->isEmpty()) {
            $this->info("✅ No stale/inactive accounts found.");

            return self::SUCCESS;
        }

        $this->info("🔍 Found {$accounts->count()} accounts (idle ≥ {$minDays} days)");

        $baseDelay   = 10;
        $jitterRange = [5, 15];

        foreach ($accounts as $i => $account) {
            $jitter = rand(...$jitterRange);
            $delay  = now()->addSeconds(($i * $baseDelay) + $jitter);

            RefreshTwitterAccountJob::dispatch($account->username, $mode)
                ->delay($delay);

            $this->line("⏳ Delayed @$account->username by {$delay->diffInSeconds(now())}s");
        }

        $this->info("📤 Dispatched refresh jobs in mode={$mode} with spacing.");

        return self::SUCCESS;
    }
}
