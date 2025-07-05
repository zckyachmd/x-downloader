<?php

namespace App\Console\Commands;

use App\Contracts\PendingJobCheckerContract;
use App\Jobs\ReplyToQueuedJob;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use Illuminate\Console\Command;

class RepliesTweetQueue extends Command
{
    protected $signature = 'twitter:replies-queue
        {--limit=3 : Max tweets to dispatch}
        {--force : Force run even if AUTO_TWEET_REPLY is false}';

    protected $description = 'Dispatch reply jobs for tweets (status=queue) with related_tweet_id';

    protected PendingJobCheckerContract $jobChecker;

    public function __construct(PendingJobCheckerContract $jobChecker)
    {
        parent::__construct();
        $this->jobChecker = $jobChecker;
    }

    public function handle()
    {
        if (!$this->option('force') && !Config::getValue('AUTO_TWEET_REPLY', true)) {
            $this->warn("⚠️ AUTO_TWEET_REPLY is disabled. Use --force to override.");

            return self::SUCCESS;
        }

        $pendingJobsCount = $this->jobChecker->getPendingReplyJobsCount();

        $max      = (int) $this->option('limit', 3);
        $jobLimit = max(1, $max - $pendingJobsCount);

        if ($pendingJobsCount > 0) {
            $this->warn("⚠️ There are {$pendingJobsCount} pending reply jobs. Reducing the number of new jobs.");
        }

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
            ->limit($jobLimit)
            ->get();

        if ($tweets->isEmpty()) {
            $this->warn('⚠️ No tweets found to reply.');

            return self::SUCCESS;
        }

        $now        = now();
        $dispatched = 0;
        $totalDelay = 0;

        foreach ($tweets as $i => $tweet) {
            $updated = Tweet::whereKey($tweet->id)
                ->where('status', 'queue')
                ->update(['status' => 'process']);

            if (!$updated) {
                $this->line("⏭️ Skipped {$tweet->tweet_id} (already taken)");
                continue;
            }

            $jitter       = rand(20, 35);
            $burstPadding = ($i >= 1 && $i % 2 === 0) ? rand(30, 60) : 0;

            $totalDelay += $jitter + $burstPadding;
            $delayAt = $now->copy()->addSeconds($totalDelay);

            ReplyToQueuedJob::dispatch($tweet->id)
                ->onQueue('medium')
                ->delay($delayAt);

            $this->line("🚀 Reply for {$tweet->tweet_id} → {$delayAt->format('H:i:s')} (+{$jitter}s +{$burstPadding}s)");
            $dispatched++;
        }

        $this->info("✅ $dispatched replies dispatched (max=$jobLimit, spread=$totalDelay s).");

        return self::SUCCESS;
    }
}
