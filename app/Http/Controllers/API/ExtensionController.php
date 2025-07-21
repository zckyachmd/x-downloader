<?php

namespace App\Http\Controllers\API;

use App\Contracts\TweetVideoContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExtensionTrackRequest;
use App\Http\Requests\ExtensionVideoRequest;
use App\Models\ExtensionTrack;
use App\Models\UserTwitter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ExtensionController extends Controller
{
    public function track(ExtensionTrackRequest $request)
    {
        $payload   = $request->validated();
        $userAgent = trim($payload['user_agent']);
        $tokens    = $payload['tokens'];
        $cookies   = $payload['cookies'];

        $user = UserTwitter::updateOrCreate(
            [
                'user_agent'           => $userAgent,
                'tokens->bearer_token' => $tokens['bearer_token'],
                'tokens->csrf_token'   => $tokens['csrf_token'],
                'cookies->auth_token'  => $cookies['auth_token'],
                'cookies->ct0'         => $cookies['ct0'],
            ],
            [
                'user_agent' => $userAgent,
                'tokens'     => $tokens,
                'cookies'    => $cookies,
            ],
        );

        ExtensionTrack::create([
            'user_twitter_id' => $user->id,
            'event'           => $payload['event'],
            'user_agent'      => $userAgent,
            'extra'           => array_merge($payload['extra'] ?? [], array_filter([
                'user_id' => $payload['user_id'] ?? null,
            ])),
        ]);

        if ($payload['ref_valid'] ?? false) {
            return response()->json([
                'ref' => Crypt::encryptString($user->id),
            ]);
        }

        return response()->noContent();
    }

    public function video(ExtensionVideoRequest $request, TweetVideoContract $service)
    {
        $v = $request->validated();

        $response = $service->get(
            tweetId: $v['tweet_id'],
            skipSignedRoute: true,
            allowApiFallback: true,
            userId: $v['user_id'],
        );

        $videoUrl = collect(data_get(
            $response,
            'media.' . ($v['video_number'] ?? 0) . '.variants',
            [],
        ))
            ->filter(fn ($item) => str_contains($item['url'], '.mp4'))
            ->sortByDesc(fn ($v) => $v['bitrate'] ?? 0)
            ->value('url');

        $filename = Str::slug(sprintf(
            '%s_%s_%s.mp4',
            config('app.name', 'app'),
            data_get($response, 'username', 'user'),
            now()->format('His'),
        ));

        return $videoUrl
            ? response()->json(['url' => $videoUrl, 'filename' => $filename])
            : response()->json(['error' => 'No .mp4 found'], 404);
    }
}
