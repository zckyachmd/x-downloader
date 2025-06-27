<?php

namespace App\Console\Commands;

use App\Models\UserTwitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RefreshInactiveTwitterAccounts extends Command
{
    protected $signature = 'twitter:refresh-inactive {--limit= : Limit number of accounts to refresh}';
    protected $description = 'Refresh token, cookie, and user agent for inactive Twitter accounts';

    protected string $endpoint;

    public function __construct()
    {
        parent::__construct();
        $this->endpoint = rtrim(env('TWITTER_LOGIN_ENDPOINT', 'http://localhost:3000'), '/') . '/login';
    }

    public function handle()
    {
        $accounts = UserTwitter::where('is_active', false)->get();
        $limit = (int) $this->option('limit', 3);

        if ($limit > 0) {
            $accounts = $accounts->take($limit);
        }

        if ($accounts->isEmpty()) {
            $this->info("✅ No inactive accounts found.");
            return self::SUCCESS;
        }

        $total = $accounts->count();
        $success = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            $username = $account->username;
            $cacheKey = "failed_login_{$username}";

            if (Cache::get($cacheKey, 0) >= 3) {
                $this->warn("🚫 Skipping @$username (3x failed attempts)");
                continue;
            }

            $this->line("🔁 Refreshing @$username...");

            try {
                $password = decrypt($account->password);
            } catch (\Throwable $e) {
                $this->error("❌ Failed to decrypt password for @$username");
                Log::warning("[RefreshInactiveTwitter] Decrypt error @$username: {$e->getMessage()}");
                $this->markFail($cacheKey);
                $failed++;
                continue;
            }

            $response = Http::timeout(30)->post($this->endpoint, [
                'identifier' => $username,
                'password'   => $password,
            ]);

            if (!$response->ok()) {
                $this->warn("❌ HTTP error for @$username: " . $response->status());
                Log::warning("[RefreshInactiveTwitter] HTTP error @$username: {$response->status()}");
                $this->markFail($cacheKey);
                $failed++;
                continue;
            }

            $body = $response->json();

            if (!($body['success'] ?? false) || !isset($body['data']['token'], $body['data']['cookie']['parsed'])) {
                $this->warn("❌ Login failed or bad response for @$username");
                Log::warning("[RefreshInactiveTwitter] Login fail @$username: " . json_encode($body));
                $this->markFail($cacheKey);
                $failed++;
                continue;
            }

            $data = $body['data'];

            $account->update([
                'tokens'     => $data['token'],
                'cookies'    => $data['cookie']['parsed'],
                'user_agent' => $data['credentials']['userAgent'],
                'is_active'  => true,
            ]);

            Cache::forget($cacheKey);
            $this->info("✅ @$username reactivated.");
            Log::info("[RefreshInactiveTwitter] Account reactivated @$username");
            $success++;

            sleep(rand(1, 2));
        }

        $this->line("📊 Done. Total: $total | ✅ Success: $success | ❌ Failed: $failed");
        return self::SUCCESS;
    }

    protected function markFail(string $cacheKey): void
    {
        $count = Cache::increment($cacheKey);
        Cache::put($cacheKey, $count, now()->addHours(6));
    }
}
