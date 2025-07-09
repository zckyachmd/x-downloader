<?php

namespace App\Http\Controllers;

use App\Contracts\TweetVideoServiceContract;
use App\Http\Requests\TweetSearchRequest;
use App\Models\VideoDownload;
use App\Traits\EncodesVideoKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TweetVideoController extends Controller
{
    use EncodesVideoKey;

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

        $data = $this->tweetVideoService->get(tweetId: $tweetId, allowApiFallback: true);

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

    public function thumbnail(string $key): Response
    {
        $resolved = $this->decodeVideoKey($key);

        if (!$resolved && is_numeric($key)) {
            $resolved = [
                'tweet_id' => (int) $key,
                'index'    => 0,
            ];
        }

        if (!$resolved) {
            abort(404);
        }

        $result = $this->tweetVideoService->imageThumbnail($resolved['tweet_id'], $resolved['index']);

        if (!$result || empty($result['stream'])) {
            abort(404);
        }

        $body = $result['stream'];
        $type = str_starts_with($result['content_type'], 'image/')
            ? $result['content_type']
            : 'image/jpeg';

        return response($body, 200, [
            'Content-Type'   => $type,
            'Content-Length' => strlen($body),
            'Cache-Control'  => 'public, max-age=86400',
            'Accept-Ranges'  => 'bytes',
        ]);
    }

    public function preview(Request $request, string $videoKey)
    {
        $resolved = $this->decodeVideoKey($videoKey);
        if (!$resolved) {
            abort(404);
        }

        $data = $this->tweetVideoService->streamVideo($resolved['tweet_id'], $resolved['index'], true, null, $request->header('Range'));
        if (!$data) {
            abort(404);
        }

        $length = $data['end'] - $data['start'] + 1;

        return new StreamedResponse(function () use ($data) {
            while (!$data['stream']->eof()) {
                echo $data['stream']->read(65536);
                flush();
            }
        }, $data['status'], [
            'Content-Type'           => $data['content_type'],
            'Content-Disposition'    => 'inline',
            'Accept-Ranges'          => 'bytes',
            'Content-Length'         => $length,
            'Content-Range'          => "bytes {$data['start']}-{$data['end']}/{$data['total_length']}",
            'Cache-Control'          => 'no-store, no-cache, must-revalidate',
            'Pragma'                 => 'no-cache',
            'Expires'                => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Request $request, string $videoKey)
    {
        $bitrate = $request->query('bitrate');
        if (!is_null($bitrate) && (!is_numeric($bitrate) || (int) $bitrate < 0)) {
            return response()->json(['message' => 'That video seems to be missing or unavailable.'], 422);
        }

        $resolved = $this->decodeVideoKey($videoKey);
        if (!$resolved) {
            abort(404);
        }

        $data = $this->tweetVideoService->streamVideo(
            $resolved['tweet_id'],
            $resolved['index'],
            false,
            (int) $bitrate,
            $request->header('Range'),
        );

        if (!$data) {
            return response()->json(['message' => 'This video is no longer available.'], 410);
        }

        VideoDownload::updateOrCreate(
            [
                'tweet_id'    => $resolved['tweet_id'],
                'video_index' => $resolved['index'],
            ],
            [
                'last_downloaded_at' => now(),
            ],
        )->increment('total_count');

        $filename = config('app.name') . '_' . Str::random(6) . '.mp4';
        $length   = $data['end'] - $data['start'] + 1;

        return new StreamedResponse(function () use ($data) {
            while (!$data['stream']->eof()) {
                echo $data['stream']->read(65536);
                flush();
            }
        }, $data['status'], [
            'Content-Type'        => $data['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Accept-Ranges'       => 'bytes',
            'Content-Length'      => $length,
            'Content-Range'       => "bytes {$data['start']}-{$data['end']}/{$data['total_length']}",
            'Cache-Control'       => 'no-store',
            'Connection'          => 'keep-alive',
            'Pragma'              => 'public',
            'Expires'             => '0',
        ]);
    }
}
