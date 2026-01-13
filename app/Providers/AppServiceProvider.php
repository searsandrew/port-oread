<?php

namespace App\Providers;

use App\Game\Contracts\GameDriver;
use App\Game\Drivers\LocalSkirmishDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GameDriver::class, LocalSkirmishDriver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local') && request()->secure()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
