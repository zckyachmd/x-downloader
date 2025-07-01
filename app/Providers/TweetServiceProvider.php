<?php

namespace App\Providers;

use App\Contracts\TweetVideoServiceContract;
use App\Services\TweetVideoService;
use Illuminate\Support\ServiceProvider;

class TweetServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(TweetVideoServiceContract::class, TweetVideoService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
