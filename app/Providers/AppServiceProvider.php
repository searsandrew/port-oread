<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Game\Contracts\GameDriver;
use App\Game\Drivers\LocalSkirmishDriver;

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
        //
    }
}
