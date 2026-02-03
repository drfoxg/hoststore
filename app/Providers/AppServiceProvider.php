<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiter для rename операций: 10 запросов в минуту на хост
        RateLimiter::for('host-rename', function (Request $request) {
            $hostId = $request->route('host')?->id ?? $request->route('host');

            return Limit::perMinute(10)
                ->by($hostId . '|' . ($request->user()?->id ?? $request->ip()))
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many rename requests. Please try again later.',
                    ], 429);
                });
        });
    }
}
