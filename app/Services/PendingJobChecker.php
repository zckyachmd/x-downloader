<?php

namespace App\Services;

use App\Contracts\PendingJobCheckerContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PendingJobChecker implements PendingJobCheckerContract
{
    /**
     * Check if there are pending ReplyToQueuedJob jobs in the queue.
     * Detects driver (redis or database) dynamically.
     *
     * @return bool
     */
    public function hasPendingReplyJobs(): bool
    {
        $driver = config('queue.default');

        return match ($driver) {
            'redis'    => $this->hasPendingReplyJobsRedis(),
            'database' => $this->hasPendingReplyJobsDatabase(),
            default    => false,
        };
    }

    /**
     * Get the count of pending ReplyToQueuedJob jobs in Redis.
     *
     * @return int
     */
    public function getPendingReplyJobsCount(): int
    {
        $driver = config('queue.default');

        return match ($driver) {
            'redis'    => $this->getPendingReplyJobsCountRedis(),
            'database' => $this->getPendingReplyJobsCountDatabase(),
            default    => 0,
        };
    }

    /**
     * Check for pending ReplyToQueuedJob jobs in Redis.
     *
     * @return bool
     */
    protected function hasPendingReplyJobsRedis(): bool
    {
        $key     = 'queues:medium';
        $samples = Redis::lrange($key, 0, 20);

        foreach ($samples as $payload) {
            if (str_contains($payload, 'ReplyToQueuedJob')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for pending ReplyToQueuedJob jobs in the Redis queue.
     *
     * @return int
     */
    protected function getPendingReplyJobsCountRedis(): int
    {
        $key   = 'queues:medium';
        $count = Redis::llen($key);

        return $count;
    }

    /**
     * Check for pending ReplyToQueuedJob jobs in the database.
     *
     * @return bool
     */
    protected function hasPendingReplyJobsDatabase(): bool
    {
        return DB::table('jobs')
            ->where('queue', 'medium')
            ->where('payload', 'like', '%ReplyToQueuedJob%')
            ->exists();
    }

    /**
     * Get the count of pending ReplyToQueuedJob jobs in the database.
     *
     * @return int
     */
    protected function getPendingReplyJobsCountDatabase(): int
    {
        return DB::table('jobs')
            ->where('queue', 'medium')
            ->where('payload', 'like', '%ReplyToQueuedJob%')
            ->count();
    }
}
