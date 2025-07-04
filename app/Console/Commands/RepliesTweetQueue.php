<?php

namespace App\Console\Commands;

use App\Jobs\ReplyToQueuedJob;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use Illuminate\Console\Command;

class RepliesTweetQueue extends Command
{
    protected $signature = 'twitter:replies-queue
        {--limit=5 : Max tweets to dispatch}
        {--force : Force run even if AUTO_TWEET_REPLY is false}';

    protected $description = 'Dispatch reply jobs for tweets (status=queue) with related_tweet_id';

    public function handle()
    {
        if (!$this->option('force') && !Config::getValue('AUTO_TWEET_REPLY', true)) {
            $this->warn("⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.");

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit', 5);

        $excluded = UserTwitter::getExcludedUsernames()
            ->map(fn ($u) => strtolower($u))
            ->all() ?: [''];

        $tweets = Tweet::query()
            ->where('status', 'queue')
            ->whereNotNull('related_tweet_id')
            ->whereRaw(
                'LOWER(username) NOT IN (' . implode(',', array_fill(0, count($excluded), '?')) . ')',
                $excluded,
            )
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($tweets->isEmpty()) {
            $this->warn('⚠️ No tweets found to reply.');

            return self::SUCCESS;
        }

        $now        = now();
        $dispatched = 0;
        $delayTotal = 0;

        foreach ($tweets as $i => $tweet) {
            $updated = Tweet::whereKey($tweet->id)
                ->where('status', 'queue')
                ->update(['status' => 'process']);

            if (!$updated) {
                $this->line("⏭️ Skipped {$tweet->tweet_id} (already taken)");
                continue;
            }

            $jitter    = rand(5, 25);
            $burstWait = ($i > 0 && $i % 3 === 0) ? rand(20, 40) : 0;
            $delayTotal += $jitter + $burstWait;
            $delayAt = $now->copy()->addSeconds($delayTotal);

            ReplyToQueuedJob::dispatch($tweet->id)
                ->onQueue('medium')
                ->delay($delayAt);

            $this->line("🚀 Reply job for {$tweet->tweet_id} scheduled at {$delayAt->format('H:i:s')} (delay +{$jitter}s +{$burstWait}s)");

            $dispatched++;
        }

        $this->info("✅ $dispatched reply jobs dispatched.");

        return self::SUCCESS;
    }
}
