<?php

namespace App\Console\Commands;

use App\Models\Tweet;
use Illuminate\Console\Command;

class RepliesTweetsClean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitter:replies-clean {--days=7 : Delete replied tweets older than this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete replied tweets older than the given number of days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days   = max((int) $this->option('days', 7), 1);
        $cutoff = now()->subDays($days);

        $count = Tweet::where('status', 'replied')
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $this->info("✅ Deleted {$count} replied tweet(s) older than {$days} day(s).");
    }
}
