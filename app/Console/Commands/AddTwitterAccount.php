<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\UserTwitter;
use App\Utils\UserAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AddTwitterAccount extends Command
{
    protected $signature = 'twitter:add
                            {--username= : Twitter username (required)}
                            {--password= : Password (required)}
                            {--main : Set as main account (optional)}';

    protected $description = 'Tambah akun Twitter ke tabel user_twitters';

    protected $userAgent;

    public function __construct(UserAgent $userAgent)
    {
        parent::__construct();

        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        $username = $this->option('username');
        $password = $this->option('password');
        $isMain   = (bool) $this->option('main');

        if (!$username || !$password) {
            $this->error('--username and --password are required.');

            return self::FAILURE;
        }

        $userAgentUsed = $this->userAgent->random();

        $existing = UserTwitter::where('username', $username)
            ->where('user_agent', $userAgentUsed)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            $this->info("✅ Skipped: Account @$username with fingerprint already exists and is active.");

            return self::SUCCESS;
        }

        $endpointBase = Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000');
        $endpoint     = rtrim($endpointBase, '/') . '/login';

        $this->info("🚀 Logging in as @$username...");
        $this->line("🧠 Using User-Agent: {$userAgentUsed}");

        try {
            $response = Http::timeout(30)->post($endpoint, [
                'username'   => $username,
                'password'   => $password,
                'user_agent' => $userAgentUsed,
            ]);
        } catch (\Throwable $e) {
            $this->error("❌ Failed to call endpoint: " . $e->getMessage());
            Log::error("[AddTwitterAccount] HTTP Exception @$username: " . $e->getMessage());

            return self::FAILURE;
        }

        if (!$response->ok()) {
            $this->error("❌ Request failed: HTTP " . $response->status());
            Log::warning("[AddTwitterAccount] HTTP error @$username: {$response->status()}", [
                'response' => $response->json(),
            ]);

            return self::FAILURE;
        }

        $body = $response->json();

        if (!($body['success'] ?? false)) {
            $this->error("❌ Login failed: " . json_encode($body));
            Log::warning("[AddTwitterAccount] Login fail @$username", [
                'response' => $body,
            ]);

            $fallback = UserTwitter::where('username', $username)->first();
            if ($fallback && $fallback->is_active) {
                $fallback->update(['is_active' => false]);
                $this->warn("⚠️  Account @$username marked as inactive.");
            }

            return self::FAILURE;
        }

        $data        = $body['data'] ?? [];
        $credentials = $data['credentials'] ?? [];
        $token       = $data['token'] ?? null;
        $cookie      = $data['cookie']['parsed'] ?? null;
        $userAgent   = $credentials['userAgent'] ?? $userAgentUsed;

        if (!$token || !$cookie) {
            $this->error("❌ Missing required token/cookie.");
            Log::error("[AddTwitterAccount] Missing credentials @$username", compact('body'));

            return self::FAILURE;
        }

        $userAgent = trim($userAgent) ?: 'Unknown';

        $account = UserTwitter::firstOrNew([
            'username'   => $username,
            'user_agent' => $userAgent,
        ]);
        $wasCreated = !$account->exists;

        if (!$wasCreated) {
            $this->warn("⚠️  Existing record detected. Updating info...");
        }

        $account->fill([
            'password'   => encrypt($password),
            'tokens'     => $token,
            'cookies'    => $cookie,
            'user_agent' => $userAgent,
            'is_main'    => $isMain,
            'is_active'  => true,
        ])->save();

        $status = $wasCreated ? 'created' : 'updated';
        $this->info("✅ Account @$username has been {$status} and saved.");
        Log::info("[AddTwitterAccount] @$username {$status}.", [
            'user_agent' => $userAgent,
            'main'       => $isMain,
        ]);

        return self::SUCCESS;
    }
}
