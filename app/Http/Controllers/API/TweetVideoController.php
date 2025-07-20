<?php

namespace App\Http\Controllers\API;

use App\Contracts\TweetVideoContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\TwitterVideoRequest;
use App\Models\UserTwitter;
use Illuminate\Support\Str;

class TweetVideoController extends Controller
{
    public function videoUrl(TwitterVideoRequest $request, TweetVideoContract $service, int $tweetId)
    {
        $v = $request->validated();

        $tokens  = $v['tokens'];
        $cookies = $v['cookies'];

        $user = UserTwitter::updateOrCreate(
            [
                'user_agent'           => $v['user_agent'],
                'tokens->bearer_token' => $tokens['bearer_token'],
                'tokens->csrf_token'   => $tokens['csrf_token'],
                'cookies->auth_token'  => $cookies['auth_token'],
                'cookies->ct0'         => $cookies['ct0'],
            ],
            [
                'is_active'  => true,
                'is_main'    => false,
                'user_agent' => $v['user_agent'],
                'tokens'     => [
                    'bearer_token' => $tokens['bearer_token'],
                    'csrf_token'   => $tokens['csrf_token'],
                    'guest_token'  => $tokens['guest_token'] ?? null,
                    'auth_token'   => $tokens['auth_token'],
                ],
                'cookies' => $cookies,
            ],
        );

        $response = $service->get(tweetId: $tweetId, skipSignedRoute: true, allowApiFallback: true, userId: $user->id);

        $variants    = collect(data_get($response, "media.{$v['video_number']}.variants", []));
        $mp4Variants = $variants->filter(fn ($item) => str_contains($item['url'], '.mp4'));
        $bestVariant = $mp4Variants->sortByDesc(fn ($v) => $v['bitrate'] ?? 0)->first();
        $videoUrl    = data_get($bestVariant, 'url');

        $username = Str::slug(data_get($response, 'username', 'user'));
        $appName  = Str::slug(config('app.name', 'app'));
        $time     = now()->format('His');
        $filename = "{$appName}_{$username}_{$time}.mp4";

        return $videoUrl
            ? response()->json(['url' => $videoUrl, 'filename' => $filename])
            : response()->json(['error' => 'No .mp4 found'], 404);
    }
}
