<?php

namespace App\Providers;

use App\Contracts\PendingJobCheckerContract;
use App\Services\PendingJobChecker;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PendingJobCheckerContract::class, PendingJobChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production and staging
        if (
            $this->app->environment(['production', 'staging']) &&
            !$this->app->runningInConsole()
        ) {
            URL::forceScheme('https');
        }
    }
}
