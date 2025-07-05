<?php

namespace App\Contracts;

interface PendingJobCheckerContract
{
    /**
     * Check if there are pending ReplyToQueuedJob jobs in the queue.
     *
     * @return bool
     */
    public function hasPendingReplyJobs(): bool;

    /**
     * Get the count of pending ReplyToQueuedJob jobs.
     *
     * @return int
     */
    public function getPendingReplyJobsCount(): int;
}
