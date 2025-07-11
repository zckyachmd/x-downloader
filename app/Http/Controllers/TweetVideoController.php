<?php

namespace App\Http\Controllers;

use App\Contracts\TweetVideoServiceContract;
use App\Http\Requests\TweetSearchRequest;
use App\Models\VideoDownload;
use App\Traits\EncodesVideoKey;
use App\Utils\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TweetVideoController extends Controller
{
    use EncodesVideoKey;

    protected $tweetVideoService;
    protected $userAgent;

    public function __construct(TweetVideoServiceContract $tweetVideoService, UserAgent $userAgent)
    {
        $this->tweetVideoService = $tweetVideoService;
        $this->userAgent         = $userAgent;
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

    public function thumbnail(string $key): StreamedResponse
    {
        $resolved = $this->decodeVideoKey($key);

        if (!$resolved && is_numeric($key)) {
            $resolved = [
                'tweet_id' => (int) $key,
                'index'    => 0,
            ];
        }

        if (!$resolved) {
            return $this->fallbackThumbnailResponse();
        }

        $tweetId = $resolved['tweet_id'];
        $index   = $resolved['index'];

        $result = $this->tweetVideoService->imageThumbnail($tweetId, $index);
        $url    = $result['stream'] ?? null;

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->fallbackThumbnailResponse();
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent->random(),
            ])->timeout(10)->withOptions(['stream' => true])->get($url);

            if (!$response->ok()) {
                throw new \Exception('Remote thumbnail not ok');
            }

            $psr    = $response->toPsrResponse();
            $body   = $psr->getBody();
            $length = $psr->getHeaderLine('Content-Length');
            $type   = $psr->getHeaderLine('Content-Type') ?: 'image/jpeg';

            return response()->stream(function () use ($body) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
                if (function_exists('ob_implicit_flush')) {
                    ob_implicit_flush(true);
                }

                while (!$body->eof()) {
                    echo $body->read(65536);
                    flush();
                }
            }, 200, array_filter([
                'Content-Type'           => $type,
                'Content-Length'         => $length ?: null,
                'Cache-Control'          => 'public, max-age=86400',
                'Accept-Ranges'          => 'bytes',
                'Connection'             => 'keep-alive',
                'X-Content-Type-Options' => 'nosniff',
            ]));
        } catch (\Throwable $e) {
            return $this->fallbackThumbnailResponse();
        }
    }

    protected function fallbackThumbnailResponse(): StreamedResponse
    {
        $path = public_path('assets/img/favicon.png');

        abort_unless(file_exists($path), 404);
        $length = filesize($path);

        return response()->stream(function () use ($path) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            if (function_exists('ob_implicit_flush')) {
                ob_implicit_flush(true);
            }

            $stream = fopen($path, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 65536);
                flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type'           => 'image/jpeg',
            'Content-Length'         => $length,
            'Cache-Control'          => 'public, max-age=86400',
            'Accept-Ranges'          => 'bytes',
            'Connection'             => 'keep-alive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, string $videoKey): StreamedResponse
    {
        $resolved = $this->decodeVideoKey($videoKey);
        if (!$resolved) {
            abort(404);
        }

        $data = $this->tweetVideoService->streamVideo(
            $resolved['tweet_id'],
            $resolved['index'],
            true,
            null,
            $request->header('Range'),
        );
        if (!$data) {
            abort(404);
        }

        return $this->buildStreamedResponse($data, inline: true);
    }

    public function download(Request $request, string $videoKey)
    {
        $bitrate = $request->query('bitrate');
        if (!is_null($bitrate) && (!is_numeric($bitrate) || (int) $bitrate < 0)) {
            return response()->json(['message' => 'That video seems to be missing or unavailable.'], 422);
        }

        $resolved = $this->decodeVideoKey($videoKey);
        if (!$resolved) {
            return response()->json(['message' => 'Invalid or expired video key.'], 404);
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

        if ($request->header('X-Check-Only') === '1') {
            return response()->noContent();
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

        return $this->buildStreamedResponse($data, inline: false, filename: $filename);
    }

    protected function buildStreamedResponse(array $data, bool $inline = true, string $filename = null): StreamedResponse
    {
        $length = $data['end'] - $data['start'] + 1;

        $disposition = $inline
            ? 'inline'
            : 'attachment; filename="' . ($filename ?? 'video.mp4') . '"';

        return new StreamedResponse(function () use ($data) {
            while (!$data['stream']->eof()) {
                echo $data['stream']->read(65536);
                flush();
            }
        }, $data['status'], [
            'Content-Type'           => $data['content_type'],
            'Content-Disposition'    => $disposition,
            'Accept-Ranges'          => 'bytes',
            'Content-Length'         => $length,
            'Content-Range'          => "bytes {$data['start']}-{$data['end']}/{$data['total_length']}",
            'Cache-Control'          => 'no-store, no-cache, must-revalidate',
            'Pragma'                 => $inline ? 'no-cache' : 'public',
            'Expires'                => '0',
            'Connection'             => 'keep-alive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
