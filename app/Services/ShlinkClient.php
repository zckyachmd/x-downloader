<?php

namespace App\Services;

use App\Contracts\ShlinkClientContract;
use App\Traits\HasConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShlinkClient implements ShlinkClientContract
{
    use HasConfig;

    public function shorten(string $longUrl): string
    {
        $enabled = $this->getConfig('SHLINK_ENABLED', false);
        $apiUrl  = $this->getConfig('SHLINK_API_URL');
        $apiKey  = $this->getConfig('SHLINK_API_KEY');

        if (!$enabled || empty($apiUrl) || empty($apiKey)) {
            return $longUrl;
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 1000)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                ])
                ->post("{$apiUrl}/rest/v3/short-urls", [
                    'longUrl' => $longUrl,
                    'tags'    => ['app:' . config('app.name', 'x-downloader')],
                ]);

            $shortUrl = $response->json('shortUrl');

            if (!$shortUrl) {
                Log::warning('[ShlinkClient] shortUrl key missing in response', [
                    'response' => $response->json(),
                ]);

                return $longUrl;
            }

            return $shortUrl;
        } catch (\Throwable $e) {
            Log::error('[ShlinkClient] Exception while shortening URL', [
                'long_url' => $longUrl,
                'error'    => $e->getMessage(),
            ]);

            return $longUrl;
        }
    }
}
