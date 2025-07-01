<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\UserTwitter;
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

    public function handle()
    {
        $username = $this->option('username');
        $password = $this->option('password');
        $isMain   = (bool) $this->option('main');

        if (!$username || !$password) {
            $this->error('--username and --password are required.');

            return self::FAILURE;
        }

        $endpointBase = Config::getValue('API_X_DOWNLOADER', 'http://localhost:3000');
        $endpoint     = rtrim($endpointBase, '/') . '/login';

        $this->info("🚀 Logging in as @$username...");

        try {
            $response = Http::timeout(30)->post($endpoint, [
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\Throwable $e) {
            $this->error("❌ Failed to call endpoint: " . $e->getMessage());
            Log::error("[AddTwitterAccount] HTTP Exception @$username: " . $e->getMessage());

            return self::FAILURE;
        }

        if (!$response->ok()) {
            $this->error("❌ Request failed: HTTP " . $response->status());
            Log::warning("[AddTwitterAccount] HTTP error @$username: {$response->status()}");

            return self::FAILURE;
        }

        $body = $response->json();

        if (!($body['success'] ?? false)) {
            $this->error("❌ Login failed: " . json_encode($body));
            Log::warning("[AddTwitterAccount] Login fail @$username: " . json_encode($body));

            $existing = UserTwitter::where('username', $username)->first();
            if ($existing && $existing->is_active) {
                $existing->update(['is_active' => false]);
                $this->warn("⚠️  Account @$username marked as inactive.");
            }

            return self::FAILURE;
        }

        $data        = $body['data'];
        $credentials = $data['credentials'];
        $token       = $data['token'];
        $cookie      = $data['cookie'];
        $userAgent   = $credentials['userAgent'] ?? null;

        $account    = UserTwitter::firstOrNew(['username' => $username]);
        $wasCreated = !$account->exists;

        $account->fill([
            'password'   => encrypt($password),
            'tokens'     => $token,
            'cookies'    => $cookie['parsed'],
            'user_agent' => $userAgent,
            'is_main'    => $isMain,
            'is_active'  => true,
        ])->save();

        $status = $wasCreated ? 'created' : 'updated';
        $this->info("✅ Account @$username has been {$status} and saved.");
        Log::info("[AddTwitterAccount] @$username {$status}.");

        return self::SUCCESS;
    }
}
