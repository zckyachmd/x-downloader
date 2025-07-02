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

    public function get(int $tweetId, bool $skipSignedRoute = false, bool $proxyPreviewImage = true): ?array
    {
        $cacheKey = "tweet:$tweetId";
        $mapped   = Cache::get($cacheKey);

        if (!$mapped) {
            $model = Tweet::where('tweet_id', $tweetId)
                ->where('status', 'video')
                ->first();

            $data = $model
                ? $model->only([
                    'tweet_id',
                    'user_id',
                    'username',
                    'tweet',
                    'related_tweet_id',
                    'urls',
                    'media',
                    'status',
                ])
                : $this->fetchFromAPI($tweetId);

            $mapped = $data ? $this->map($data) : null;

            if (!$mapped) {
                return null;
            }

            Cache::put($cacheKey, $mapped, now()->addMinutes(5));
        }

        $mapped['media'] = collect($mapped['media'])
            ->map(function ($variant) use ($skipSignedRoute, $proxyPreviewImage) {
                $videoKey = $variant['key'] ?? null;

                if ($proxyPreviewImage && $videoKey) {
                    $variant['preview'] = route('tweet.thumbnail', ['videoKey' => $videoKey]);
                }

                if (!$skipSignedRoute && !empty($variant['video']) && $videoKey) {
                    $durationSec  = ($variant['duration_ms'] ?? 0) / 1000;
                    $validMinutes = max(10, min(180, ceil($durationSec * 5 / 60)));

                    $variant['video']    = URL::temporarySignedRoute('tweet.preview', now()->addMinutes($validMinutes), ['videoKey' => $videoKey]);
                    $variant['variants'] = collect($variant['variants'] ?? [])
                        ->map(fn ($v) => Arr::except($v, ['url']))
                        ->values();
                }

                unset($variant['duration_ms']);

                return $variant;
            })
            ->values()
            ->all();

        return $mapped;
    }

    public function imageThumbnail(int $tweetId, int $index = 0): ?array
    {
        $tweet = $this->get($tweetId, skipSignedRoute: false, proxyPreviewImage: false);
        $url   = $tweet['media'][$index]['preview'] ?? null;
        if (!$url) {
            return null;
        }

        try {
            $client = new Client([
                'headers' => ['User-Agent' => $this->userAgent->random()],
                'stream'  => true,
                'timeout' => 0,
            ]);

            $response = $client->get($url, ['stream' => true]);

            return [
                'stream'       => $response->getBody(),
                'content_type' => $response->getHeaderLine('Content-Type') ?: 'image/jpeg',
            ];
        } catch (\Throwable $e) {
            Log::error('[TweetVideoService] Failed to stream thumbnail', [
                'tweet_id' => $tweetId,
                'index'    => $index,
                'url'      => $url,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function streamVideo(int $tweetId, int $index = 0, bool $isPreview = false, ?int $bitrate = null, ?string $rangeHeader = null): ?array
    {
        try {
            $tweet    = $this->get($tweetId, skipSignedRoute: true, proxyPreviewImage: $isPreview);
            $variants = $tweet['media'][$index]['variants'] ?? [];

            $url = $isPreview
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
                    !empty($data['tweet_id']) &&
                    ($data['author']['username'] ?? 'unknown') !== 'unknown' &&
                    collect($data['media'] ?? [])->firstWhere('type', 'video')
                ) {
                    $model = Tweet::updateOrCreate(
                        ['tweet_id' => $data['tweet_id']],
                        [
                            'user_id'          => $data['author']['id'],
                            'username'         => $data['author']['username'],
                            'tweet'            => $data['text'],
                            'related_tweet_id' => $data['related_tweet_id'] ?? null,
                            'urls'             => $data['urls'] ?? [],
                            'media'            => $data['media'] ?? [],
                            'status'           => 'video',
                        ],
                    );

                    return $model->only([
                        'tweet_id',
                        'user_id',
                        'username',
                        'tweet',
                        'related_tweet_id',
                        'urls',
                        'media',
                        'status',
                    ]);
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
    }

    private function map(array $data): ?array
    {
        $tweetId    = $data['tweet_id'] ?? null;
        $text       = $data['tweet'] ?? null;
        $username   = $data['username'] ?? null;
        $mediaItems = $data['media'] ?? [];

        $cleanText = trim(preg_replace('/https?:\/\/\S+/', '', $text));

        $media = collect($mediaItems)
            ->where('type', 'video')
            ->values()
            ->map(function ($m, $index) use ($tweetId) {
                $variants = collect($m['video'] ?? [])
                    ->filter(fn ($v) => isset($v['bitrate'], $v['url']))
                    ->values();

                if ($variants->isEmpty()) {
                    return null;
                }

                $lowest = $variants->sortBy('bitrate')->first();

                return [
                    'key'         => $this->encodeVideoKey($tweetId, $index),
                    'video'       => $lowest['url'] ?? null,
                    'preview'     => $m['preview_url'] ?? null,
                    'duration'    => $m['duration'] ?? null,
                    'duration_ms' => isset($m['duration_ms']) ? (int) $m['duration_ms'] : 0,
                    'variants'    => $variants->map(fn ($v) => [
                        'bitrate'    => $v['bitrate'] ?? null,
                        'size'       => $v['size'] ?? null,
                        'resolution' => $v['resolution'] ?? null,
                        'url'        => $v['url'] ?? null,
                    ])->values(),
                ];
            })
            ->filter()
            ->values();

        if ($media->isEmpty()) {
            return null;
        }

        return [
            'tweet_id' => $tweetId,
            'text'     => $cleanText,
            'author'   => [
                'username' => $username,
            ],
            'media' => $media,
        ];
    }
}
