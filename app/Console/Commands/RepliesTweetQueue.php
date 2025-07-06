<?php

namespace App\Console\Commands;

use App\Jobs\ReplyToQueuedJob;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepliesTweetQueue extends Command
{
    protected $signature = 'twitter:replies-queue
        {--limit=5 : Max tweets to dispatch}
        {--max-account=3 : Max accounts to use}
        {--usage=85 : Percent of daily tweet limit to use}
        {--force : Force run even if AUTO_TWEET_REPLY is false}';

    protected $description = 'Dispatch reply jobs for tweets (status=queue) with related_tweet_id';

    protected int $maxDailyLimit;
    protected int $maxHourlyLimit;

    public function handle(): int
    {
        if (!$this->option('force') && !Config::getValue('AUTO_TWEET_REPLY', true)) {
            $this->warn('⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.');

            return self::SUCCESS;
        }

        $usagePercent         = max(1, min((int) $this->option('usage', 85), 100));
        $this->maxDailyLimit  = (int) (2400 * $usagePercent / 100);
        $this->maxHourlyLimit = (int) ($this->maxDailyLimit / 24);

        $excluded = UserTwitter::getExcludedUsernames()
            ->map(fn ($u) => strtolower($u))
            ->all() ?: [''];

        $limit        = (int) $this->option('limit', 5);
        $accountLimit = (int) $this->option('max-account', 3);

        $tweets = Tweet::query()
            ->where('status', 'queue')
            ->whereNotNull('related_tweet_id')
            ->whereNotIn(DB::raw('LOWER(username)'), $excluded)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($tweets->isEmpty()) {
            $this->warn('⚠️ No tweets found to reply.');

            return self::SUCCESS;
        }

        $accounts = UserTwitter::query()
            ->where('is_active', true)
            ->where('is_main', true)
            ->orderByRaw('CASE WHEN last_used_at IS NULL THEN 0 ELSE 1 END, last_used_at ASC')
            ->limit($accountLimit)
            ->get()
            ->filter(fn ($acc) => !$this->isOnCooldown($acc->id))
            ->values();

        if ($accounts->isEmpty()) {
            $this->error('❌ No active main accounts available.');

            return self::FAILURE;
        }

        $now        = now();
        $delay      = 0;
        $dispatched = 0;

        foreach ($tweets as $i => $tweet) {
            $updated = Tweet::whereKey($tweet->id)
                ->where('status', 'queue')
                ->update(['status' => 'process']);

            if (!$updated) {
                $this->line("⏭️ Skipped {$tweet->tweet_id} (already taken)");
                continue;
            }

            $account = $accounts[$i % $accounts->count()];

            if (!$this->hasQuotaLeft($account->id)) {
                $this->warn("🚫 Skipped @{$account->username} — quota reached (hourly/daily)");
                $tweet->update(['status' => 'queue']);
                continue;
            }

            $this->incrementTweetQuota($account->id);
            $account->forceFill(['last_used_at' => now()])->save();

            $jitter       = rand(40, 65);
            $burstPadding = ($i > 0 && $i % 2 === 0) ? rand(90, 180) : 0;
            $delay += $jitter + $burstPadding;

            $runAt = $now->copy()->addSeconds($delay);

            ReplyToQueuedJob::dispatch($tweet->id, $account->id)
                ->onQueue('medium')
                ->delay($runAt);

            $this->scheduleRandomCooldown($account->id);
            $this->line("🚀 Reply for {$tweet->tweet_id} at {$runAt->format('H:i:s')} (+{$jitter}s +{$burstPadding}s) [@{$account->username}]");

            $dispatched++;
        }

        $this->info("✅ $dispatched replies dispatched (limit=$limit, usage={$usagePercent}%, spread=$delay s).");

        return self::SUCCESS;
    }

    protected function getTweetQuotaKey(int $accountId, string $type): string
    {
        $timestamp = now()->format($type === 'hourly' ? 'YmdH' : 'Ymd');

        return "tweet:quota:{$type}:{$accountId}:{$timestamp}";
    }

    protected function hasQuotaLeft(int $accountId): bool
    {
        $daily  = Cache::get($this->getTweetQuotaKey($accountId, 'daily'), 0);
        $hourly = Cache::get($this->getTweetQuotaKey($accountId, 'hourly'), 0);

        return $daily < $this->maxDailyLimit && $hourly < $this->maxHourlyLimit;
    }

    protected function incrementTweetQuota(int $accountId): void
    {
        $dailyKey  = $this->getTweetQuotaKey($accountId, 'daily');
        $hourlyKey = $this->getTweetQuotaKey($accountId, 'hourly');

        $dailyTTL  = now()->endOfDay()->diffInRealSeconds();
        $hourlyTTL = now()->addHour()->startOfHour()->addHour()->diffInRealSeconds();

        $daily  = Cache::increment($dailyKey);
        $hourly = Cache::increment($hourlyKey);

        if ($daily === 1) {
            Cache::put($dailyKey, $daily, $dailyTTL);
        }

        if ($hourly === 1) {
            Cache::put($hourlyKey, $hourly, $hourlyTTL);
        }
    }

    protected function isOnCooldown(int $accountId): bool
    {
        return Cache::has("tweet:cooldown:{$accountId}");
    }

    protected function scheduleRandomCooldown(int $accountId): void
    {
        $hourKey = "tweet:cooldown:scheduled:{$accountId}:" . now()->format('YmdH');

        if (Cache::has($hourKey)) {
            return;
        }

        $delay = rand(0, 2700);
        $ttl   = rand(60, 900);

        Cache::put("tweet:cooldown:{$accountId}", true, $delay + $ttl);
        Cache::put($hourKey, true, now()->endOfHour()->diffInRealSeconds());

        $this->line("💤 Cooldown scheduled for @{$accountId}: in {$delay}s, for {$ttl}s");
    }
}
