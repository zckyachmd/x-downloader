<?php

namespace App\Jobs;

use App\Models\Config;
use App\Models\UserTwitter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshTwitterAccountJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    protected string $username;

    public function __construct(string $username)
    {
        $this->username = $username;
        $this->tries    = (int) env('QUEUE_TRIES', 1);
    }

    public function handle(): void
    {
        $account = UserTwitter::where('username', $this->username)->first();
        if (!$account) {
            Log::warning("[RefreshTwitterAccountJob] Account not found @$this->username");

            return;
        }

        $cacheKey = "failed_login_{$this->username}";
        if (Cache::get($cacheKey, 0) >= 3) {
            Log::notice("🚫 Skipping @$this->username (3x failed attempts)");

            return;
        }

        try {
            $endpoint = rtrim(Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000'), '/') . '/login';
            $password = decrypt($account->password);

            $response = Http::timeout(30)->post($endpoint, [
                'username' => $this->username,
                'password' => $password,
            ]);

            if (!$response->ok()) {
                Log::warning("[RefreshTwitterAccountJob] HTTP {$response->status()} @$this->username", [
                    'body' => $response->body(),
                ]);
                $this->markFail($cacheKey);

                return;
            }

            $body   = $response->json();
            $token  = data_get($body, 'data.token');
            $cookie = data_get($body, 'data.cookie.parsed');
            $agent  = data_get($body, 'data.credentials.userAgent');

            if (!($body['success'] ?? false) || !$token || !$cookie || !$agent) {
                Log::warning("[RefreshTwitterAccountJob] Invalid response @$this->username", [
                    'body' => $body,
                ]);
                $this->markFail($cacheKey);

                return;
            }

            $account->update([
                'tokens'     => $token,
                'cookies'    => $cookie,
                'user_agent' => $agent,
                'is_active'  => true,
            ]);

            Cache::forget($cacheKey);
            Log::info("[RefreshTwitterAccountJob] ✅ Reactivated @$this->username");
        } catch (\Throwable $e) {
            Log::error("[RefreshTwitterAccountJob] ❌ Exception @$this->username", [
                'error' => $e->getMessage(),
            ]);
            $this->markFail($cacheKey);
        }
    }

    protected function markFail(string $cacheKey): void
    {
        $count = Cache::increment($cacheKey);
        Cache::put($cacheKey, $count, now()->addHours(6));
    }

    public function tags(): array
    {
        return ['refresh-twitter', "user:{$this->username}"];
    }
}
