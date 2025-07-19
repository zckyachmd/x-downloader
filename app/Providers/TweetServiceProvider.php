<?php

namespace App\Providers;

use App\Contracts\TrendingVideoContract;
use App\Contracts\TweetVideoContract;
use App\Services\TrendingVideo;
use App\Services\TweetVideo;
use Illuminate\Support\ServiceProvider;

class TweetServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(TweetVideoContract::class, TweetVideo::class);
        $this->app->bind(TrendingVideoContract::class, TrendingVideo::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
