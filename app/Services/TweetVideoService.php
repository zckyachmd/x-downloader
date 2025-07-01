<?php

namespace App\Services;

use App\Contracts\TweetVideoServiceContract;
use App\Models\Config;
use App\Models\Tweet;
use App\Models\UserTwitter;
use App\Utils\UserAgent;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Psr\Http\Message\StreamInterface;

class TweetVideoService implements TweetVideoServiceContract
{
    public function get(int $tweetId, bool $skipSignedRoute = false, bool $proxyPreviewImage = true): ?array
    {
        $cacheKey = "tweet:$tweetId";
        $mapped   = Cache::get($cacheKey);

        if (!$mapped) {
            $model = Tweet::where('tweet_id', $tweetId)
                ->where('status', 'video')
                ->first();

            if (!$model) {
                return $this->fetchFromAPI($tweetId, $cacheKey);
            }

            $data = $model->only([
                'tweet_id',
                'user_id',
                'username',
                'tweet',
                'related_tweet_id',
                'urls',
                'media',
                'status',
            ]);

            $mapped = $this->map($data);

            Cache::put($cacheKey, $mapped, now()->addMinutes(5));
        }

        if (empty($mapped['media']['variants'])) {
            return null;
        }

        if ($proxyPreviewImage) {
            $mapped['preview']['image'] = route('tweet.thumbnail', ['tweetId' => $mapped['tweet_id']]);
        }

        if (!$skipSignedRoute && !empty($mapped['preview']['video'])) {
            $durationSec  = (float) rtrim($mapped['media']['duration'] ?? '0', ' sec');
            $validMinutes = min(180, ceil($durationSec * 5 / 60));

            $mapped['preview']['video'] = URL::temporarySignedRoute(
                'tweet.preview',
                now()->addMinutes($validMinutes),
                ['tweetId' => $mapped['tweet_id']],
            );
        }

        return $mapped;
    }

    public function imageThumbnail(int $tweetId): ?StreamInterface
    {
        $tweet = $this->get($tweetId, skipSignedRoute: true, proxyPreviewImage: false);

        $url = $tweet['preview']['image'] ?? null;

        if (!$url) {
            return null;
        }

        try {
            $client = new Client([
                'headers' => ['User-Agent' => UserAgent::random()],
                'stream'  => true,
                'timeout' => 10,
            ]);

            $response = $client->get($url, ['stream' => true]);

            return $response->getBody();
        } catch (\Throwable $e) {
            Log::error('[TweetVideoService] Stream image preview error', [
                'tweet_id' => $tweetId,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function streamVideo(int $tweetId, ?int $bitrate = null, bool $isPreview = false): ?StreamInterface
    {
        if ($isPreview) {
            $tweet = $this->get($tweetId, skipSignedRoute: true, proxyPreviewImage: true);
            $url   = $tweet['preview']['video'] ?? null;
        } else {
            if ($bitrate === null) {
                Log::warning('[TweetVideoService] Bitrate is required for non-preview stream', ['tweet_id' => $tweetId]);

                return null;
            }

            $tweet = Tweet::where('tweet_id', $tweetId)->where('status', 'video')->first();

            if (!$tweet || empty($tweet->media)) {
                return null;
            }

            $videos = collect($tweet->media)->firstWhere('type', 'video')['video'] ?? [];
            $match  = collect($videos)->firstWhere('bitrate', $bitrate);

            $url = $match['url'] ?? null;
        }

        if (!$url) {
            return null;
        }

        try {
            $client = new Client([
                'headers' => ['User-Agent' => UserAgent::random()],
                'stream'  => true,
                'timeout' => 0,
            ]);

            $response = $client->get($url, ['stream' => true]);

            return $response->getBody();
        } catch (\Throwable $e) {
            Log::error('[TweetVideoService] Stream error', [
                'tweet_id' => $tweetId,
                'bitrate'  => $bitrate,
                'preview'  => $isPreview,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchFromAPI(int $tweetId, string $cacheKey): ?array
    {
        $accounts = UserTwitter::where('is_active', true)
            ->where('is_main', false)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/tweet/detail';

        foreach ($accounts as $user) {
            try {
                $params = [
                    'tweet_id'     => $tweetId,
                    'bearer_token' => $user->tokens['bearer_token'] ?? '',
                    'csrf_token'   => $user->tokens['csrf_token'] ?? '',
                    'cookie'       => collect($user->cookies)->map(fn ($v, $k) => "$k=$v")->join('; '),
                    'user_agent'   => $user->user_agent,
                ];

                $response = Http::timeout(15)->get($endpoint, $params);
                $json     = $response->json();
                $data     = $json['data'][0] ?? null;

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

                    $tweet = $model->only([
                        'tweet_id',
                        'user_id',
                        'username',
                        'tweet',
                        'related_tweet_id',
                        'urls',
                        'media',
                        'status',
                    ]);

                    Cache::put($cacheKey, $tweet, now()->addMinutes(5));

                    return $this->map($tweet);
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

    private function map(array $data): array
    {
        $tweetId    = $data['tweet_id'] ?? null;
        $text       = $data['tweet'] ?? null;
        $username   = $data['username'] ?? null;
        $mediaItems = $data['media'] ?? [];

        $cleanText = trim(preg_replace('/https?:\/\/\S+/', '', $text));

        $videoMedia = collect($mediaItems)->firstWhere('type', 'video');
        $videoList  = $videoMedia['video'] ?? [];

        $best = collect($videoList)
            ->filter(fn ($v) => isset($v['bitrate'], $v['url']))
            ->sortByDesc('bitrate')
            ->first();

        return [
            'tweet_id' => $tweetId,
            'text'     => $cleanText,
            'preview'  => [
                'image' => $videoMedia['preview_url'] ?? null,
                'video' => $best['url'] ?? null,
            ],
            'media' => [
                'duration' => $videoMedia['duration'] ?? null,
                'variants' => collect($videoList)
                    ->map(fn ($v) => [
                        'bitrate'    => $v['bitrate'] ?? null,
                        'size'       => $v['size'] ?? null,
                        'resolution' => $v['resolution'] ?? null,
                    ])
                    ->values(),
            ],
            'author' => [
                'username' => $username,
            ],
        ];
    }
}
