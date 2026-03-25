<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Console\Kernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConsoleKernelContract::class, Kernel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
