<?php

namespace App\Providers;

use App\Console\Kernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
		//加载自动定时任务
        $this->app->singleton(ConsoleKernelContract::class, Kernel::class);
    }

    public function boot(): void
    {
        //
    }
}
