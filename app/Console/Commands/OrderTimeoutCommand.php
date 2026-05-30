<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:order-timeout-command')]
#[Description('Command description')]
class OrderTimeoutCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
         // 业务逻辑
        echo "开始处理超时订单\n";

        return self::SUCCESS;
    }
}
