<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

/**
 * 复制指定产品命令
 * 用于复制指定的 product_id 产品，进行分类与价格的替换 
 * 主要功能：
 * 1. 根据产品ID复制产品数据
 * 2. 分类映射转换
 * 3. 价格规则处理
 * 4. 外键冲突时记录详细日志（包含SQL语句）
 * 
 * # 复制单个产品
 * php artisan app:copy-product-by-id --ids=123
 * 
 * # 复制多个产品
 * php artisan app:copy-product-by-id --ids=123,456,789
 * 
 * # 从文件读取产品ID列表
 * php artisan app:copy-product-by-id --file=storage/product_ids_to_copy.txt
 * 
 * # 模拟运行（不写入数据库）
 * php artisan app:copy-product-by-id --ids=971,1138,1645 --dry-run
 * php artisan app:copy-product-by-id --file=storage/product_ids_to_copy.txt --dry-run 
 * 
 * 
 */
#[Signature('app:copy-product-by-id 
    {--ids= : 要复制的产品ID，多个ID用逗号分隔，如 --ids=1,2,3}
    {--file= : 从文件读取产品ID列表，每行一个ID}
    {--dry-run : 仅模拟运行，不实际写入数据库}')]
#[Description('复制指定产品ID的产品数据，进行分类与价格替换')]
class CopyProductByIdCommand extends Command {

    /**
     * 分类映射配置
     * 键为原始分类ID，值为目标分类ID
     */
    private array $categoryMap = [
        333 => 778, 329 => 779, 330 => 780, 331 => 781, 332 => 782, 334 => 783, 349 => 797, 347 => 784, 416 => 785, 346 => 786, 414 => 787,
        338 => 788, 341 => 789, 337 => 790, 342 => 791, 343 => 792, 417 => 793, 344 => 794, 348 => 795, 345 => 796,
    ];

    /**
     * 日志文件路径
     */
    private string $logFile;

    /**
     * 错误日志文件路径（记录外键冲突等错误）
     */
    private string $errorLogFile;

    /**
     * SQL日志文件路径（记录所有执行的SQL语句）
     */
    private string $sqlLogFile;

    /**
     * 是否为模拟运行模式
     */
    private bool $isDryRun = false;

    /**
     * 命令执行入口
     *
     * @return int 执行状态码
     */
    public function handle(): int {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);


        exit();
        $this->isDryRun = (bool) $this->option('dry-run');

        $this->initLogFiles();

        $this->info('开始复制产品数据...');
        $this->log('info', '开始复制产品数据...');

        if ($this->isDryRun) {
            $this->warn('【模拟运行模式】不会实际写入数据库');
            $this->log('info', '【模拟运行模式】不会实际写入数据库');
        }

        $productIds = $this->getProductIds();

        if (empty($productIds)) {
            $this->error('请指定要复制的产品ID，使用 --ids=1,2,3 或 --file=/path/to/file.txt');
            return 1;
        }

        $this->info('待处理产品ID: ' . implode(', ', $productIds));
        $this->log('info', '待处理产品ID: ' . implode(', ', $productIds));

        $successCount = 0;
        $failCount = 0;

        foreach ($productIds as $productId) {
            try {
                $result = $this->processProduct((int) $productId);
                if ($result) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->handleError($productId, $e, '处理产品时发生异常');
            }
        }

        $this->newLine(2);
        $this->info("处理完成！成功: {$successCount}, 失败: {$failCount}");
        $this->info("日志文件: {$this->logFile}");
        $this->info("错误日志: {$this->errorLogFile}");
        $this->info("SQL日志: {$this->sqlLogFile}");

        $this->log('info', "处理完成！成功: {$successCount}, 失败: {$failCount}");

        return 0;
    }

    /**
     * 初始化日志文件
     */
    private function initLogFiles(): void {
        $timestamp = date('Ymd_His');
        $logDir = storage_path('logs/product_copy');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->logFile = $logDir . "/copy_{$timestamp}.log";
        $this->errorLogFile = $logDir . "/copy_error_{$timestamp}.log";
        $this->sqlLogFile = $logDir . "/copy_sql_{$timestamp}.log";
    }

    /**
     * 获取要处理的产品ID列表
     *
     * @return array 产品ID数组
     */
    private function getProductIds(): array {
        $idsOption = $this->option('ids');
        $fileOption = $this->option('file');

        $productIds = [];

        if ($idsOption) {
            $ids = array_map('trim', explode(',', $idsOption));
            $productIds = array_filter($ids, 'is_numeric');
        }

        if ($fileOption && file_exists($fileOption)) {
            $fileContent = file($fileOption, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($fileContent as $line) {
                $id = trim($line);
                if (is_numeric($id) && !in_array($id, $productIds)) {
                    $productIds[] = $id;
                }
            }
        }

        return array_unique($productIds);
    }

    /**
     * 处理单个产品
     *
     * @param int $productId 产品ID
     * @return bool 是否成功
     */
    private function processProduct(int $productId): bool {
        $product = DB::table('oc_product')->where('product_id', $productId)->first();

        if (!$product) {
            $this->error("产品 ID {$productId} 不存在");
            $this->log('error', "产品 ID {$productId} 不存在");
            return false;
        }

        $this->info("处理产品 ID: {$productId}");
        $this->log('info', "处理产品 ID: {$productId}");

        // 获取产品的所有原始分类(用于日志记录)
        $allCategories = DB::table('oc_product_to_category')
                ->where('product_id', $productId)
                ->pluck('category_id')
                ->toArray();

        // 获取排除分类后的新分类
        $newCategories = $this->getNewCategories($productId);

        // 检查是否有分类被排除
        $excludeCategories = [766, 327, 326];
        $excludedCategories = array_intersect($allCategories, $excludeCategories);
 

        if (!empty($excludedCategories)) {
            $this->warn("  排除的分类: " . implode(',', $excludedCategories));
            Log::info("产品 ID {$productId} 排除的分类: " . implode(',', $excludedCategories));
        }

        $newPrice = $this->processPrice((float) $product->price);

        if ($this->isDryRun) {
            $this->info("  [模拟] 原价格: {$product->price} -> 新价格: {$newPrice}");
            $this->info("  [模拟] 原分类: " . implode(',', $allCategories));
            $this->info("  [模拟] 新分类: " . implode(',', $newCategories));
            if (!empty($excludedCategories)) {
                $this->info("  [模拟] 排除分类: " . implode(',', $excludedCategories));
            }
            $this->log('info', "  [模拟] 原价格: {$product->price} -> 新价格: {$newPrice}");
            $this->log('info', "  [模拟] 原分类: " . implode(',', $allCategories));
            $this->log('info', "  [模拟] 新分类: " . implode(',', $newCategories));
            if (!empty($excludedCategories)) {
                $this->log('info', "  [模拟] 排除分类: " . implode(',', $excludedCategories));
            }
            return true;
        }

        Log::info("产品 ID {$productId} 原分类: " . implode(',', $allCategories));
        Log::info("产品 ID {$productId} 新分类: " . implode(',', $newCategories));
        if (!empty($excludedCategories)) {
            Log::info("产品 ID {$productId} 排除分类: " . implode(',', $excludedCategories));
        }

        try {
            $newProductId = $this->createNewProduct($product, $newPrice);

            $this->createProductCategories($newProductId, $newCategories);
            
            $this->copyRelatedData($product->product_id, $newProductId, $newPrice);

            if(empty(in_array(766, $allCategories))){
                DB::table('oc_product_to_category')->insert(['product_id' => $productId,'category_id' => 766]);
            }
//            DB::table('oc_product_to_category')->insert(['product_id' => $productId,'category_id' => 766]);
            $logMessage = "成功: 原ID {$productId} -> 新ID {$newProductId}, 价格 {$product->price} -> {$newPrice}";
            $this->info("  {$logMessage}");
            $this->log('success', $logMessage);

            return true;
        } catch (QueryException $e) {
            $this->handleError($productId, $e, '数据库操作失败');
            return false;
        } catch (\Exception $e) {
            $this->handleError($productId, $e, '处理失败');
            return false;
        }
    }

    /**
     * 获取产品的新分类列表
     *
     * @param int $productId 产品ID
     * @return array 新分类ID数组
     */
    private function getNewCategories(int $productId): array {
        // 需要排除的原分类ID
//        $excludeCategories = [766, 327, 326];

        // 查询产品的分类,排除指定的分类
        $categories = DB::table('oc_product_to_category')
                ->where('product_id', $productId)
//                ->whereNotIn('category_id', $excludeCategories)
                ->get();

        $newCategories = [];
        foreach ($categories as $category) {
            if (isset($this->categoryMap[$category->category_id])) {
                $newCategories[] = $this->categoryMap[$category->category_id];
            } else {
                $newCategories[] = $category->category_id;
            }
        }

        $categoryGroup776 = [778, 779, 780, 781, 782, 783, 797, 784, 785, 786, 787];
        $categoryGroup777 = [788, 789, 790, 791, 792, 793, 794, 795, 796];

        $hasGroup776 = false;
        $hasGroup777 = false;

        foreach ($newCategories as $cat) {
            if (in_array($cat, $categoryGroup776)) {
                $hasGroup776 = true;
            }
            if (in_array($cat, $categoryGroup777)) {
                $hasGroup777 = true;
            }
        }
        
        if ($hasGroup776 && !in_array(776, $newCategories)) {
            $newCategories[] = 776;
        }
        if ($hasGroup777 && !in_array(777, $newCategories)) {
            $newCategories[] = 777;
        }
        
        if(!in_array(767, $newCategories)){
            $newCategories[] = 767;
        }

        return array_unique($newCategories);
    }

    /**
     * 创建新产品记录
     *
     * @param object $product 原产品对象
     * @param float $newPrice 新价格
     * @return int 新产品ID
     */
    private function createNewProduct(object $product, float $newPrice): int {
        $data = [
            'model' => $product->model,
            'sku' => $product->sku,
            'upc' => $product->upc,
            'ean' => $product->ean,
            'jan' => $product->jan,
            'isbn' => $product->isbn,
            'mpn' => $product->mpn,
            'location' => $product->location,
            'quantity' => $product->quantity,
            'stock_status_id' => $product->stock_status_id,
            'image' => $product->image,
            'manufacturer_id' => $product->manufacturer_id,
            'shipping' => $product->shipping,
            'price' => $newPrice,
            'points' => $product->points,
            'tax_class_id' => $product->tax_class_id,
            'date_available' => $product->date_available,
            'weight' => $product->weight,
            'weight_class_id' => $product->weight_class_id,
            'length' => $product->length,
            'width' => $product->width,
            'height' => $product->height,
            'length_class_id' => $product->length_class_id,
            'subtract' => $product->subtract,
            'minimum' => $product->minimum,
            'sort_order' => $product->sort_order,
            'status' => $product->status,
            'viewed' => 0,
            'date_added' => now(),
            'date_modified' => now()
        ];

        $sql = $this->generateSqlString('oc_product', $data);
        $this->logSql('INSERT INTO oc_product', $data);
        Log::info("准备插入产品记录 - 原产品ID: {$product->product_id}");
        Log::info("插入SQL: " . $sql);

        try {
            $newProductId = DB::table('oc_product')->insertGetId($data);
            Log::info("插入成功 - 新产品ID: {$newProductId}");
            return $newProductId;
        } catch (\Exception $e) {
            Log::error("插入失败 - 错误: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 创建产品分类关联
     *
     * @param int $newProductId 新产品ID
     * @param array $categories 分类ID数组
     */
    private function createProductCategories(int $newProductId, array $categories): void {
        $excludeCategories = [766, 327, 326];
        foreach ($categories as $categoryId) {
            
            if(in_array($categoryId, $excludeCategories)){
                continue;
            }
            $data = [
                'product_id' => $newProductId,
                'category_id' => $categoryId
            ];

            $sql = $this->generateSqlString('oc_product_to_category', $data);
            $this->logSql('INSERT INTO oc_product_to_category', $data);
            Log::info("准备插入分类关联 - 产品ID: {$newProductId}, 分类ID: {$categoryId}");
            Log::info("插入SQL: " . $sql);

            try {
                DB::table('oc_product_to_category')->insert($data);
                Log::info("插入成功 - 产品ID: {$newProductId}, 分类ID: {$categoryId}");
            } catch (QueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $this->log('warning', "分类关联已存在: product_id={$newProductId}, category_id={$categoryId}");
                    Log::warning("分类关联已存在 - 产品ID: {$newProductId}, 分类ID: {$categoryId}");
                } else {
                    Log::error("插入失败 - 错误: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    }

    /**
     * 复制产品相关数据
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     * @param float $newPrice 新价格
     */
    private function copyRelatedData(int $oldProductId, int $newProductId, float $newPrice): void {
        $this->copyProductDescription($oldProductId, $newProductId);
        $this->copyProductImage($oldProductId, $newProductId);
        $this->copyProductOption($oldProductId, $newProductId);
        $this->copyProductOptionValue($oldProductId, $newProductId, $newPrice);
        $this->copyProductToLayout($oldProductId, $newProductId);
        $this->copyProductToStore($oldProductId, $newProductId);
    }

    /**
     * 复制产品描述
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     */
    private function copyProductDescription(int $oldProductId, int $newProductId): void {
        $descriptions = DB::table('oc_product_description')
                ->where('product_id', $oldProductId)
                ->get();

        foreach ($descriptions as $desc) {
            $suffix = 'MASTER';
            $newName = $this->addSuffixToName($desc->name, $suffix);

            $data = [
                'product_id' => $newProductId,
                'language_id' => $desc->language_id,
                'name' => $newName,
                'description' => $desc->description,
                'meta_title' => $newName,
                'meta_description' => $desc->meta_description,
                'meta_keyword' => $desc->meta_keyword,
                'tag' => $desc->tag
            ];

            $sql = $this->generateSqlString('oc_product_description', $data);
            $this->logSql('INSERT INTO oc_product_description', $data);
            Log::info("准备插入产品描述 - 产品ID: {$newProductId}, 语言ID: {$desc->language_id}");
            Log::info("插入SQL: " . $sql);

            try {
                DB::table('oc_product_description')->insert($data);
                Log::info("插入成功 - 产品ID: {$newProductId}, 语言ID: {$desc->language_id}");
            } catch (QueryException $e) {
                Log::error("插入失败 - 错误: " . $e->getMessage());
                $this->handleInsertError('oc_product_description', $oldProductId, $newProductId, $data, $e);
            }
        }
    }

    /**
     * 复制产品图片
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     */
    private function copyProductImage(int $oldProductId, int $newProductId): void {
        $images = DB::table('oc_product_image')
                ->where('product_id', $oldProductId)
                ->get();

        foreach ($images as $image) {
            $data = [
                'product_id' => $newProductId,
                'image' => $image->image,
                'sort_order' => $image->sort_order
            ];

            $sql = $this->generateSqlString('oc_product_image', $data);
            $this->logSql('INSERT INTO oc_product_image', $data);
            Log::info("准备插入产品图片 - 产品ID: {$newProductId}");
            Log::info("插入SQL: " . $sql);

            try {
                DB::table('oc_product_image')->insert($data);
                Log::info("插入成功 - 产品ID: {$newProductId}");
            } catch (QueryException $e) {
                Log::error("插入失败 - 错误: " . $e->getMessage());
                $this->handleInsertError('oc_product_image', $oldProductId, $newProductId, $data, $e);
            }
        }
    }

    /**
     * 复制产品选项
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     */
    private function copyProductOption(int $oldProductId, int $newProductId): void {
        $options = DB::table('oc_product_option')
                ->where('product_id', $oldProductId)
                ->get();

        foreach ($options as $option) {
            $data = [
                'product_id' => $newProductId,
                'option_id' => $option->option_id,
                'value' => $option->value,
                'required' => $option->required
            ];

            $sql = $this->generateSqlString('oc_product_option', $data);
            $this->logSql('INSERT INTO oc_product_option', $data);
            Log::info("准备插入产品选项 - 产品ID: {$newProductId}, 选项ID: {$option->option_id}");
            Log::info("插入SQL: " . $sql);

            try {
                $productOptionId = DB::table('oc_product_option')->insertGetId($data);
                Log::info("插入成功 - 产品ID: {$newProductId}, 选项ID: {$option->option_id}, 新产品选项ID: {$productOptionId}");
            } catch (QueryException $e) {
                Log::error("插入失败 - 错误: " . $e->getMessage());
                $this->handleInsertError('oc_product_option', $oldProductId, $newProductId, $data, $e);
            }
        }
    }

    /**
     * 复制产品选项值
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     * @param float $price 产品价格
     */
    private function copyProductOptionValue(int $oldProductId, int $newProductId, float $price): void {
        $optionValues = DB::table('oc_product_option_value')
                ->where('product_id', $oldProductId)
                ->get();

        foreach ($optionValues as $ov) {
            $newProductOptionId = DB::table('oc_product_option')
                    ->where('product_id', $newProductId)
                    ->where('option_id', $ov->option_id)
                    ->value('product_option_id');

            if (!$newProductOptionId) {
                continue;
            }

            $ovPrice = $ov->price ?? 0;
            $ovOptionValueId = $ov->option_value_id ?? 0;

            if ($ovPrice > 0 && $ovOptionValueId == 230) {
                $ovPrice = $this->calculateGiftPackagePrice($price);
            }

            $data = [
                'product_option_id' => $newProductOptionId,
                'product_id' => $newProductId,
                'option_id' => $ov->option_id,
                'option_value_id' => $ov->option_value_id,
                'quantity' => $ov->quantity,
                'subtract' => $ov->subtract,
                'price' => $ovPrice,
                'price_prefix' => $ov->price_prefix,
                'points' => $ov->points,
                'points_prefix' => $ov->points_prefix,
                'weight' => $ov->weight,
                'weight_prefix' => $ov->weight_prefix
            ];

            $sql = $this->generateSqlString('oc_product_option_value', $data);
            $this->logSql('INSERT INTO oc_product_option_value', $data);
            Log::info("准备插入产品选项值 - 产品ID: {$newProductId}, 选项ID: {$ov->option_id}, 选项值ID: {$ov->option_value_id}");
            Log::info("插入SQL: " . $sql);

            try {
                DB::table('oc_product_option_value')->insert($data);
                Log::info("插入成功 - 产品ID: {$newProductId}, 选项ID: {$ov->option_id}, 选项值ID: {$ov->option_value_id}");
            } catch (QueryException $e) {
                Log::error("插入失败 - 错误: " . $e->getMessage());
                $this->handleInsertError('oc_product_option_value', $oldProductId, $newProductId, $data, $e);
            }
        }

        if ($price > 200) {
            $this->addGiftPackageOption($newProductId, $price);
        }
    }

    /**
     * 添加礼品包装选项
     *
     * @param int $newProductId 新产品ID
     * @param float $price 产品价格
     */
    private function addGiftPackageOption(int $newProductId, float $price): void {
        $existingOption = DB::table('oc_product_option')
                ->where('product_id', $newProductId)
                ->where('option_id', 39)
                ->first();

        if ($existingOption) {
            return;
        }

        $productOptionData = [
            'product_id' => $newProductId,
            'option_id' => 39,
            'value' => '',
            'required' => 1
        ];

        $sql = $this->generateSqlString('oc_product_option', $productOptionData);
        $this->logSql('INSERT INTO oc_product_option (Gift Package)', $productOptionData);
        Log::info("准备插入礼品包装选项 - 产品ID: {$newProductId}");
        Log::info("插入SQL: " . $sql);

        try {
            $productOptionId = DB::table('oc_product_option')->insertGetId($productOptionData);
            Log::info("插入成功 - 产品ID: {$newProductId}, 新产品选项ID: {$productOptionId}");
        } catch (QueryException $e) {
            Log::error("插入失败 - 错误: " . $e->getMessage());
            $this->handleInsertError('oc_product_option (Gift)', 0, $newProductId, $productOptionData, $e);
            return;
        }

        $giftPrice = $this->calculateGiftPackagePrice($price);

        $noOptionData = [
            'product_option_id' => $productOptionId,
            'product_id' => $newProductId,
            'option_id' => 39,
            'option_value_id' => 229,
            'quantity' => 9999,
            'subtract' => 1,
            'price' => 0.0000,
            'price_prefix' => '+',
            'points' => 0,
            'points_prefix' => '+',
            'weight' => 0,
            'weight_prefix' => '+'
        ];

        $sql = $this->generateSqlString('oc_product_option_value', $noOptionData);
        $this->logSql('INSERT INTO oc_product_option_value (No)', $noOptionData);
        Log::info("准备插入礼品包装选项值(No) - 产品ID: {$newProductId}");
        Log::info("插入SQL: " . $sql);

        try {
            DB::table('oc_product_option_value')->insert($noOptionData);
            Log::info("插入成功 - 产品ID: {$newProductId}, 选项值ID: 229");
        } catch (QueryException $e) {
            Log::error("插入失败 - 错误: " . $e->getMessage());
            $this->handleInsertError('oc_product_option_value (No)', 0, $newProductId, $noOptionData, $e);
        }

        $yesOptionData = [
            'product_option_id' => $productOptionId,
            'product_id' => $newProductId,
            'option_id' => 39,
            'option_value_id' => 230,
            'quantity' => 9999,
            'subtract' => 1,
            'price' => $giftPrice,
            'price_prefix' => '-',
            'points' => 0,
            'points_prefix' => '+',
            'weight' => 0,
            'weight_prefix' => '+'
        ];

        $sql = $this->generateSqlString('oc_product_option_value', $yesOptionData);
        $this->logSql('INSERT INTO oc_product_option_value (Yes)', $yesOptionData);
        Log::info("准备插入礼品包装选项值(Yes) - 产品ID: {$newProductId}, 价格: {$giftPrice}");
        Log::info("插入SQL: " . $sql);

        try {
            DB::table('oc_product_option_value')->insert($yesOptionData);
            Log::info("插入成功 - 产品ID: {$newProductId}, 选项值ID: 230, 价格: {$giftPrice}");
        } catch (QueryException $e) {
            Log::error("插入失败 - 错误: " . $e->getMessage());
            $this->handleInsertError('oc_product_option_value (Yes)', 0, $newProductId, $yesOptionData, $e);
        }
    }

    /**
     * 复制产品布局配置
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     */
    private function copyProductToLayout(int $oldProductId, int $newProductId): void {
        $layouts = DB::table('oc_product_to_layout')
                ->where('product_id', $oldProductId)
                ->get();

        foreach ($layouts as $layout) {
            $data = [
                'product_id' => $newProductId,
                'store_id' => $layout->store_id,
                'layout_id' => $layout->layout_id
            ];

            $sql = $this->generateSqlString('oc_product_to_layout', $data);
            $this->logSql('INSERT INTO oc_product_to_layout', $data);
            Log::info("准备插入产品布局 - 产品ID: {$newProductId}, 店铺ID: {$layout->store_id}");
            Log::info("插入SQL: " . $sql);

            try {
                DB::table('oc_product_to_layout')->insert($data);
                Log::info("插入成功 - 产品ID: {$newProductId}, 店铺ID: {$layout->store_id}");
            } catch (QueryException $e) {
                Log::error("插入失败 - 错误: " . $e->getMessage());
                $this->handleInsertError('oc_product_to_layout', $oldProductId, $newProductId, $data, $e);
            }
        }
    }

    /**
     * 复制产品店铺关联
     *
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     */
    private function copyProductToStore(int $oldProductId, int $newProductId): void {
        $stores = DB::table('oc_product_to_store')
                ->where('product_id', $oldProductId)
                ->get();

        foreach ($stores as $store) {
            $data = [
                'product_id' => $newProductId,
                'store_id' => $store->store_id
            ];

            $sql = $this->generateSqlString('oc_product_to_store', $data);
            $this->logSql('INSERT INTO oc_product_to_store', $data);
            Log::info("准备插入产品店铺关联 - 产品ID: {$newProductId}, 店铺ID: {$store->store_id}");
            Log::info("插入SQL: " . $sql);

            try {
                DB::table('oc_product_to_store')->insert($data);
                Log::info("插入成功 - 产品ID: {$newProductId}, 店铺ID: {$store->store_id}");
            } catch (QueryException $e) {
                Log::error("插入失败 - 错误: " . $e->getMessage());
                $this->handleInsertError('oc_product_to_store', $oldProductId, $newProductId, $data, $e);
            }
        }
    }

    /**
     * 处理价格
     *
     * @param float $price 原价格
     * @return float 新价格
     */
    private function processPrice(float $price): float {
        $integerPart = (int) floor($price);
        $lastTwoDigits = $integerPart % 100;

        switch ($lastTwoDigits) {
            case 19:
                if ($integerPart < 200) {
                    $integerPart = ($integerPart - 19) + 59;
                } elseif ($integerPart < 500) {
                    $integerPart = ($integerPart - 19) + 89;
                } else {
                    $integerPart = ($integerPart - 19) + 100 + 19;
                }
                break;
            case 59:
                if ($integerPart < 200) {
                    $integerPart = ($integerPart - 59) + 89;
                } elseif ($integerPart < 500) {
                    $integerPart = ($integerPart - 59) + 100 + 19;
                } else {
                    $integerPart = ($integerPart - 59) + 100 + 59;
                }
                break;
            case 89:
                if ($integerPart < 200) {
                    $integerPart = ($integerPart - 89) + 100 + 19;
                } elseif ($integerPart < 500) {
                    $integerPart = ($integerPart - 89) + 100 + 59;
                } else {
                    $integerPart = ($integerPart - 89) + 100 + 89;
                }
                break;
        }

        return (float) ($integerPart . '.0000');
    }

    /**
     * 计算礼品包装价格
     *
     * @param float $price 产品价格
     * @return float 礼品包装价格
     */
    private function calculateGiftPackagePrice(float $price): float {
        if ($price > 200 && $price <= 300) {
            return 10.0000;
        } elseif ($price > 300 && $price <= 700) {
            return 20.0000;
        } elseif ($price > 700) {
            return 30.0000;
        }
        return 0.0000;
    }

    /**
     * 为产品名称添加后缀
     *
     * @param string $name 原名称
     * @param string $suffix 后缀
     * @return string 新名称
     */
    private function addSuffixToName(string $name, string $suffix): string {
        $parts = explode(' ', $name);

        if (count($parts) > 2) {
            $brand = $parts[0] . ' ' . $parts[1];
            array_shift($parts);
            array_shift($parts);
            return $brand . ' ' . $suffix . ' ' . implode(' ', $parts);
        } elseif (count($parts) > 1) {
            $brand = array_shift($parts);
            return $brand . ' ' . $suffix . ' ' . implode(' ', $parts);
        }

        return $name . ' ' . $suffix;
    }

    /**
     * 处理插入错误
     *
     * @param string $table 表名
     * @param int $oldProductId 原产品ID
     * @param int $newProductId 新产品ID
     * @param array $data 插入数据
     * @param QueryException $e 异常
     */
    private function handleInsertError(string $table, int $oldProductId, int $newProductId, array $data, QueryException $e): void {
        $errorMsg = $e->getMessage();
        $sql = $this->generateSqlString($table, $data);

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'table' => $table,
            'old_product_id' => $oldProductId,
            'new_product_id' => $newProductId,
            'error' => $errorMsg,
            'sql' => $sql,
            'data' => $data
        ];

        $this->log('error', "插入失败 [{$table}]: {$errorMsg}");
        $this->logError($logData);
    }

    /**
     * 处理错误
     *
     * @param int $productId 产品ID
     * @param \Exception $e 异常
     * @param string $context 上下文信息
     */
    private function handleError(int $productId, \Exception $e, string $context): void {
        $errorMsg = $e->getMessage();

        $this->error("产品 ID {$productId} {$context}: {$errorMsg}");
        $this->log('error', "产品 ID {$productId} {$context}: {$errorMsg}");

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'product_id' => $productId,
            'context' => $context,
            'error' => $errorMsg,
            'trace' => $e->getTraceAsString()
        ];

        if ($e instanceof QueryException) {
            $logData['sql'] = $e->getSql();
            $logData['bindings'] = $e->getBindings();
        }

        $this->logError($logData);
    }

    /**
     * 记录SQL语句
     *
     * @param string $operation 操作描述
     * @param array $data 数据
     */
    private function logSql(string $operation, array $data): void {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] {$operation}\n";
        $logLine .= "Data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        $logLine .= str_repeat('-', 80) . "\n";

        file_put_contents($this->sqlLogFile, $logLine, FILE_APPEND);
    }

    /**
     * 记录错误日志
     *
     * @param array $data 错误数据
     */
    private function logError(array $data): void {
        $logLine = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        $logLine .= str_repeat('=', 80) . "\n";

        file_put_contents($this->errorLogFile, $logLine, FILE_APPEND);
    }

    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     */
    private function log(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}\n";

        file_put_contents($this->logFile, $logLine, FILE_APPEND);
    }

    /**
     * 生成SQL语句字符串
     *
     * @param string $table 表名
     * @param array $data 数据
     * @return string SQL语句
     */
    private function generateSqlString(string $table, array $data): string {
        $columns = array_keys($data);
        $values = array_map(function ($value) {
            if (is_null($value)) {
                return 'NULL';
            } elseif (is_string($value)) {
                return "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                return $value ? '1' : '0';
            }
            return $value;
        }, array_values($data));

        return "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");";
    }
}
