<?php

namespace App\Providers;

use App\Game\Contracts\GameDriver;
use App\Game\Drivers\LocalSkirmishDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Game engine driver binding (offline/local driver)
        $this->app->bind(GameDriver::class, LocalSkirmishDriver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login throttle limiter (email + IP)
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }
}
