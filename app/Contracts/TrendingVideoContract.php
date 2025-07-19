<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface TrendingVideoContract
{
    /**
     * Get daily trending videos.
     *
     * @param int $limit
     * @param bool $excludeSensitive
     * @return Collection
     */
    public function getDaily(int $limit = 10, bool $excludeSensitive = false): Collection;

    /**
     * Get weekly trending videos.
     *
     * @param int $limit
     * @param bool $excludeSensitive
     * @return Collection
     */
    public function getWeekly(int $limit = 10, bool $excludeSensitive = false): Collection;
}
