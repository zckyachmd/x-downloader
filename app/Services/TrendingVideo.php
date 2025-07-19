<?php

namespace App\Services;

use App\Contracts\TrendingVideoContract;
use App\Models\VideoDownload;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TrendingVideo implements TrendingVideoContract
{
    public function getDaily(int $limit = 10, bool $excludeSensitive = false): Collection
    {
        $today = Carbon::today();

        return $this->fetchTrending($today, $today->copy()->endOfDay(), $limit, $excludeSensitive);
    }

    public function getWeekly(int $limit = 10, bool $excludeSensitive = false): Collection
    {
        $start = Carbon::now()->startOfWeek();
        $end   = Carbon::now()->endOfWeek();

        return $this->fetchTrending($start, $end, $limit, $excludeSensitive);
    }

    protected function fetchTrending(Carbon $start, Carbon $end, int $limit, bool $excludeSensitive): Collection
    {
        $cacheKey = "trending_videos_{$start->toDateString()}_{$end->toDateString()}_limit{$limit}_excludeSens" . ($excludeSensitive ? '1' : '0');

        $ttl = $this->getCacheTtl();

        return Cache::remember($cacheKey, $ttl, function () use ($start, $end, $limit, $excludeSensitive) {
            $query = VideoDownload::query()
                ->selectRaw('tweet_id, video_index, SUM(total_count) as total')
                ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->groupBy('tweet_id', 'video_index')
                ->orderByDesc('total')
                ->limit($limit);

            if ($excludeSensitive) {
                $query->whereHas('tweet', function ($q) {
                    $q->where('is_sensitive', false);
                });
            }

            $records = $query->with('tweet')->get();

            return $records->map(function ($row) {
                $tweet = $row->tweet;
                if (!$tweet) {
                    return null;
                }

                $media     = collect($tweet->media)->get($row->video_index, []);
                $thumbnail = $media['preview_url'] ?? null;

                return [
                    'tweet_id'      => $tweet->tweet_id,
                    'text'          => $tweet->tweet,
                    'username'      => $tweet->username,
                    'media'         => $media,
                    'thumbnail_url' => $thumbnail,
                    'video_index'   => $row->video_index,
                    'total_count'   => $row->total,
                ];
            })->filter()->values();
        });
    }

    protected function getCacheTtl(int $intervalMinutes = 30): int
    {
        $now        = Carbon::now();
        $minute     = $now->minute;
        $passed     = $minute % $intervalMinutes;
        $ttlMinutes = $passed === 0 ? $intervalMinutes : $intervalMinutes - $passed;

        return $ttlMinutes * 60;
    }
}
