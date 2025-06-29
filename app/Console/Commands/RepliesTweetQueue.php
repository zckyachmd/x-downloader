<?php

namespace App\Console\Commands;

use App\Jobs\ReplyToQueuedJob;
use App\Models\Tweet;
use Illuminate\Console\Command;

class RepliesTweetQueue extends Command
{
    protected $signature   = 'twitter:replies-queue {--limit=5 : Max queued tweets to process}';
    protected $description = 'Dispatch jobs to reply to tweets with status=queue and a related_tweet_id';

    public function handle(): int
    {
        $limit = (int) $this->option('limit') ?: 5;

        $tweets = Tweet::query()
            ->where('status', 'queue')
            ->whereNotNull('related_tweet_id')
            ->limit($limit)
            ->get();

        if ($tweets->isEmpty()) {
            $this->warn('⚠️  No queued tweets with related_tweet_id found.');

            return self::FAILURE;
        }

        foreach ($tweets as $tweet) {
            ReplyToQueuedJob::dispatch($tweet->id)->onQueue('tweet-replies-queue');
            $this->line("➡️  Dispatched reply job for tweet ID: {$tweet->tweet_id}");
        }

        $this->info("✅ Dispatched {$tweets->count()} reply jobs.");

        return self::SUCCESS;
    }
}
