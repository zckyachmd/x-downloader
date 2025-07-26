<?php

namespace App\Console\Commands;

use App\Jobs\RefreshTwitterAccountJob;
use App\Models\UserTwitter;
use App\Traits\HasConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshInactiveTwitterAccounts extends Command
{
    use HasConfig;

    protected $signature = 'twitter:refresh-inactive
        {--limit= : Max accounts to refresh (default from config or 2-5)}
        {--mode= : Mode of refresh: light|deep (default=light)}
        {--force : (bool) Force run even if AUTO_REFRESH_TOKEN is false}';

    protected $description = 'Dispatch jobs to refresh inactive or stale Twitter accounts';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->getConfig('AUTO_REFRESH_TOKEN', true)) {
            $this->warn("⚠️ AUTO_REFRESH_TOKEN is disabled. Use --force to override.");

            return self::SUCCESS;
        }

        $mode         = $this->getConfig('TWITTER_REFRESH_MODE', 'light', 'mode');
        $limit        = (int) $this->getConfig('TWITTER_REFRESH_LIMIT', rand(2, 5), 'limit');
        $activeChance = 20;
        $minDays      = $mode === 'deep' ? rand(2, 3) : rand(5, 8);
        $now          = now();

        $candidates = UserTwitter::select(['username', 'password', 'is_active', 'updated_at'])
            ->where('username', '!=', '')->whereNotNull('username')
            ->where('password', '!=', '')->whereNotNull('password')
            ->where('updated_at', '<=', $now->copy()->subDays($minDays))
            ->inRandomOrder()->get()
            ->filter(fn ($a) => !$a->is_active || rand(1, 100) <= $activeChance)
            ->unique('username')->take($limit)->values();

        if ($candidates->isEmpty()) {
            $this->info("✅ No stale/inactive accounts found.");

            return self::SUCCESS;
        }

        $this->info("🔍 Found {$candidates->count()} accounts (idle ≥ {$minDays} days)");

        $lockTtl    = rand(300, 900);
        $delaysUsed = [];

        foreach ($candidates as $account) {
            $lockKey = "refresh-lock:{$account->username}";
            $lock    = Cache::lock($lockKey, $lockTtl);

            if (!$lock->get()) {
                $this->line("🚫 Skip @$account->username (already queued)");
                continue;
            }

            do {
                $delaySeconds = rand(7, 45);
            } while (in_array($delaySeconds, $delaysUsed));
            $delaysUsed[] = $delaySeconds;

            RefreshTwitterAccountJob::dispatch($account->username, $mode)
                ->onQueue('high')
                ->delay(now()->addSeconds($delaySeconds));

            $this->line("⏳ Delayed @$account->username by {$delaySeconds}s");
            usleep(rand(500, 1500) * 1000);
        }

        $this->info("📤 Dispatched refresh jobs in mode={$mode}.");

        return self::SUCCESS;
    }
}
