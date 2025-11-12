<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

use App\Services\MetricsContract;
use App\Services\Metrics;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsContract::class, Metrics::class);
    }

    public function boot(): void
    {
        // Crear enlaces (ya estaba)
        RateLimiter::for('create-links', function (Request $request) {
            $id = app()->bound('api.token') ? app('api.token')->id : $request->ip();
            $perMin = (int) config('shortener.rate_limits.create_per_min', 30);
            return Limit::perMinute($perMin)->by($id);
        });

        // Resolver enlaces (por IP)
        RateLimiter::for('resolve', function (Request $request) {
            $perMin = (int) config('shortener.rate_limits.resolve_per_min', 120);
            return Limit::perMinute($perMin)->by($request->ip());
        });
    }
}
