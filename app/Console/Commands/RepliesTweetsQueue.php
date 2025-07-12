<?php

namespace App\Console\Commands;

use App\Jobs\ReplyToQueuedJob;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepliesTweetsQueue extends Command
{
    protected $signature = 'twitter:replies-queue
        {--limit=5 : Max tweets to dispatch}
        {--max-account=3 : Max accounts to use}
        {--usage=85 : Percent of daily tweet limit to use}
        {--mode=balanced : Mode: safe, balanced, aggressive}
        {--rest=1 : Max accounts to rest per hour}
        {--force : Force run even if AUTO_TWEET_REPLY is false}';

    protected $description = 'Dispatch reply jobs for tweets (status=queue) with related_tweet_id';

    protected const KEY_LAST_DELAY    = 'tweet:last-delay:';
    protected const KEY_COOLDOWN      = 'tweet:cooldown:';
    protected const KEY_COOLDOWN_FLAG = 'tweet:cooldown-flag:';

    public function handle()
    {
        if (!$this->option('force') && !Config::getValue('AUTO_TWEET_REPLY', true)) {
            $this->warn('⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.');

            return self::FAILURE;
        }

        $modePresets = [
            'safe'       => ['base' => 90, 'burst' => [60, 150], 'jitter' => [40, 90]],
            'balanced'   => ['base' => 60, 'burst' => [40, 120], 'jitter' => [30, 70]],
            'aggressive' => ['base' => 40, 'burst' => [20, 90], 'jitter' => [15, 50]],
        ];

        $mode   = $this->option('mode');
        $config = $modePresets[$mode] ?? null;

        if (!$config) {
            $this->error("❌ Invalid mode: $mode. Use one of: safe, balanced, aggressive.");

            return self::FAILURE;
        }

        $usage     = max(1, min((int) $this->option('usage', 85), 100));
        $maxDaily  = (int) (2400 * $usage / 100);
        $maxHourly = (int) ($maxDaily / 24);

        $excluded = UserTwitter::getExcludedUsernames()
            ->map(fn ($u) => strtolower($u))
            ->all() ?: [''];

        $tweets = Tweet::query()
            ->where('status', 'queue')
            ->whereNotNull('related_tweet_id')
            ->whereNotIn(DB::raw('LOWER(username)'), $excluded)
            ->orderBy('created_at')
            ->limit((int) $this->option('limit', 5))
            ->get();

        if ($tweets->isEmpty()) {
            $this->warn('⚠️ No tweets found to reply.');

            return;
        }

        $accounts = UserTwitter::query()
            ->where('is_active', true)
            ->where('is_main', true)
            ->orderByRaw('CASE WHEN last_used_at IS NULL THEN 0 ELSE 1 END, last_used_at ASC')
            ->limit((int) $this->option('max-account', 3))
            ->get()
            ->filter(fn ($a) => !$this->isOnCooldown($a->id))
            ->values();

        $restCount = max(0, (int) $this->option('rest', 0));

        if ($restCount > 0 && $accounts->count() > $restCount) {
            $hourKey   = now()->format('YmdH');
            $restKey   = "tweet:rested-accounts:$hourKey";
            $restedIds = Cache::remember($restKey, now()->addHour()->diffInSeconds(), function () use ($accounts, $restCount) {
                return $accounts->shuffle()->take($restCount)->pluck('id')->all();
            });

            $accounts = $accounts->filter(fn ($a) => !in_array($a->id, $restedIds))->values();
            $this->line("💤 Rested accounts this hour: " . implode(', ', $restedIds));
        }

        if ($accounts->isEmpty()) {
            $this->warn('⚠️ No available account (cooldown or quota exceeded).');

            return;
        }

        $now        = now();
        $delays     = [];
        $dispatched = 0;

        foreach ($tweets as $i => $tweet) {
            $updated = Tweet::whereKey($tweet->id)
                ->where('status', 'queue')
                ->update(['status' => 'process']);

            if (!$updated) {
                $this->line("⏭️ Skipped {$tweet->tweet_id} (taken)");
                continue;
            }

            $account = $accounts
                ->filter(fn ($a) => $this->hasQuota($a->id, $maxDaily, $maxHourly))
                ->sortBy(fn ($a) => $delays[$a->id] ?? 0)
                ->first();

            if (!$account) {
                $this->warn("🚫 All accounts quota full or on cooldown.");
                $tweet->update(['status' => 'queue']);
                continue;
            }

            $lock = Cache::lock("lock:tweet:account:{$account->id}", 10);

            if ($lock->get()) {
                try {
                    $runAt = $this->calculateNextRunTime($account->id, $now, $config, $i);

                    ReplyToQueuedJob::dispatch($tweet->id, $account->id)
                        ->onQueue('medium')
                        ->delay($runAt);

                    $this->line("🚀 {$tweet->tweet_id} → {$runAt->format('H:i:s')} [@{$account->username}]");

                    $dispatched++;

                    $this->incrementQuota($account->id);
                    $this->scheduleCooldown($account->id, $runAt);
                    $account->forceFill(['last_used_at' => now()])->save();
                } finally {
                    $lock->release();
                }
            } else {
                $this->warn("⏳ Skipped {$tweet->tweet_id} — Account @{$account->username} is busy.");
                $tweet->update(['status' => 'queue']);
                continue;
            }
        }

        $this->info("✅ $dispatched replies dispatched (mode=$mode, usage=$usage%).");

        return self::SUCCESS;
    }

    protected function getQuotaKey(int $accountId, string $type): string
    {
        $stamp = now()->format($type === 'hourly' ? 'YmdH' : 'Ymd');

        return "tweet:quota:{$type}:{$accountId}:{$stamp}";
    }

    protected function hasQuota(int $accountId, int $maxDaily, int $maxHourly): bool
    {
        $dailyUsed  = Cache::get($this->getQuotaKey($accountId, 'daily'), 0);
        $hourlyUsed = Cache::get($this->getQuotaKey($accountId, 'hourly'), 0);

        return $dailyUsed < $maxDaily && $hourlyUsed < $maxHourly;
    }

    protected function calculateNextRunTime(int $accountId, Carbon $now, array $config, int $index): Carbon
    {
        $key      = self::KEY_LAST_DELAY . $accountId;
        $lastRun  = Cache::get($key, 0);
        $baseTime = max($now->timestamp, $lastRun);
        $baseAt   = Carbon::createFromTimestamp($baseTime);

        $jitter = rand(...$config['jitter']);
        $burst  = ($index > 0 && $index % 3 === 0) ? rand(...$config['burst']) : 0;
        $delay  = $config['base'] + $jitter + $burst;

        $runAt = $baseAt->copy()->addSeconds($delay);
        Cache::put($key, $runAt->timestamp, 3600);

        $this->line("⏱️ Delay @{$accountId}: {$delay}s (base={$config['base']} + jitter={$jitter} + burst={$burst})");

        return $runAt;
    }

    protected function incrementQuota(int $accountId): void
    {
        $dailyKey  = $this->getQuotaKey($accountId, 'daily');
        $hourlyKey = $this->getQuotaKey($accountId, 'hourly');

        $dailyTTL  = now()->endOfDay()->diffInRealSeconds();
        $hourlyTTL = now()->addHour()->startOfHour()->addHour()->diffInRealSeconds();

        if (Cache::increment($dailyKey) === 1) {
            Cache::put($dailyKey, 1, $dailyTTL);
        }
        if (Cache::increment($hourlyKey) === 1) {
            Cache::put($hourlyKey, 1, $hourlyTTL);
        }
    }

    protected function isOnCooldown(int $accountId): bool
    {
        $cooldownUntil = Cache::get(self::KEY_COOLDOWN . "$accountId", 0);

        return now()->timestamp < $cooldownUntil;
    }

    protected function scheduleCooldown(int $accountId, Carbon $runAt): void
    {
        $hourKey         = now()->format('YmdH');
        $cooldownFlagKey = self::KEY_COOLDOWN_FLAG . "$accountId:$hourKey";

        if (Cache::has($cooldownFlagKey)) {
            $this->line("⏩ Skipped cooldown for @{$accountId} (already rested this hour)");

            return;
        }

        $now = now();
        $gap = max(60, min(600, $runAt->diffInSeconds($now)));

        $minTTL = max(90, (int) ($gap * 0.5));
        $maxTTL = max(180, (int) ($gap * 1.2));
        $ttl    = rand($minTTL, $maxTTL);
        $until  = $now->copy()->addSeconds($ttl)->timestamp;

        Cache::put(self::KEY_COOLDOWN . $accountId, $until, $ttl);

        $expiresIn = now()->addHour()->startOfHour()->diffInRealSeconds();
        Cache::put($cooldownFlagKey, true, $expiresIn);
    }
}
