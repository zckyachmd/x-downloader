<?php

namespace App\Services;

use App\Contracts\TweetVideoServiceContract;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use App\Traits\EncodesVideoKey;
use App\Utils\UserAgent;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class TweetVideoService implements TweetVideoServiceContract
{
    use EncodesVideoKey;

    protected $userAgent;

    public function __construct()
    {
        $this->userAgent = new UserAgent();
    }

    public function get(int $tweetId, bool $skipSignedRoute = false, bool $proxyThumbnail = true, bool $allowApiFallback = false): ?array
    {
        $cacheKey = "tweet:$tweetId";
        $mapped   = Cache::get($cacheKey);

        if (!$mapped) {
            $model = Tweet::where('tweet_id', $tweetId)
                ->where('status', 'video')
                ->first();

            $data = $model?->toArray();

            if (!$data && $allowApiFallback) {
                $lockKey = "tweet:fetching:$tweetId";

                if (Cache::add($lockKey, true, 10)) {
                    $data = $this->fetchFromAPI($tweetId);
                    Cache::forget($lockKey);
                } else {
                    $waitUntil = now()->addSeconds(2);
                    while (now()->lt($waitUntil)) {
                        usleep(200_000); // 200ms
                        $data = Tweet::where('tweet_id', $tweetId)
                            ->where('status', 'video')
                            ->first()?->toArray();
                        if ($data) {
                            break;
                        }
                    }
                }
            }

            if (!$data) {
                return null;
            }

            $mapped = $this->present($data);
            if (!$mapped) {
                return null;
            }

            Cache::put($cacheKey, $mapped, now()->addMinutes(5));
        }

        $mapped['media'] = collect($mapped['media'] ?? [])->map(function ($m) use ($skipSignedRoute, $proxyThumbnail) {
            $videoKey     = $m['key'] ?? null;
            $durationSec  = ($m['duration_ms'] ?? 0) / 1000;
            $validMinutes = max(10, min(180, ceil($durationSec * 5 / 60)));

            return [
                ...$m,
                'video' => (!$skipSignedRoute && filled($videoKey))
                    ? URL::temporarySignedRoute('tweet.preview', now()->addMinutes($validMinutes), ['videoKey' => $videoKey])
                    : null,
                'thumbnail' => $proxyThumbnail
                    ? route('tweet.thumbnail', ['key' => $videoKey])
                    : ($m['thumbnail'] ?? null),
                'variants' => collect($m['variants'] ?? [])->map(function ($v) use ($skipSignedRoute) {
                    return !$skipSignedRoute ? Arr::except($v, ['url']) : $v;
                })->values(),
            ];
        })->values()->all();

        return $mapped;
    }

    public function imageThumbnail(int $tweetId, int $index = 0): ?array
    {
        $cacheKey = "tweet:thumb:url:{$tweetId}:{$index}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $tweet = $this->get(
                tweetId: $tweetId,
                proxyThumbnail: false,
            );

            $media = $tweet['media'][$index] ?? null;
            $url   = $media['thumbnail'] ?? null;

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            $result = [
                'stream'       => $url,
                'content_type' => 'image/jpeg',
            ];

            Cache::put($cacheKey, $result, now()->addMinutes(60));

            return $result;
        } catch (\Throwable $e) {
            Log::error('[TweetVideoService] Thumbnail lookup failed', [
                'tweet_id' => $tweetId,
                'index'    => $index,
                'error'    => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function streamVideo(int $tweetId, int $index = 0, bool $isPreview = false, ?int $bitrate = null, ?string $rangeHeader = null): ?array
    {
        try {
            $tweet    = $this->get($tweetId, skipSignedRoute: true, proxyThumbnail: $isPreview);
            $variants = $tweet['media'][$index]['variants'] ?? [];
            $url      = $isPreview
                ? collect($variants)->filter(fn ($v) => isset($v['bitrate']))->sortByDesc('bitrate')->first()['url'] ?? null
                : collect($variants)->first(fn ($v) => (int) $v['bitrate'] === (int) $bitrate)['url'] ?? null;
            if (!$url) {
                return null;
            }

            if (!$isPreview && $bitrate === null) {
                Log::warning('[TweetVideoService] Bitrate required for non-preview stream', compact('tweetId', 'index'));

                return null;
            }

            $client = new Client([
                'headers' => ['User-Agent' => $this->userAgent->random()],
                'stream'  => true,
                'timeout' => 0,
            ]);

            $head          = $client->head($url);
            $totalLength   = (int) $head->getHeaderLine('Content-Length');
            [$start, $end] = [0, $totalLength - 1];

            if ($rangeHeader && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
                $start = (int) $matches[1];
                $end   = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $end;
            }

            $headers = [
                'Range'      => "bytes=$start-$end",
                'User-Agent' => $this->userAgent->random(),
            ];

            $response = $client->get($url, [
                'headers' => $headers,
                'stream'  => true,
            ]);

            return [
                'stream'       => $response->getBody(),
                'content_type' => $response->getHeaderLine('Content-Type') ?: 'video/mp4',
                'total_length' => $totalLength,
                'start'        => $start,
                'end'          => $end,
                'status'       => $rangeHeader ? 206 : 200,
            ];
        } catch (\Throwable $e) {
            Log::error('[TweetVideoService] Stream failed', [
                'tweet_id' => $tweetId,
                'index'    => $index,
                'bitrate'  => $bitrate,
                'preview'  => $isPreview,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fetchFromAPI(int $tweetId, int $maxProcess = 3): ?array
    {
        $lockKey = "tweet:fetch-lock:$tweetId";
        $lock    = Cache::lock($lockKey, 30);

        if (!$lock->get()) {
            usleep(random_int(150, 300) * 1000);

            return null;
        }

        try {
            $accounts = UserTwitter::where('is_active', true)
                ->where('is_main', false)
                ->inRandomOrder()
                ->limit($maxProcess)
                ->get();

            $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/tweet/detail';

            foreach ($accounts as $user) {
                if (!$user->tokens['bearer_token'] || !$user->tokens['csrf_token']) {
                    continue;
                }

                try {
                    $params = [
                        'tweet_id'     => $tweetId,
                        'bearer_token' => $user->tokens['bearer_token'],
                        'csrf_token'   => $user->tokens['csrf_token'],
                        'cookie'       => collect($user->cookies)->map(fn ($v, $k) => "$k=$v")->join('; '),
                        'user_agent'   => $user->user_agent,
                    ];

                    $response = Http::timeout(15)->get($endpoint, $params);
                    $json     = $response->json();

                    if (($json['code'] ?? null) === 326) {
                        $user->update(['is_active' => false]);
                        Log::warning('[TweetVideoService] Locked Twitter account deactivated', [
                            'username' => $user->username,
                            'tweet_id' => $tweetId,
                        ]);
                        continue;
                    }

                    $data = $json['data'][0] ?? null;

                    if (
                        $response->successful() &&
                        ($json['success'] ?? false) &&
                        $data &&
                        filled($data['tweet_id']) &&
                        filled($data['author']['username'] ?? null)
                    ) {
                        $media = collect($data['media'] ?? [])->where('type', 'video')->values()->map(function ($m, $index) use ($data) {
                            $variants = collect($m['video'] ?? [])
                                ->filter(fn ($v) => filled($v['url']))
                                ->values();

                            if ($variants->isEmpty()) {
                                return null;
                            }

                            return [
                                'key'         => $this->encodeVideoKey($data['tweet_id'], $index),
                                'type'        => 'video',
                                'thumbnail'   => $m['preview_url'] ?? null,
                                'duration'    => $m['duration'] ?? null,
                                'duration_ms' => (int) ($m['duration_ms'] ?? 0),
                                'video'       => null,
                                'variants'    => $variants->map(fn ($v) => [
                                    'url'        => $v['url'] ?? null,
                                    'bitrate'    => $v['bitrate'] ?? null,
                                    'size'       => $v['size'] ?? null,
                                    'resolution' => $v['resolution'] ?? null,
                                    'type'       => $v['type'] ?? null,
                                ])->values(),
                            ];
                        })->filter()->values()->toArray();

                        if (empty($media)) {
                            return null;
                        }

                        $presented = $this->present([
                            'tweet_id' => $data['tweet_id'],
                            'tweet'    => $data['text'],
                            'username' => $data['author']['username'],
                            'media'    => $media,
                        ]);

                        Tweet::updateOrCreate(
                            ['tweet_id' => $data['tweet_id']],
                            [
                                'user_id'          => $data['author']['id'],
                                'username'         => $data['author']['username'],
                                'tweet'            => $data['text'],
                                'related_tweet_id' => $data['related_tweet_id'] ?? null,
                                'urls'             => $data['urls'] ?? [],
                                'media'            => $presented['media'],
                                'status'           => 'video',
                                'is_sensitive'     => $data['is_sensitive'] ?? false,
                            ],
                        );

                        return $presented;
                    }

                    usleep(random_int(100, 300) * 1000);
                } catch (\Throwable $e) {
                    Log::error('[TweetVideoService] API fetch error', [
                        'tweet_id' => $tweetId,
                        'account'  => $user->username,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            return null;
        } finally {
            optional($lock)->release();
        }
    }

    private function present(array $data): ?array
    {
        $media = collect($data['media'] ?? [])
            ->where('key')
            ->map(function ($m) {
                return [
                    'key'         => $m['key'],
                    'type'        => 'video',
                    'thumbnail'   => $m['thumbnail'] ?? null,
                    'duration'    => $m['duration'] ?? null,
                    'duration_ms' => (int) ($m['duration_ms'] ?? 0),
                    'video'       => null,
                    'variants'    => collect($m['variants'] ?? [])->map(fn ($v) => [
                        'url'        => $v['url'] ?? null,
                        'bitrate'    => $v['bitrate'] ?? null,
                        'size'       => $v['size'] ?? null,
                        'resolution' => $v['resolution'] ?? null,
                        'type'       => $v['type'] ?? null,
                    ])->filter(fn ($v) => filled($v['url']))->values(),
                ];
            })
            ->filter(fn ($m) => filled($m['key']) && $m['variants']->isNotEmpty())
            ->values();

        if ($media->isEmpty()) {
            return null;
        }

        return [
            'tweet_id' => $data['tweet_id'],
            'text'     => trim(preg_replace('/https?:\/\/\S+/', '', $data['tweet'] ?? '')),
            'username' => $data['username'] ?? null,
            'media'    => $media,
        ];
    }
}
