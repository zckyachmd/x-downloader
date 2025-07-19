<?php

namespace App\Providers;

use App\Contracts\ShlinkClientContract;
use App\Models\Config;
use App\Services\ShlinkClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ShlinkClientContract::class, ShlinkClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS
        if ($this->app->environment(['production', 'staging']) && !$this->app->runningInConsole()) {
            URL::forceScheme('https');
        }

        // Stealth Ads
        View::composer('*', function ($view) {
            $enabled = filter_var(Config::getValue('STEALTH_ADS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

            $urlsRaw = Config::getValue('STEALTH_ADS_URLS', []);
            $urls    = is_string($urlsRaw) ? json_decode($urlsRaw, true) : (array) $urlsRaw;
            $urls    = array_values(array_filter(
                $urls,
                fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL),
            ));

            $excludedRaw   = Config::getValue('STEALTH_ADS_EXCLUDED_URLS', '[]');
            $excludedPaths = is_string($excludedRaw) ? json_decode($excludedRaw, true) : (array) $excludedRaw;
            $excludedPaths = array_values(array_filter(
                $excludedPaths,
                fn ($path) => is_string($path),
            ));

            $currentPath = '/' . ltrim(parse_url(request()->getRequestUri(), PHP_URL_PATH), '/');
            $isExcluded  = in_array(rtrim($currentPath, '/'), array_map(fn ($p) => rtrim('/' . ltrim($p, '/'), '/'), $excludedPaths), true);

            $view->with([
                'stealthAdsEnabled'  => $enabled && !$isExcluded,
                'stealthAdsUrls'     => $urls,
                'stealthAdsExcluded' => $excludedPaths,
            ]);
        });
    }
}
