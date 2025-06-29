<?php

namespace App\Console\Commands;

use App\Jobs\RefreshTwitterAccountJob;
use App\Models\UserTwitter;
use Illuminate\Console\Command;

class RefreshInactiveTwitterAccounts extends Command
{
    protected $signature   = 'twitter:refresh-inactive {--limit=3}';
    protected $description = 'Dispatch jobs to refresh inactive Twitter accounts';

    public function handle()
    {
        $limit = (int) $this->option('limit', 3);

        $accounts = UserTwitter::select(['username'])
            ->where('is_active', false)
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();

        if ($accounts->isEmpty()) {
            $this->info("✅ No inactive accounts found.");

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            RefreshTwitterAccountJob::dispatch($account->username)
                ->onQueue('login-refresh');
            $this->line("📤 Dispatched refresh job for @$account->username");
        }

        $this->info("🚀 Dispatched {$accounts->count()} refresh jobs.");

        return self::SUCCESS;
    }
}
