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
        $apiUrl  = $this->getConfig('SHLINK_API_URL', '');
        $apiKey  = $this->getConfig('SHLINK_API_KEY', '');

        if (!$enabled || empty($apiUrl) || empty($apiKey)) {
            return $longUrl;
        }

        try {
            $response = Http::timeout(3)
                ->retry(1, 500)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                ])
                ->post("{$apiUrl}/rest/v3/short-urls", [
                    'longUrl' => $longUrl,
                    'tags'    => ['app:' . config('app.name')],
                ]);

            if (!$response->successful()) {
                Log::warning('[ShlinkClient] Failed to shorten URL', [
                    'long_url' => $longUrl,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);

                return $longUrl;
            }

            return $response->json('shortUrl', $longUrl);
        } catch (\Throwable $e) {
            Log::error('[ShlinkClient] Exception while shortening URL', [
                'long_url' => $longUrl,
                'error'    => $e->getMessage(),
            ]);

            return $longUrl;
        }
    }
}
