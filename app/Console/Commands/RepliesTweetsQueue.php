<?php

namespace App\Console\Commands;

use App\Jobs\ReplyToQueuedJob;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use App\Traits\HasConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepliesTweetsQueue extends Command
{
    use HasConfig;

    protected $signature = 'twitter:replies-queue
        {--limit= : Max tweets to dispatch}
        {--max-account= : Max accounts to use}
        {--usage= : Percent of daily tweet limit to use}
        {--mode= : Mode: safe, balanced, aggressive}
        {--rest-start= : Rest window start time (e.g. 00:00)}
        {--rest-end= : Rest window end time (e.g. 03:00)}
        {--rest= : Max accounts to rest per hour}
        {--force : (bool) Force run even if AUTO_TWEET_REPLY is false}';

    protected $description = 'Dispatch reply jobs for tweets (status=queue) with related_tweet_id';

    protected const KEY_LAST_DELAY    = 'tweet:last-delay:';
    protected const KEY_COOLDOWN      = 'tweet:cooldown:';
    protected const KEY_COOLDOWN_FLAG = 'tweet:cooldown-flag:';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!Config::getValue('AUTO_TWEET_REPLY', true)) {
                $this->warn('⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.');

                return self::SUCCESS;
            }

            if ($this->isRestWindow()) {
                $this->warn("😴 Skipped due to rest window. Use --force to override.");

                return self::SUCCESS;
            }
        }

        $limit      = (int) $this->getConfig('TWEET_REPLY_LIMIT', 5, 'limit');
        $maxAccount = (int) $this->getConfig('TWEET_REPLY_MAX_ACCOUNT', 3, 'max-account');
        $usage      = max(1, min((int) $this->getConfig('TWEET_REPLY_USAGE_LIMIT', 85, 'usage'), 100));
        $mode       = $this->getConfig('TWEET_REPLY_MODE', 'balanced', 'mode');
        $restCount  = (int) $this->getConfig('TWEET_REPLY_REST_LIMIT', 1, 'rest');

        $config = $this->getModeConfig($mode);
        if (!$config) {
            $this->error("❌ Invalid mode: $mode. Use one of: safe, balanced, aggressive.");

            return self::FAILURE;
        }

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
            ->limit($limit)
            ->get();

        if ($tweets->isEmpty()) {
            $this->warn('⚠️ No tweets found to reply.');

            return self::SUCCESS;
        }

        $rawAccounts = UserTwitter::query()
            ->where('is_active', true)
            ->where('is_main', true)
            ->orderByRaw('CASE WHEN last_used_at IS NULL THEN 0 ELSE 1 END, last_used_at ASC')
            ->get();

        $accounts = $rawAccounts
            ->groupBy('username')
            ->map(fn ($group) => $group->random())
            ->filter(fn ($a) => !$this->isOnCooldown($a->id))
            ->values()
            ->take($maxAccount);

        $hourKey  = now()->format('YmdH');
        $accounts = $accounts->filter(function ($account) use ($restCount, $hourKey) {
            $restKey = "tweet:rest-limit:{$account->id}:$hourKey";
            $count   = Cache::get($restKey, 0);

            return $count < $restCount;
        })->values();

        if ($accounts->isEmpty()) {
            $this->warn('⚠️ No available account (cooldown or quota exceeded).');

            return self::SUCCESS;
        }

        $now        = now();
        $delays     = [];
        $dispatched = 0;

        foreach ($tweets as $i => $tweet) {
            $updated = Tweet::whereKey($tweet->id)->where('status', 'queue')->update(['status' => 'process']);
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
            if (!$lock->get()) {
                $this->warn("⏳ Skipped {$tweet->tweet_id} — Account @{$account->username} is busy.");
                $tweet->update(['status' => 'queue']);

                $restKey = "tweet:rest-limit:{$account->id}:" . now()->format('YmdH');
                $count   = Cache::increment($restKey);
                if ($count === 1) {
                    Cache::put($restKey, 1, now()->addHour()->startOfHour()->addHour()->diffInRealSeconds());
                }
                continue;
            }

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
        }

        $this->info("✅ $dispatched replies dispatched (mode=$mode, usage=$usage%).");

        return self::SUCCESS;
    }

    protected function isRestWindow(): bool
    {
        $now   = now();
        $start = Carbon::parse($this->getConfig('TWEET_REST_START_TIME', '00:00', 'rest-start'), $now->timezone);
        $end   = Carbon::parse($this->getConfig('TWEET_REST_END_TIME', '03:00', 'rest-end'), $now->timezone);

        if ($start->gt($end)) {
            $end->addDay();
        }

        return $now->between($start, $end);
    }

    protected function getModeConfig(string $mode): ?array
    {
        return [
            'safe'       => ['base' => 90, 'burst' => [60, 150], 'jitter' => [40, 90]],
            'balanced'   => ['base' => 60, 'burst' => [40, 120], 'jitter' => [30, 70]],
            'aggressive' => ['base' => 40, 'burst' => [20, 90], 'jitter' => [15, 50]],
        ][$mode] ?? null;
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
