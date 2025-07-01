<?php

namespace App\Http\Controllers;

use App\Contracts\TweetVideoServiceContract;
use App\Http\Requests\TweetSearchRequest;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TweetVideoController extends Controller
{
    protected $tweetVideoService;

    public function __construct(TweetVideoServiceContract $tweetVideoService)
    {
        $this->tweetVideoService = $tweetVideoService;
    }

    public function search(TweetSearchRequest $request)
    {
        $tweetId = $request->tweetId();

        if (!$tweetId) {
            return response()->json([
                'message' => 'Invalid tweet URL, please try again.',
            ], 422);
        }

        $data = $this->tweetVideoService->get($tweetId);

        if (!$data) {
            return response()->json([
                'message' => 'Tweet could not be retrieved. It may be private, deleted, or not a video.',
            ], 502);
        }

        return response()->json([
            'message' => 'Successfully retrieved tweet details.',
            'data'    => $data,
        ]);
    }

    public function thumbnail(int $tweetId)
    {
        $stream = $this->tweetVideoService->imageThumbnail($tweetId);

        if (!$stream) {
            abort(404);
        }

        return response()->stream(
            fn () => fpassthru($stream->detach()),
            200,
            [
                'Content-Type'  => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400',
            ],
        );
    }

    public function preview(int $tweetId)
    {
        $stream = $this->tweetVideoService->streamVideo($tweetId, null, true);

        if (!$stream) {
            abort(404);
        }

        return new StreamedResponse(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type'        => 'video/mp4',
            'Content-Disposition' => 'inline; filename="preview.mp4"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function download(int $tweetId, int $bitrate)
    {
        $stream = $this->tweetVideoService->streamVideo($tweetId, $bitrate, false);

        if (!$stream) {
            return response()->json(['message' => 'Unable to download video.'], 410);
        }

        $filename = Str::slug(config('app.name')) . '_' . Str::random(6) . '.mp4';

        return new StreamedResponse(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type'        => 'video/mp4',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache',
        ]);
    }
}
