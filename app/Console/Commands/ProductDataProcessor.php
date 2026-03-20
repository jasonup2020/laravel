<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('app:product-data-processor {category? : 要处理的分类ID，留空则处理所有映射分类}')]
#[Description('处理产品数据并创建具有修改属性的新产品')]
class ProductDataProcessor extends Command {

    

    public function handle() {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        Log::info("测试");
        
    }

}
