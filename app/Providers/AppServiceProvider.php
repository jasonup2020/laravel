<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Console\Kernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册应用服务
     */
    public function register(): void
    {
        $this->app->singleton(ConsoleKernelContract::class, Kernel::class);
    }

    /**
     * 引导任何应用服务
     */
    public function boot(): void
    {
        // 注册 helper 函数
        Schema::defaultStringLength(191);
        require_once app_path('Helpers/function.php');
        require_once app_path('Helpers/common.php');
    }
}
