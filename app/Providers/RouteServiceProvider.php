<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        RateLimiter::for('tweet-search', function (Request $request) {
            $key = sha1($request->ip() . '|' . $request->header('User-Agent', 'unknown'));

            return Limit::perMinute(10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message'     => 'Too many searches. Chill out. Try again later.',
                        'retry_after' => $headers['Retry-After'] ?? null,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('tweet-video', function (Request $request) {
            $key = sha1($request->ip() . '|' . $request->header('User-Agent', 'unknown'));

            return Limit::perMinute(10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message'     => 'Too many requests. Chill out. Try again later.',
                        'retry_after' => $headers['Retry-After'] ?? null,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('tweet-download', function (Request $request) {
            $key = sha1($request->ip() . '|' . $request->header('User-Agent', 'unknown'));

            return Limit::perMinute(15)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response('Download limit reached. Wait a moment.', 429, $headers);
                });
        });

        RateLimiter::for('tweet-redirect', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response('Too many requests. Wait a moment.', 429, $headers);
                });
        });
    }
}
