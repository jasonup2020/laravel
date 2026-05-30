<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * 产品增量同步命令 V14
 *
 * V14版本核心特性：
 * 1. 继承V9的产品增量同步功能
 * 2. 完善 oc_category 系列表的同步支持，支持ID映射关联
 * 3. 新增 oc_option 系列表同步，采用全量ID匹配模式
 * 4. 支持产品和分类的同步映射
 * 5. 支持更新模式：新增不查date_modified，只有更新才根据date_modified判断是否更新已存在数据
 * 6. 远程新增数据映射为本地全新ID
 * 7. 所有子表以本地新ID关联存储
 * 8. oc_product_to_category 的 category_id 会正确映射为本地ID
 * 9. oc_product_option 和 oc_product_option_value 使用各自的主键映射
 * 10. oc_category.parent_id 正确映射为本地 category_id
 *
 * 
 *  命令参数
 *  可用选项：
 *  | 选项 | 说明 |
 *  | --- | --- |
 *  | --dry-run | 模拟运行模式 |
 *  | --force | 强制全量同步 |
 *  | --site= | 指定站点 |
 *  | --date= | 指定日期（格式：Y-m-d） |
 *  | --days= | 最近N天 |
 *  | --ids= | 指定产品ID列表（逗号分隔），传入时不受时间限制 |
 *  | --category-ids= | 指定分类ID列表（逗号分隔），传入时不受时间限制 |
 *  | --timeout=300 | 超时时间 |
 *  | --sync-type= | 同步类型：product（仅产品）、category（仅分类）、option（仅选项）、all（全部，默认） |
 *  例子 
 *  php artisan product:sync-v14 --site=savebull_cp2026 --date=2026-05-01 --days=1 --sync-type=product
 *  说明：同步2026年5月1日到2026年5月2日的产品增量数据，仅同步产品表
 * @author CodeArts Agent
 * @version 14.0
 * @date 2026-05-05
 */
class ProductIncrementalSyncV14 extends Command {

    protected $signature = 'product:sync-v14
        {--dry-run : 模拟运行}
        {--force : 强制全量同步}
        {--site= : 指定站点}
        {--date= : 指定日期（格式：Y-m-d）}
        {--days= : 最近N天}
        {--ids= : 指定产品ID列表（逗号分隔），传入时不受时间限制}
        {--category-ids= : 指定分类ID列表（逗号分隔），传入时不受时间限制}
        {--timeout=300 : 超时时间}
        {--sync-type= : 同步类型：product（仅产品）、category（仅分类）、option（仅选项）、currency（仅货币）、all（全部，默认）}';
    
    
    /**
     * 命令描述
     */
    protected $description = '非同ID产品增量同步V14 - 支持新增和更新产品、分类、选项同步，子表先删后加 
                                --days=7 --ids=59428,59429 --date=2026-05-07 --category-ids=800 
                                --sync-type=product|category|option|currency|all';
    
    // 配置
    private array $config = [];
    private bool $isDryRun = false;
    private int $queryTimeout = 300;

    private const BATCH_SIZE = 3000; // 固定每次提取3000条

    private int $similarity=97; //相似度 97%
    // 映射数据
    private array $idMappings = [];
    private array $tempMappings = [];
    // 统计数据
    private array $stats = [];
    // 本地产品缓存（优化版：包含多级索引）
    private array $localProductCache = [];
    // 本地分类缓存
    private array $localCategoryCache = [];
    // 本地选项缓存
    private array $localOptionCache = [];
    // 本地货币缓存
    private array $localCurrencyCache = [];
    // 日期范围
    private string $dateStart = '';
    private string $dateEnd = '';
    // 本次同步涉及的远程ID（用于子表同步过滤）
    private array $currentSyncRemoteIds = [];
    // 指定的产品ID列表（不受时间限制）
    private array $specifiedProductIds = [];
    private array $specifiedCategoryIds = [];
    // 产品子表配置
    private array $productChildTables = [
        'oc_product_description' => ['primary_key' => ['product_id', 'language_id']],
        'oc_product_image' => ['primary_key' => ['product_image_id']],
        'oc_product_option' => ['primary_key' => ['product_option_id']],
        'oc_product_option_value' => ['primary_key' => ['product_option_value_id']],
        'oc_product_to_category' => ['primary_key' => ['product_id', 'category_id']],
        'oc_product_to_store' => ['primary_key' => ['product_id', 'store_id']],
        'oc_product_to_layout' => ['primary_key' => ['product_id', 'store_id']],
        'oc_product_attribute' => ['primary_key' => ['product_id', 'attribute_id', 'language_id']],
        'oc_product_discount' => ['primary_key' => ['product_discount_id']],
        'oc_product_filter' => ['primary_key' => ['product_id', 'filter_id']],
        'oc_product_recurring' => ['primary_key' => ['product_id', 'recurring_id', 'customer_group_id']],
        'oc_product_related' => ['primary_key' => ['product_id', 'related_id']],
        'oc_product_reward' => ['primary_key' => ['product_reward_id']],
        'oc_product_special' => ['primary_key' => ['product_special_id']],
        'oc_product_to_download' => ['primary_key' => ['product_id', 'download_id']],
    ];
    // 分类子表配置
    private array $categoryChildTables = [
        'oc_category_description' => ['primary_key' => ['category_id', 'language_id']],
        'oc_category_filter' => ['primary_key' => ['category_id', 'filter_id']],
        'oc_category_path' => ['primary_key' => ['category_id', 'path_id']],
        'oc_category_to_layout' => ['primary_key' => ['category_id', 'store_id']],
        'oc_category_to_store' => ['primary_key' => ['category_id', 'store_id']],
    ];
    // 选项子表配置
    private array $optionChildTables = [
        'oc_option_description' => ['primary_key' => ['option_id', 'language_id']],
        'oc_option_value' => ['primary_key' => ['option_value_id']],
        'oc_option_value_description' => ['primary_key' => ['option_value_id', 'language_id']],
    ];
    // 同步记录批量缓冲区
    private array $syncRecordBuffer = [];
    private array $syncLogBuffer = [];
    private array $syncConflictBuffer = [];
    private array $syncDeadBuffer = [];

    private const SYNC_BUFFER_LIMIT = 100;

    public function handle(): int {
        $startTime = microtime(true);

        // 设置PHP运行参数
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);

        $this->isDryRun = $this->option('dry-run');
        $this->queryTimeout = (int) $this->option('timeout');

        $this->info('=== 产品增量同步 V14 ===');
        $this->info('核心原则：新增不查date_modified，只有更新才根据date_modified判断');
        $this->info("日期范围: {$this->dateStart} ~ {$this->dateEnd}");

        if ($this->isDryRun) {
            $this->warn('⚠️  【模拟运行模式】');
        }

        // 初始化
        $this->loadConfig();  # 加载配置
        $this->loadIdMappings();  # 加载ID映射
        $this->setDateRange();  # 设置日期范围
        $this->parseSpecifiedIds();  # 解析指定的产品ID列表
        $this->initStats();  # 初始化统计数据

        // 构建缓存
        $this->buildLocalOptionCache();  # 构建本地选项缓存
        $this->buildLocalCategoryCache();  # 构建本地分类缓存
        $this->buildLocalProductCache();  # 构建本地产品缓存

        // 获取同步类型
        $syncType = $this->option('sync-type') ?? 'all';

        // 根据同步类型执行同步
        $this->info("同步类型: {$syncType}");
        if ($syncType === 'category' || $syncType === 'all') {
            $this->syncCategories();
        }

        if ($syncType === 'option' || $syncType === 'all') {
            $this->syncOptions();
        }

        if ($syncType === 'currency' || $syncType === 'all') {
            $this->syncCurrencies();
        }

        if ($syncType === 'product' || $syncType === 'all') {
            if ($syncType === 'product') {
                $this->info('⚠️  仅同步产品模式，将使用已有的分类映射');
            }
            $this->syncProducts();
        }

        // 保存映射
        $this->saveIdMappings();

        // 刷新所有同步记录缓冲区
        $this->flushAllSyncBuffers();

        // 生成报告
        $this->generateReport();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->info("\n=== 同步完成 ===");
        $this->info("总耗时: {$duration} 秒");

        return 0;
    }

    /**
     * 设置日期范围
     */
    private function setDateRange(): void {
        $dateOption = $this->option('date');
        $daysOption = $this->option('days');

        if ($daysOption) {
            $this->dateStart = date('Y-m-d 00:00:00', strtotime("-$daysOption days"));
            $this->dateEnd = date('Y-m-d H:i:s');
        } elseif ($dateOption) {
            $this->dateStart = $dateOption . ' 00:00:00';
            $this->dateEnd = $dateOption . ' 23:59:59';
        } else {
            $this->dateStart = date('Y-m-d 00:00:00');
            $this->dateEnd = date('Y-m-d 23:59:59');
        }
    }

    /**
     * 解析指定的产品ID列表
     */
    private function parseSpecifiedIds(): void {
        // 解析产品ID
        $idsOption = $this->option('ids');
        if ($idsOption) {
            $ids = explode(',', $idsOption);
            $this->specifiedProductIds = array_filter(array_map('intval', $ids));
            
            if (!empty($this->specifiedProductIds)) {
                $this->info('📌 指定产品ID: ' . implode(', ', $this->specifiedProductIds));
            }
        }
        
        // 解析分类ID
        $categoryIdsOption = $this->option('category-ids');
        if ($categoryIdsOption) {
            $ids = explode(',', $categoryIdsOption);
            $this->specifiedCategoryIds = array_filter(array_map('intval', $ids));
            
            if (!empty($this->specifiedCategoryIds)) {
                $this->info('📌 指定分类ID: ' . implode(', ', $this->specifiedCategoryIds));
            }
        }
    }

    /**
     * 检查是否指定了产品ID（不受时间限制）
     */
    private function hasSpecifiedIds(): bool {
        return !empty($this->specifiedProductIds);
    }

    /**
     * 检查是否指定了分类ID（不受时间限制）
     */
    private function hasSpecifiedCategoryIds(): bool {
        return !empty($this->specifiedCategoryIds);
    }

    /**
     * 加载配置
     */
    private function loadConfig(): void {
        $this->config = config('remote_databases_products');
    }

    /**
     * 加载ID映射
     */
    private function loadIdMappings(): void {
        $mappingFile = storage_path('app/remote_product_sync/id_mappings.json');
        if (file_exists($mappingFile)) {
            $this->idMappings = json_decode(file_get_contents($mappingFile), true) ?? [];
            $productCount = count($this->idMappings['savebull_cp2026']['oc_product'] ?? []);
            $categoryCount = count($this->idMappings['savebull_cp2026']['oc_category'] ?? []);
            $optionCount = count($this->idMappings['savebull_cp2026']['oc_option'] ?? []);
            $this->info("✅ 加载已有映射: 产品 {$productCount} 条, 分类 {$categoryCount} 条, 选项 {$optionCount} 条");
        }
        $this->tempMappings = [];
    }

    /**
     * 初始化统计
     */
    private function initStats(): void {
        $this->stats = [
            'start_time' => microtime(true),
            'tables' => [],
            'total_inserted' => 0,
            'total_updated' => 0,
            'total_skipped' => 0,
        ];
    }

    /**
     * 构建本地产品缓存（包含多级索引）
     */
    private function buildLocalProductCache(): void {
        $this->info('构建本地产品缓存...');

        $localCount = DB::table('oc_product')->count();

        if ($localCount === 0) {
            $this->info('  ℹ️  本地产品数据库为空，跳过缓存构建');
            $this->localProductCache = [
                'date_model' => [],
                'date_model_image' => [],
                'date_model_image_name' => [],
                'products' => [],
            ];
            return;
        }

        $this->localProductCache = [
            'date_model' => [], // date_added + model
            'date_model_image' => [], // date_added + model + image
            'date_model_image_name' => [], // date_added + model + image + name
            'products' => [], // 产品详情
        ];

        $batchSize = 1000;
        $offset = 0;
        $totalCount = 0;

        $this->info("  📦 开始构建缓存，每批 {$batchSize} 条");

        while (true) {
            $products = DB::table('oc_product as p')
                    ->leftJoin('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
                    ->where(function ($query) {
                        $query->where('pd.language_id', 1)->orWhereNull('pd.language_id');
                    })
                    ->select(
                            'p.product_id',
                            'p.model',
                            'p.image',
                            'p.date_added',
                            'p.date_modified',
                            'pd.name'
                    )
                    ->orderBy('p.product_id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

            if ($products->isEmpty()) {
                break;
            }

            foreach ($products as $product) {
                $imagePath = $this->extractImagePath($product->image ?? '');

                if (!empty($product->date_added) && !empty($product->model)) {
                    $key1 = $product->date_added . '_' . $product->model;
                    if (!isset($this->localProductCache['date_model'][$key1])) {
                        $this->localProductCache['date_model'][$key1] = [];
                    }
                    $this->localProductCache['date_model'][$key1][] = $product->product_id;
                }

                if (!empty($product->date_added) && !empty($product->model) && !empty($imagePath)) {
                    $key2 = $product->date_added . '_' . $product->model . '_' . $imagePath;
                    if (!isset($this->localProductCache['date_model_image'][$key2])) {
                        $this->localProductCache['date_model_image'][$key2] = [];
                    }
                    $this->localProductCache['date_model_image'][$key2][] = $product->product_id;
                }

                if (!empty($product->date_added) && !empty($product->model) && !empty($imagePath) && !empty($product->name)) {
                    $key3 = $product->date_added . '_' . $product->model . '_' . $imagePath . '_' . $product->name;
                    if (!isset($this->localProductCache['date_model_image_name'][$key3])) {
                        $this->localProductCache['date_model_image_name'][$key3] = [];
                    }
                    $this->localProductCache['date_model_image_name'][$key3][] = $product->product_id;
                }

                $this->localProductCache['products'][$product->product_id] = (object) [
                            'product_id' => $product->product_id,
                            'model' => $product->model,
                            'name' => $product->name,
                            'date_added' => $product->date_added,
                            'date_modified' => $product->date_modified,
                            'image' => $imagePath,
                ];
            }

            $totalCount += $products->count();
            $offset += $batchSize;
            unset($products);
        }

        $this->info("  ✅ 产品缓存构建完成: {$totalCount} 个产品");
    }

    /**
     * 构建本地分类缓存
     */
    private function buildLocalCategoryCache(): void {
        $this->info('构建本地分类缓存...');

        $localCount = DB::table('oc_category')->count();

        if ($localCount === 0) {
            $this->info('  ℹ️  本地分类数据库为空，跳过缓存构建');
            $this->localCategoryCache = [
                'date_name' => [],
                'date_name_parent' => [],
                'categories' => [],
            ];
            return;
        }

        $this->localCategoryCache = [
            'date_name' => [], // date_added + name
            'date_name_parent' => [], // date_added + name + parent_id
            'categories' => [], // 分类详情
        ];

        $batchSize = 1000;
        $offset = 0;
        $totalCount = 0;

        $this->info("  📦 开始构建分类缓存，每批 {$batchSize} 条");

        while (true) {
            $categories = DB::table('oc_category as c')
                    ->leftJoin('oc_category_description as cd', 'c.category_id', '=', 'cd.category_id')
                    ->where(function ($query) {
                        $query->where('cd.language_id', 1)->orWhereNull('cd.language_id');
                    })
                    ->select(
                            'c.category_id',
                            'c.parent_id',
                            'c.date_added',
                            'c.date_modified',
                            'cd.name'
                    )
                    ->orderBy('c.category_id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

            if ($categories->isEmpty()) {
                break;
            }

            foreach ($categories as $category) {
                if (!empty($category->date_added) && !empty($category->name)) {
                    $key1 = $category->date_added . '_' . $category->name;
                    if (!isset($this->localCategoryCache['date_name'][$key1])) {
                        $this->localCategoryCache['date_name'][$key1] = [];
                    }
                    $this->localCategoryCache['date_name'][$key1][] = $category->category_id;
                }

                if (!empty($category->date_added) && !empty($category->name)) {
                    $key2 = $category->date_added . '_' . $category->name . '_' . $category->parent_id;
                    if (!isset($this->localCategoryCache['date_name_parent'][$key2])) {
                        $this->localCategoryCache['date_name_parent'][$key2] = [];
                    }
                    $this->localCategoryCache['date_name_parent'][$key2][] = $category->category_id;
                }

                $this->localCategoryCache['categories'][$category->category_id] = (object) [
                            'category_id' => $category->category_id,
                            'parent_id' => $category->parent_id,
                            'name' => $category->name,
                            'date_added' => $category->date_added,
                            'date_modified' => $category->date_modified,
                ];
            }

            $totalCount += $categories->count();
            $offset += $batchSize;
            unset($categories);
        }

        $this->info("  ✅ 分类缓存构建完成: {$totalCount} 个分类");
    }

    /**
     * 构建本地选项缓存
     */
    private function buildLocalOptionCache(): void {
        $this->info('构建本地选项缓存...');

        $localCount = DB::table('oc_option')->count();

        if ($localCount === 0) {
            $this->info('  ℹ️  本地选项数据库为空，跳过缓存构建');
            $this->localOptionCache = [
                'options' => [],
                'option_values' => [],
            ];
            return;
        }

        $this->localOptionCache = [
            'options' => [], // 选项详情 key: option_id
            'option_values' => [], // 选项值详情 key: option_value_id
        ];

        // 加载选项主表
        $options = DB::table('oc_option')->get();
        foreach ($options as $option) {
            $this->localOptionCache['options'][$option->option_id] = (object) [
                        'option_id' => $option->option_id,
                        'type' => $option->type,
                        'sort_order' => $option->sort_order,
                        'tag' => $option->tag,
            ];
        }

        // 加载选项值表
        $optionValues = DB::table('oc_option_value')->get();
        foreach ($optionValues as $value) {
            $this->localOptionCache['option_values'][$value->option_value_id] = (object) [
                        'option_value_id' => $value->option_value_id,
                        'option_id' => $value->option_id,
                        'image' => $value->image,
                        'sort_order' => $value->sort_order,
            ];
        }

        $this->info("  ✅ 选项缓存构建完成: {$localCount} 个选项, " . count($this->localOptionCache['option_values']) . " 个选项值");
    }

    /**
     * 同步产品数据
     */
    private function syncProducts(): void {
        // 产品系列表同步前先加载映射文件
        $this->loadIdMappings();

        $remoteDatabases = $this->config['remote_databases'] ?? [];
        $site = $this->option('site');

        foreach ($remoteDatabases as $remoteDb) {
            if ($site && $remoteDb['name'] !== $site) {
                continue;
            }

            $this->info("\n========== 处理站点: {$remoteDb['name']} (产品) ==========");

            $connectionName = $this->createRemoteConnection($remoteDb);
            $dbName = $remoteDb['name'];

            $this->syncProductTable($connectionName, $dbName);
            $this->syncProductChildTables($connectionName, $dbName);
        }

        // 产品系列表执行完后更新映射文件
        $this->saveIdMappings();
    }

    /**
     * 同步分类数据
     */
    private function syncCategories(): void {
        // 分类系列表同步前先加载映射文件
        $this->loadIdMappings();

        $remoteDatabases = $this->config['remote_databases'] ?? [];
        $site = $this->option('site');

        foreach ($remoteDatabases as $remoteDb) {
            if ($site && $remoteDb['name'] !== $site) {
                continue;
            }

            $this->info("\n========== 处理站点: {$remoteDb['name']} (分类) ==========");

            $connectionName = $this->createRemoteConnection($remoteDb);
            $dbName = $remoteDb['name'];

            $this->syncCategoryTable($connectionName, $dbName);
            $this->syncCategoryChildTables($connectionName, $dbName);
        }

        // 分类系列表执行完后更新映射文件
        $this->saveIdMappings();
    }

    /**
     * 同步选项数据
     */
    private function syncOptions(): void {
        $this->loadIdMappings();

        $remoteDatabases = $this->config['remote_databases'] ?? [];
        $site = $this->option('site');

        foreach ($remoteDatabases as $remoteDb) {
            if ($site && $remoteDb['name'] !== $site) {
                continue;
            }

            $this->info("\n========== 处理站点: {$remoteDb['name']} (选项) ==========");

            $connectionName = $this->createRemoteConnection($remoteDb);
            $dbName = $remoteDb['name'];

            $this->syncOptionTable($connectionName, $dbName);
            $this->syncOptionChildTables($connectionName, $dbName);
        }

        $this->saveIdMappings();
    }

    /**
     * 同步货币数据
     */
    private function syncCurrencies(): void {
        $this->loadIdMappings();

        $remoteDatabases = $this->config['remote_databases'] ?? [];
        $site = $this->option('site');

        foreach ($remoteDatabases as $remoteDb) {
            if ($site && $remoteDb['name'] !== $site) {
                continue;
            }

            $this->info("\n========== 处理站点: {$remoteDb['name']} (货币) ==========");

            $connectionName = $this->createRemoteConnection($remoteDb);
            $dbName = $remoteDb['name'];

            $this->syncCurrencyTable($connectionName, $dbName);
        }

        $this->saveIdMappings();
    }

    /**
     * 同步货币主表（全量ID匹配模式）
     */
    private function syncCurrencyTable(string $remoteConnection, string $dbName): void {
        $this->info('  【同步 oc_currency 主表】');

        $table = 'oc_currency';
        $primaryKey = 'currency_id';

        $total = $this->executeWithRetry($remoteConnection, function ($db) use ($table) {
            return $db->table($table)->count();
        });

        if ($total === 0) {
            $this->info('    ℹ️  无数据');
            return;
        }

        $this->info("    📊 发现 {$total} 条远程记录");
        $this->info("    📦 批量大小: " . self::BATCH_SIZE);

        $inserted = 0;
        $updated = 0;
        $maxId = 0;

        $offset = 0;
        while ($offset < $total) {
            $batch = $this->executeWithRetry($remoteConnection, function ($db) use ($table, $offset, $primaryKey) {
                return $db->table($table)
                                ->orderBy($primaryKey)
                                ->skip($offset)
                                ->take(self::BATCH_SIZE)
                                ->get();
            });

            if ($batch->isEmpty()) {
                break;
            }

            $batchResult = $this->syncCurrencyBatch($remoteConnection, $dbName, $batch);

            $inserted += $batchResult['inserted'];
            $updated += $batchResult['updated'];

            foreach ($batch as $currency) {
                $maxId = max($maxId, $currency->$primaryKey);
            }

            $offset += self::BATCH_SIZE;
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 更新: {$updated}");

        $this->stats['tables'][$table] = ['inserted' => $inserted, 'updated' => $updated, 'skipped' => 0];
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_updated'] = ($this->stats['total_updated'] ?? 0) + $updated;
    }

    /**
     * 批量同步货币
     */
    private function syncCurrencyBatch(
            string $remoteConnection,
            string $dbName,
            \Illuminate\Support\Collection $batch
    ): array {
        $inserted = 0;
        $updated = 0;
        $insertData = [];
        $updateData = [];

        foreach ($batch as $currency) {
            $remoteId = $currency->currency_id;
            $localId = null;

            // 检查映射文件
            if (isset($this->idMappings[$dbName]['oc_currency'][$remoteId])) {
                $localId = $this->idMappings[$dbName]['oc_currency'][$remoteId];
            } elseif (isset($this->idMappings[$dbName]['oc_currency'][(string) $remoteId])) {
                $localId = $this->idMappings[$dbName]['oc_currency'][(string) $remoteId];
            }

            // 如果映射文件中找不到，尝试通过 code 查找
            if ($localId === null && !empty($currency->code)) {
                $localCurrency = DB::table('oc_currency')
                                ->where('code', $currency->code)
                                ->first();
                if ($localCurrency) {
                    $localId = $localCurrency->currency_id;
                }
            }

            if ($localId !== null) {
                // 更新已存在的记录
                $this->tempMappings[$dbName]['oc_currency'][$remoteId] = $localId;

                $currencyData = (array) $currency;
                unset($currencyData['currency_id']);

                $updateData[] = [
                    'data' => $currencyData,
                    'local_id' => $localId,
                    'remote_id' => $remoteId
                ];
            } else {
                // 插入新记录
                $currencyData = (array) $currency;
                unset($currencyData['currency_id']);

                $insertData[] = [
                    'data' => $currencyData,
                    'remote_id' => $remoteId
                ];
            }
        }

        // 执行更新
        foreach ($updateData as $item) {
            if (!$this->isDryRun) {
                try {
                    DB::table('oc_currency')
                            ->where('currency_id', $item['local_id'])
                            ->update($item['data']);
                    $updated++;
                } catch (\Exception $e) {
                    $this->error("    ❌ 更新货币失败 (ID: {$item['local_id']}): " . $e->getMessage());
                }
            } else {
                $updated++;
            }
        }

        // 执行插入
        foreach ($insertData as $item) {
            if (!$this->isDryRun) {
                try {
                    $localId = DB::table('oc_currency')->insertGetId($item['data']);
                    $this->tempMappings[$dbName]['oc_currency'][$item['remote_id']] = $localId;
                    $inserted++;
                } catch (\Exception $e) {
                    $this->error("    ❌ 插入货币失败: " . $e->getMessage());
                }
            } else {
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * 创建远程连接
     */
    private function createRemoteConnection(array $remoteDb): string {
        $connectionName = 'remote_product_' . $remoteDb['name'];

        config(["database.connections.{$connectionName}" => [
                'driver' => 'mysql',
                'host' => $remoteDb['db_host'],
                'port' => $remoteDb['db_port'] ?? '3306',
                'database' => $remoteDb['db_name'],
                'username' => $remoteDb['db_username'],
                'password' => $remoteDb['db_pwd'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options' => [
                    \PDO::ATTR_TIMEOUT => $this->queryTimeout,
                    \PDO::ATTR_PERSISTENT => true,
                ],
        ]]);

        return $connectionName;
    }

    /**
     * 判断是否是首次同步
     */
    private function isFirstSync(string $dbName, string $table): bool {
        return !isset($this->idMappings[$dbName][$table]) || empty($this->idMappings[$dbName][$table]);
    }

    protected function logSqlQueries() {
        $queries = DB::getQueryLog();

        foreach ($queries as $query) {
            $sql = $query['query'];
            $bindings = $query['bindings'];
            $time = $query['time'];

            $pdo = DB::connection()->getPdo();
            $realSql = $sql;
            foreach ($bindings as $binding) {
                $value = is_numeric($binding) ? $binding : $pdo->quote($binding);
                $realSql = preg_replace('/\?/', $value, $realSql, 1);
            }

            // 输出到日志
            Log::channel('daily')->info("[SYNC SQL] {$time}ms | {$realSql}");

            // 输出到命令行
            $this->line("SQL: {$realSql}");
        }
    }

    /**
     * 同步产品主表
     */
    private function syncProductTable(string $remoteConnection, string $dbName): void {
        $this->info('  【同步 oc_product 主表】');

        $table = 'oc_product';
        $primaryKey = 'product_id';

        $isFirstSync = $this->isFirstSync($dbName, $table);
        $hasSpecifiedIds = $this->hasSpecifiedIds();
        
        $this->info("    ℹ️  是否首次同步: " . ($isFirstSync ? '是' : '否'));
        
        if ($hasSpecifiedIds) {
            $this->info("    📌 指定ID同步模式（不受时间限制）");
            $this->info("    📌 指定产品ID: " . implode(', ', $this->specifiedProductIds));
        } elseif (!$isFirstSync) {
            $this->info("    📅 日期范围: {$this->dateStart} ~ {$this->dateEnd}");
        }

        $lastMaxId = $this->option('force') ? 0 : $this->getLastMaxId($dbName, $table);

        $total = $this->executeWithRetry($remoteConnection, function ($db) use ($table, $primaryKey, $lastMaxId, $isFirstSync, $hasSpecifiedIds) {
            $query = $db->table($table);

            if ($this->option('force')) {
                // 强制全量同步，不添加任何条件
            } elseif ($hasSpecifiedIds) {
                // 指定ID模式：按ID列表筛选
                $query->whereIn($primaryKey, $this->specifiedProductIds);
            } elseif ($isFirstSync) {
                // 首次同步：按ID增量
                $query->where($primaryKey, '>', $lastMaxId);
            } else {
                // 非首次同步：按日期范围筛选
                $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('date_added', '>=', $this->dateStart)
                                ->Where('date_added', '<=', $this->dateEnd)
                                ->orwhere('date_modified', '>=', $this->dateStart)
                                ->Where('date_modified', '<=', $this->dateEnd);
                    });
                });
            }

            return $query->count();
        });

        if ($total === 0) {
            $this->info('    ℹ️  无新数据');
            return;
        }

        $this->info("    📊 发现 {$total} 条远程记录");
        $this->info("    📦 批量大小: " . self::BATCH_SIZE);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $maxId = $lastMaxId;

        $offset = 0;
        while ($offset < $total) {


            $batch = $this->executeWithRetry($remoteConnection, function ($db) use ($table, $primaryKey, $lastMaxId, $offset, $isFirstSync, $hasSpecifiedIds) {
                $query = $db->table($table);

                if ($this->option('force')) {
                    // 强制全量同步
                } elseif ($hasSpecifiedIds) {
                    // 指定ID模式：按ID列表筛选
                    $query->whereIn($primaryKey, $this->specifiedProductIds);
                } elseif ($isFirstSync) {
                    // 首次同步：按ID增量
                    $query->where($primaryKey, '>', $lastMaxId);
                } else {
                    // 非首次同步：按日期范围筛选
                    $query->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('date_added', '>=', $this->dateStart)
                                    ->Where('date_added', '<=', $this->dateEnd)
                                    ->orwhere('date_modified', '>=', $this->dateStart)
                                    ->Where('date_modified', '<=', $this->dateEnd);
                        });
                    });
                }

                return $query->distinct()->orderBy($primaryKey)
                                ->skip($offset)
                                ->take(self::BATCH_SIZE)
                                ->get();
            });

            if ($batch->isEmpty()) {
                break;
            }

            $remoteIds = $batch->pluck('product_id')->toArray();
            $remoteDescriptions = $this->executeWithRetry($remoteConnection, function ($db) use ($remoteIds) {
                return $db->table('oc_product_description')
                                ->whereIn('product_id', $remoteIds)
                                ->where('language_id', 1)
                                ->get()
                                ->keyBy('product_id');
            });

            $batchWithName = $batch->map(function ($product) use ($remoteDescriptions) {
                $product->name = $remoteDescriptions[$product->product_id]->name ?? '';
                return $product;
            });

            $batchResult = $this->syncProductBatch($remoteConnection, $dbName, $batchWithName);

            $inserted += $batchResult['inserted'];
            $updated += $batchResult['updated'];
            $skipped += $batchResult['skipped'];

            // 记录本次同步涉及的远程ID（用于子表同步）
            foreach ($batch as $product) {
                $maxId = max($maxId, $product->$primaryKey);
                $this->currentSyncRemoteIds[$dbName]['oc_product'][] = $product->product_id;
            }

            $offset += self::BATCH_SIZE;
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 更新: {$updated}, 已存在: {$skipped}");

        $this->stats['tables'][$table] = compact('inserted', 'updated', 'skipped');
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_updated'] = ($this->stats['total_updated'] ?? 0) + $updated;
        $this->stats['total_skipped'] = ($this->stats['total_skipped'] ?? 0) + $skipped;

        $this->updateProgress($dbName, $table, $maxId);
    }

    /**
     * 同步分类主表
     */
    private function syncCategoryTable(string $remoteConnection, string $dbName): void {
        $this->info('  【同步 oc_category 主表】');

        DB::connection()->enableQueryLog();

        $table = 'oc_category';
        $primaryKey = 'category_id';

        $isFirstSync = $this->isFirstSync($dbName, $table);
        $hasSpecifiedCategoryIds = $this->hasSpecifiedCategoryIds();
        
        $this->info("    ℹ️  是否首次同步: " . ($isFirstSync ? '是' : '否'));
        if (!$isFirstSync && !$hasSpecifiedCategoryIds) {
            $this->info("    📅 日期范围: {$this->dateStart} ~ {$this->dateEnd}");
        }
        
        if ($hasSpecifiedCategoryIds) {
            $this->info("    📌 指定分类ID: " . implode(', ', $this->specifiedCategoryIds));
        }

        $lastMaxId = $this->option('force') ? 0 : $this->getLastMaxId($dbName, $table);

        $total = $this->executeWithRetry($remoteConnection, function ($db) use ($table, $primaryKey, $lastMaxId, $isFirstSync, $hasSpecifiedCategoryIds) {
            $query = $db->table($table);

            if ($this->option('force')) {
                // 强制全量同步，不添加任何条件
            } elseif ($hasSpecifiedCategoryIds) {
                // 指定分类ID模式：直接按指定ID查询
                $query->whereIn($primaryKey, $this->specifiedCategoryIds);
            } elseif ($isFirstSync) {
                // 首次同步：按ID增量
                $query->where($primaryKey, '>', $lastMaxId);
            } else {
                // 非首次同步：按日期范围筛选
                $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('date_added', '>=', $this->dateStart)
                                ->Where('date_added', '<=', $this->dateEnd)
                                ->orwhere('date_modified', '>=', $this->dateStart)
                                ->Where('date_modified', '<=', $this->dateEnd);
                    });
                });
            }

            return $query->count();
        });

        if ($total === 0) {
            $this->info('    ℹ️  无新数据');
            return;
        }

        $this->info("    📊 发现 {$total} 条远程记录");
        $this->info("    📦 批量大小: " . self::BATCH_SIZE);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $maxId = $lastMaxId;

        $offset = 0;
        while ($offset < $total) {
            $batch = $this->executeWithRetry($remoteConnection, function ($db) use ($table, $primaryKey, $lastMaxId, $offset, $isFirstSync, $hasSpecifiedCategoryIds) {
                $query = $db->table($table);

                if ($this->option('force')) {
                    // 强制全量同步
                } elseif ($hasSpecifiedCategoryIds) {
                    // 指定分类ID模式：直接按指定ID查询
                    $query->whereIn($primaryKey, $this->specifiedCategoryIds);
                } elseif ($isFirstSync) {
                    // 首次同步：按ID增量
                    $query->where($primaryKey, '>', $lastMaxId);
                } else {
                    // 非首次同步：按日期范围筛选
                    $query->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('date_added', '>=', $this->dateStart)
                                    ->Where('date_added', '<=', $this->dateEnd)
                                    ->orwhere('date_modified', '>=', $this->dateStart)
                                    ->Where('date_modified', '<=', $this->dateEnd);
                        });
                    });
                }

                return $query->distinct()->orderBy($primaryKey)
                                ->skip($offset)
                                ->take(self::BATCH_SIZE)
                                ->get();
            });

            if ($batch->isEmpty()) {
                break;
            }

            $remoteIds = $batch->pluck('category_id')->toArray();
            $remoteDescriptions = $this->executeWithRetry($remoteConnection, function ($db) use ($remoteIds) {
                return $db->table('oc_category_description')
                                ->whereIn('category_id', $remoteIds)
                                ->where('language_id', 1)
                                ->get()
                                ->keyBy('category_id');
            });

            $batchWithName = $batch->map(function ($category) use ($remoteDescriptions) {
                $category->name = $remoteDescriptions[$category->category_id]->name ?? '';
                return $category;
            });

            $batchResult = $this->syncCategoryBatch($remoteConnection, $dbName, $batchWithName);

            $inserted += $batchResult['inserted'];
            $updated += $batchResult['updated'];
            $skipped += $batchResult['skipped'];

            // 记录本次同步涉及的远程ID（用于子表同步）
            foreach ($batch as $category) {
                $maxId = max($maxId, $category->$primaryKey);
                $this->currentSyncRemoteIds[$dbName]['oc_category'][] = $category->category_id;
            }

            $offset += self::BATCH_SIZE;
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }



        Log::info("SQL", [DB::getQueryLog()]);

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 更新: {$updated}, 已存在: {$skipped}");

        $this->stats['tables'][$table] = compact('inserted', 'updated', 'skipped');
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_updated'] = ($this->stats['total_updated'] ?? 0) + $updated;
        $this->stats['total_skipped'] = ($this->stats['total_skipped'] ?? 0) + $skipped;

        $this->updateProgress($dbName, $table, $maxId);
    }

    /**
     * 同步选项主表（全量ID匹配模式）
     */
    private function syncOptionTable(string $remoteConnection, string $dbName): void {
        $this->info('  【同步 oc_option 主表】');

        $table = 'oc_option';
        $primaryKey = 'option_id';

        $total = $this->executeWithRetry($remoteConnection, function ($db) use ($table) {
            return $db->table($table)->count();
        });

        if ($total === 0) {
            $this->info('    ℹ️  无数据');
            return;
        }

        $this->info("    📊 发现 {$total} 条远程记录");
        $this->info("    📦 批量大小: " . self::BATCH_SIZE);

        $inserted = 0;
        $updated = 0;
        $maxId = 0;

        $offset = 0;
        while ($offset < $total) {
            $batch = $this->executeWithRetry($remoteConnection, function ($db) use ($table, $offset, $primaryKey) {
                return $db->table($table)
                                ->orderBy($primaryKey)
                                ->skip($offset)
                                ->take(self::BATCH_SIZE)
                                ->get();
            });

            if ($batch->isEmpty()) {
                break;
            }

            $batchResult = $this->syncOptionBatch($remoteConnection, $dbName, $batch);

            $inserted += $batchResult['inserted'];
            $updated += $batchResult['updated'];

            foreach ($batch as $option) {
                $maxId = max($maxId, $option->$primaryKey);
            }

            $offset += self::BATCH_SIZE;
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 更新: {$updated}");

        $this->stats['tables'][$table] = ['inserted' => $inserted, 'updated' => $updated, 'skipped' => 0];
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_updated'] = ($this->stats['total_updated'] ?? 0) + $updated;

        $this->updateProgress($dbName, $table, $maxId);
    }

    /**
     * 批量同步产品（支持更新）
     */
    private function syncProductBatch(
            string $remoteConnection,
            string $dbName,
            \Illuminate\Support\Collection $batch
    ): array {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $insertData = [];
        $updateData = [];

        $isFirstSync = $this->isFirstSync($dbName, 'oc_product');
        $hasSpecifiedIds = $this->hasSpecifiedIds();

        foreach ($batch as $product) {
            $remoteId = $product->product_id;
            $localId = null;

            // 指定ID模式：优先查找映射，找不到则尝试查找本地产品，或作为新增
            if ($hasSpecifiedIds) {
                // 优先从映射文件查找
                if (isset($this->idMappings[$dbName]['oc_product'][$remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_product'][$remoteId];
                } elseif (isset($this->idMappings[$dbName]['oc_product'][(string) $remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_product'][(string) $remoteId];
                }
                
                // 映射文件找不到，尝试通过其他方式查找本地产品
                if ($localId === null) {
                    $localProduct = $this->findLocalProduct($product);
                    if ($localProduct) {
                        $localId = $localProduct->product_id;
                    }
                }
                
                if ($localId !== null) {
                    // 找到本地ID，强制更新
                    $this->tempMappings[$dbName]['oc_product'][$remoteId] = $localId;
                    
                    $productData = (array) $product;
                    unset($productData['product_id']);
                    unset($productData['name']);
                    $productData = $this->processImageUrls('oc_product', $productData);
                    
                    $updateData[] = [
                        'data' => $productData,
                        'local_id' => $localId,
                        'remote_id' => $remoteId
                    ];
                } else {
                    // 找不到本地ID，作为新增处理
                    $productData = (array) $product;
                    unset($productData['product_id']);
                    unset($productData['name']);
                    $productData = $this->processImageUrls('oc_product', $productData);
                    
                    $insertData[] = [
                        'data' => $productData,
                        'remote_id' => $remoteId,
                        'remote_name' => $product->name ?? null
                    ];
                }
                continue;
            }

            // 非首次同步：优先检查映射文件
            if (!$isFirstSync) {
                if (isset($this->idMappings[$dbName]['oc_product'][$remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_product'][$remoteId];
                } elseif (isset($this->idMappings[$dbName]['oc_product'][(string) $remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_product'][(string) $remoteId];
                }
            }

            // 如果映射文件中找不到，尝试通过其他方式查找本地产品
            if ($localId === null) {
                $localProduct = $this->findLocalProduct($product);
                if ($localProduct) {
                    $localId = $localProduct->product_id;
                }
            }

            // 非首次同步时，如果找不到本地映射，说明可能已经同步过但映射记录丢失，跳过处理
            // if (!$isFirstSync && $localId === null) {
            //     $this->warn("    ⚠️  非首次同步，远程ID {$remoteId} 未找到映射，跳过");
            //     $skipped++;
            //     continue;
            // }

            if ($localId !== null) {
                $this->tempMappings[$dbName]['oc_product'][$remoteId] = $localId;

                // 获取本地产品信息用于更新判断
                $localProduct = $this->getProductFromCache($localId) ?? (object) [];

                // 检查是否需要更新（根据date_modified）
                if (!empty($product->date_modified) && !empty($localProduct->date_modified)) {
                    $remoteModified = strtotime($product->date_modified);
                    $localModified = strtotime($localProduct->date_modified);

                    if ($remoteModified > $localModified) {
                        $productData = (array) $product;
                        unset($productData['product_id']);
                        unset($productData['name']);
                        $productData = $this->processImageUrls('oc_product', $productData);

                        $updateData[] = [
                            'data' => $productData,
                            'local_id' => $localId,
                            'remote_id' => $remoteId
                        ];
                        continue;
                    }
                } elseif (!empty($product->date_added)) {
                    // 如果本地没有date_modified但远程有date_added，也视为需要更新
                    $updateData[] = [
                        'data' => (array) $product,
                        'local_id' => $localId,
                        'remote_id' => $remoteId
                    ];
                    continue;
                }

                $skipped++;
                continue;
            }

            // 本地不存在，作为新增处理
            $productData = (array) $product;
            unset($productData['product_id']);
            unset($productData['name']);

            $productData = $this->processImageUrls('oc_product', $productData);

            $insertData[] = [
                'data' => $productData,
                'remote_id' => $remoteId,
                'remote_name' => $product->name ?? null
            ];
        }

        if (!empty($insertData)) {
            $inserted = $this->batchInsertProductWithIdMapping($dbName, 'oc_product', $insertData);
        }

        if (!empty($updateData)) {
            $updated = $this->batchUpdateProduct($dbName, 'oc_product', $updateData);
        }

        return compact('inserted', 'updated', 'skipped');
    }

    /**
     * 批量同步分类（支持更新）
     */
    private function syncCategoryBatch(
            string $remoteConnection,
            string $dbName,
            \Illuminate\Support\Collection $batch
    ): array {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $insertData = [];
        $updateData = [];

        $isFirstSync = $this->isFirstSync($dbName, 'oc_category');
        $hasSpecifiedCategoryIds = $this->hasSpecifiedCategoryIds();

        foreach ($batch as $category) {
            $remoteId = $category->category_id;
            $localId = null;

            // 指定分类ID模式：优先查找映射，找不到则尝试查找本地分类，或作为新增
            if ($hasSpecifiedCategoryIds) {
                // 优先从映射文件查找
                if (isset($this->idMappings[$dbName]['oc_category'][$remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_category'][$remoteId];
                } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_category'][(string) $remoteId];
                }
                
                // 映射文件找不到，尝试通过其他方式查找本地分类
                if ($localId === null) {
                    $localCategory = $this->findLocalCategory($category);
                    if ($localCategory) {
                        $localId = $localCategory->category_id;
                    }
                }
                
                if ($localId !== null) {
                    // 找到本地ID，强制更新
                    $this->tempMappings[$dbName]['oc_category'][$remoteId] = $localId;
                    
                    $categoryData = (array) $category;
                    unset($categoryData['category_id']);
                    unset($categoryData['name']);

                    // 映射 parent_id 为本地 category_id
                    if (isset($categoryData['parent_id']) && $categoryData['parent_id'] > 0) {
                        $remoteParentId = $categoryData['parent_id'];
                        $localParentId = null;
                        if (isset($this->idMappings[$dbName]['oc_category'][$remoteParentId])) {
                            $localParentId = $this->idMappings[$dbName]['oc_category'][$remoteParentId];
                        } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                            $localParentId = $this->idMappings[$dbName]['oc_category'][(string) $remoteParentId];
                        } elseif (isset($this->tempMappings[$dbName]['oc_category'][$remoteParentId])) {
                            $localParentId = $this->tempMappings[$dbName]['oc_category'][$remoteParentId];
                        } elseif (isset($this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                            $localParentId = $this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId];
                        }
                        if ($localParentId !== null) {
                            $categoryData['parent_id'] = $localParentId;
                        }
                    }

                    $categoryData = $this->processImageUrls('oc_category', $categoryData);

                    $updateData[] = [
                        'data' => $categoryData,
                        'local_id' => $localId,
                        'remote_id' => $remoteId
                    ];
                } else {
                    // 找不到本地ID，作为新增处理
                    $categoryData = (array) $category;
                    $remoteId = $categoryData['category_id'];
                    unset($categoryData['category_id']);
                    unset($categoryData['name']);

                    // 映射 parent_id 为本地 category_id
                    if (isset($categoryData['parent_id']) && $categoryData['parent_id'] > 0) {
                        $remoteParentId = $categoryData['parent_id'];
                        $localParentId = null;
                        if (isset($this->idMappings[$dbName]['oc_category'][$remoteParentId])) {
                            $localParentId = $this->idMappings[$dbName]['oc_category'][$remoteParentId];
                        } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                            $localParentId = $this->idMappings[$dbName]['oc_category'][(string) $remoteParentId];
                        }
                        if ($localParentId !== null) {
                            $categoryData['parent_id'] = $localParentId;
                        }
                    }

                    $categoryData = $this->processImageUrls('oc_category', $categoryData);

                    $insertData[] = [
                        'data' => $categoryData,
                        'remote_id' => $remoteId,
                        'remote_name' => $category->name ?? null
                    ];
                }
                continue;
            }

            // 非首次同步：优先检查映射文件
            if (!$isFirstSync) {
                if (isset($this->idMappings[$dbName]['oc_category'][$remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_category'][$remoteId];
                } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteId])) {
                    $localId = $this->idMappings[$dbName]['oc_category'][(string) $remoteId];
                }
            }

            // 如果映射文件中找不到，尝试通过其他方式查找本地分类
            if ($localId === null) {
                $localCategory = $this->findLocalCategory($category);
                if ($localCategory) {
                    $localId = $localCategory->category_id;
                }
            }

            // 非首次同步时，如果找不到本地映射，说明可能已经同步过但映射记录丢失，跳过处理
            if (!$isFirstSync && $localId === null) {
                $this->warn("    ⚠️  非首次同步，远程ID {$remoteId} 未找到映射，跳过");
                $skipped++;
                continue;
            }

            if ($localId !== null) {
                $this->tempMappings[$dbName]['oc_category'][$remoteId] = $localId;

                // 获取本地分类信息用于更新判断
                $localCategory = $this->getCategoryFromCache($localId) ?? (object) [];

                // 检查是否需要更新（根据date_modified）
                if (!empty($category->date_modified) && !empty($localCategory->date_modified)) {
                    $remoteModified = strtotime($category->date_modified);
                    $localModified = strtotime($localCategory->date_modified);

                    if ($remoteModified > $localModified) {
                        $categoryData = (array) $category;
                        unset($categoryData['category_id']);
                        unset($categoryData['name']);

                        // 映射 parent_id 为本地 category_id（更新时也需要）
                        if (isset($categoryData['parent_id']) && $categoryData['parent_id'] > 0) {
                            $remoteParentId = $categoryData['parent_id'];
                            $localParentId = null;
                            if (isset($this->idMappings[$dbName]['oc_category'][$remoteParentId])) {
                                $localParentId = $this->idMappings[$dbName]['oc_category'][$remoteParentId];
                            } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                                $localParentId = $this->idMappings[$dbName]['oc_category'][(string) $remoteParentId];
                            } elseif (isset($this->tempMappings[$dbName]['oc_category'][$remoteParentId])) {
                                $localParentId = $this->tempMappings[$dbName]['oc_category'][$remoteParentId];
                            } elseif (isset($this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                                $localParentId = $this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId];
                            }
                            if ($localParentId !== null) {
                                $categoryData['parent_id'] = $localParentId;
                            }
                        }

                        $categoryData = $this->processImageUrls('oc_category', $categoryData);

                        $updateData[] = [
                            'data' => $categoryData,
                            'local_id' => $localId,
                            'remote_id' => $remoteId
                        ];
                        continue;
                    }
                } elseif (!empty($category->date_added)) {
                    // 如果本地没有date_modified但远程有date_added，也视为需要更新
                    $updateCategoryData = (array) $category;
                    unset($updateCategoryData['category_id']);
                    unset($updateCategoryData['name']);

                    // 映射 parent_id 为本地 category_id
                    if (isset($updateCategoryData['parent_id']) && $updateCategoryData['parent_id'] > 0) {
                        $remoteParentId = $updateCategoryData['parent_id'];
                        $localParentId = null;
                        if (isset($this->idMappings[$dbName]['oc_category'][$remoteParentId])) {
                            $localParentId = $this->idMappings[$dbName]['oc_category'][$remoteParentId];
                        } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                            $localParentId = $this->idMappings[$dbName]['oc_category'][(string) $remoteParentId];
                        } elseif (isset($this->tempMappings[$dbName]['oc_category'][$remoteParentId])) {
                            $localParentId = $this->tempMappings[$dbName]['oc_category'][$remoteParentId];
                        } elseif (isset($this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                            $localParentId = $this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId];
                        }
                        if ($localParentId !== null) {
                            $updateCategoryData['parent_id'] = $localParentId;
                        }
                    }

                    $updateData[] = [
                        'data' => $updateCategoryData,
                        'local_id' => $localId,
                        'remote_id' => $remoteId
                    ];
                    continue;
                }

                $skipped++;
                continue;
            }

            // 本地不存在，作为新增处理
            $categoryData = (array) $category;
            $remoteId = $categoryData['category_id'];
            unset($categoryData['category_id']);
            unset($categoryData['name']);

            // 映射 parent_id 为本地 category_id
            if (isset($categoryData['parent_id']) && $categoryData['parent_id'] > 0) {
                $remoteParentId = $categoryData['parent_id'];
                $localParentId = null;
                if (isset($this->idMappings[$dbName]['oc_category'][$remoteParentId])) {
                    $localParentId = $this->idMappings[$dbName]['oc_category'][$remoteParentId];
                } elseif (isset($this->idMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                    $localParentId = $this->idMappings[$dbName]['oc_category'][(string) $remoteParentId];
                } elseif (isset($this->tempMappings[$dbName]['oc_category'][$remoteParentId])) {
                    $localParentId = $this->tempMappings[$dbName]['oc_category'][$remoteParentId];
                } elseif (isset($this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId])) {
                    $localParentId = $this->tempMappings[$dbName]['oc_category'][(string) $remoteParentId];
                }
                if ($localParentId !== null) {
                    $categoryData['parent_id'] = $localParentId;
                }
            }

            $categoryData = $this->processImageUrls('oc_category', $categoryData);

            $insertData[] = [
                'data' => $categoryData,
                'remote_id' => $remoteId,
                'remote_name' => $category->name ?? null
            ];
        }

        if (!empty($insertData)) {
            $inserted = $this->batchInsertCategoryWithIdMapping($dbName, 'oc_category', $insertData);
        }

        if (!empty($updateData)) {
            $updated = $this->batchUpdateCategory($dbName, 'oc_category', $updateData);
        }

        return compact('inserted', 'updated', 'skipped');
    }

    /**
     * 批量同步选项（全量ID匹配，支持更新）
     */
    private function syncOptionBatch(
            string $remoteConnection,
            string $dbName,
            \Illuminate\Support\Collection $batch
    ): array {
        $inserted = 0;
        $updated = 0;

        foreach ($batch as $option) {
            $optionId = $option->option_id;

            // 检查本地是否已存在相同option_id的选项
            if (isset($this->localOptionCache['options'][$optionId])) {
                // 直接更新本地数据
                $optionData = (array) $option;
                unset($optionData['option_id']);

                if (!$this->isDryRun) {
                    try {
                        DB::table('oc_option')
                                ->where('option_id', $optionId)
                                ->update($optionData);
                        $this->addSyncRecord($dbName, 'oc_option', (string) $optionId, (string) $optionId, 'update');
                        $updated++;
                    } catch (\Exception $ex) {
                        $this->recordError($dbName, 'oc_option', $optionId, $ex->getMessage());
                    }
                } else {
                    $updated++;
                }
            } else {
                // 直接插入，使用远程的option_id
                $optionData = (array) $option;

                if (!$this->isDryRun) {
                    try {
                        // 先检查数据库中是否已存在
                        $exists = DB::table('oc_option')->where('option_id', $optionId)->exists();
                        if ($exists) {
                            // 已存在，执行更新
                            unset($optionData['option_id']);
                            DB::table('oc_option')
                                    ->where('option_id', $optionId)
                                    ->update($optionData);
                            $this->localOptionCache['options'][$optionId] = (object) array_merge(['option_id' => $optionId], $optionData);
                            $this->addSyncRecord($dbName, 'oc_option', (string) $optionId, (string) $optionId, 'update');
                            $updated++;
                        } else {
                            // 不存在，执行插入
                            DB::table('oc_option')->insert($optionData);
                            $this->localOptionCache['options'][$optionId] = (object) $optionData;
                            $this->addSyncRecord($dbName, 'oc_option', (string) $optionId, (string) $optionId, 'insert');
                            $inserted++;
                        }
                    } catch (\Exception $ex) {
                        $this->recordError($dbName, 'oc_option', $optionId, $ex->getMessage());
                    }
                } else {
                    $inserted++;
                }
            }

            // 记录映射（option_id直接映射）
            $this->tempMappings[$dbName]['oc_option'][$optionId] = $optionId;
        }

        return compact('inserted', 'updated');
    }

    /**
     * 查找本地产品
     */
    private function findLocalProduct(object $product): ?object {
        $imagePath = null;
        if (!empty($product->image)) {
            $imagePath = $this->extractImagePath($product->image);
        }

        $remoteName = $product->name ?? '';

        if (empty($product->date_added) || empty($product->model)) {
            return null;
        }

        $key1 = $product->date_added . '_' . $product->model;

        if (!isset($this->localProductCache['date_model'][$key1])) {
            return null;
        }

        $candidateIds = $this->localProductCache['date_model'][$key1];
        if (!is_array($candidateIds)) {
            $candidateIds = [$candidateIds];
        }

        if (!empty($imagePath)) {
            $key2 = $product->date_added . '_' . $product->model . '_' . $imagePath;

            if (isset($this->localProductCache['date_model_image'][$key2])) {
                $candidateIds = $this->localProductCache['date_model_image'][$key2];
                if (!is_array($candidateIds)) {
                    $candidateIds = [$candidateIds];
                }
            }
        }

        if (!empty($remoteName)) {
            $key3 = $product->date_added . '_' . $product->model . '_' . ($imagePath ?? '') . '_' . $remoteName;

            if (isset($this->localProductCache['date_model_image_name'][$key3])) {
                $ids = $this->localProductCache['date_model_image_name'][$key3];
                if (!is_array($ids)) {
                    $ids = [$ids];
                }
                return $this->getProductFromCache($ids[0]);
            }

            foreach ($candidateIds as $localId) {
                $localProduct = $this->getProductFromCache($localId);
                if ($localProduct && !empty($localProduct->name)) {
                    $similarity = $this->calculateNameSimilarity($remoteName, $localProduct->name);
                    //相似度对比   
                    if ($similarity >= $this->similarity) {
                        return $localProduct;
                    }
                }
            }
        }

        if (!empty($candidateIds)) {
            return $this->getProductFromCache($candidateIds[0]);
        }

        return null;
    }

    /**
     * 查找本地分类
     */
    private function findLocalCategory(object $category): ?object {
        $remoteName = $category->name ?? '';
        $parentId = $category->parent_id ?? 0;

        if (empty($category->date_added) || empty($remoteName)) {
            return null;
        }

        $key1 = $category->date_added . '_' . $remoteName;

        if (!isset($this->localCategoryCache['date_name'][$key1])) {
            return null;
        }

        $candidateIds = $this->localCategoryCache['date_name'][$key1];
        if (!is_array($candidateIds)) {
            $candidateIds = [$candidateIds];
        }

        $key2 = $category->date_added . '_' . $remoteName . '_' . $parentId;

        if (isset($this->localCategoryCache['date_name_parent'][$key2])) {
            $ids = $this->localCategoryCache['date_name_parent'][$key2];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            return $this->getCategoryFromCache($ids[0]);
        }

        if (!empty($candidateIds)) {
            return $this->getCategoryFromCache($candidateIds[0]);
        }

        return null;
    }

    /**
     * 从缓存获取产品对象
     */
    private function getProductFromCache(int $productId): ?object {
        return $this->localProductCache['products'][$productId] ?? null;
    }

    /**
     * 从缓存获取分类对象
     */
    private function getCategoryFromCache(int $categoryId): ?object {
        return $this->localCategoryCache['categories'][$categoryId] ?? null;
    }

    /**
     * 计算名称相似度
     */
    private function calculateNameSimilarity(string $name1, string $name2): float {
        $name1 = trim(strtolower($name1));
        $name2 = trim(strtolower($name2));

        if ($name1 === $name2) {
            return 100.0;
        }

        similar_text($name1, $name2, $percent);

        return round($percent, 2);
    }

    /**
     * 提取图片路径
     */
    private function extractImagePath(?string $image): string {
        if (empty($image)) {
            return '';
        }

        $pos = strpos($image, 'catalog/');
        if ($pos !== false) {
            return substr($image, $pos);
        }

        return $image;
    }

    /**
     * 批量插入产品并建立ID映射
     */
    private function batchInsertProductWithIdMapping(string $dbName, string $table, array $insertData): int {
        if ($this->isDryRun) {
            return count($insertData);
        }

        $count = count($insertData);
        $inserted = 0;

        foreach ($insertData as $key => $item) {
            $remoteId = $item['remote_id'] ?? 0;

            try {
                $newId = DB::table($table)->insertGetId($item['data']);

                $this->info("    📊 新的 -$key- 远程ID映射的本地ID:::: $remoteId  --> $newId");
                $this->tempMappings[$dbName][$table][$item['remote_id']] = $newId;
//                $this->info("    📊 新的 -$key --00001- 远程ID映射的本地ID:::: $remoteId  --> $newId");
                $this->updateLocalProductCacheFromRemote($newId, $item['data'], $item['remote_name'] ?? null);

                try {
                    $this->addSyncRecord($dbName, $table, $item['remote_id'], $newId, 'insert');
                } catch (\Exception $exc) {
                    $this->warn("    ⚠️ 新的 -$key --00003- 远程ID映射的本地ID:::: $remoteId  --> $newId  ".$exc->getMessage());
                }
//                $this->info("    📊 新的 -$key --00003- 远程ID映射的本地ID:::: $remoteId  --> $newId");
                $inserted++;
            } catch (\Exception $ex) {
                $this->error("    📊 ⚠ 新的 -$key- 远程ID映射的报错:::: $remoteId  ");
                $this->recordError($dbName, $table, $item['remote_id']."_err", $ex->getMessage());
                $this->addSyncDead($dbName, $table, $item['remote_id']."_err", $ex->getMessage(), $item['data']);
            }
        }

        return $inserted;
    }

    /**
     * 批量更新产品
     */
    private function batchUpdateProduct(string $dbName, string $table, array $updateData): int {
        if ($this->isDryRun) {
            return count($updateData);
        }

        $updated = 0;

        foreach ($updateData as $item) {
            try {
                DB::table($table)
                        ->where('product_id', $item['local_id'])
                        ->update($item['data']);
                $this->addSyncRecord($dbName, $table, $item['remote_id'], $item['local_id'], 'update');
                $updated++;
            } catch (\Exception $ex) {
                $this->recordError($dbName, $table, $item['remote_id'], $ex->getMessage());
            }
        }

        return $updated;
    }

    /**
     * 批量插入分类并建立ID映射 
     */
    private function batchInsertCategoryWithIdMapping(string $dbName, string $table, array $insertData): int {
        if ($this->isDryRun) {
            return count($insertData);
        }

        $count = count($insertData);
        $inserted = 0;

        foreach ($insertData as $item) {
            try {
                $newId = DB::table($table)->insertGetId($item['data']);
                $this->tempMappings[$dbName][$table][$item['remote_id']] = $newId;
                $this->updateLocalCategoryCacheFromRemote($newId, $item['data'], $item['remote_name'] ?? null);
                $this->addSyncRecord($dbName, $table, $item['remote_id'], $newId, 'insert');
                $inserted++;
            } catch (\Exception $ex) {
                $this->recordError($dbName, $table, $item['remote_id']."_err", $ex->getMessage());
                $this->addSyncDead($dbName, $table, $item['remote_id']."_err", $ex->getMessage(), $item['data']);
            }
        }

        return $inserted;
    }

    /**
     * 批量更新分类
     */
    private function batchUpdateCategory(string $dbName, string $table, array $updateData): int {
        if ($this->isDryRun) {
            return count($updateData);
        }

        $updated = 0;

        foreach ($updateData as $item) {
            try {
                DB::table($table)
                        ->where('category_id', $item['local_id'])
                        ->update($item['data']);
                $this->addSyncRecord($dbName, $table, $item['remote_id'], $item['local_id'], 'update');
                $updated++;
            } catch (\Exception $ex) {
                $this->recordError($dbName, $table, $item['remote_id'], $ex->getMessage());
            }
        }

        return $updated;
    }

    /**
     * 更新本地产品缓存
     */
    private function updateLocalProductCacheFromRemote(int $localId, array $productData, ?string $remoteName): void {
        $model = $productData['model'] ?? '';
        $dateAdded = $productData['date_added'] ?? '';
        $dateModified = $productData['date_modified'] ?? '';
        $image = $this->extractImagePath($productData['image'] ?? '');

        if (!empty($dateAdded) && !empty($model)) {
            $key = $dateAdded . '_' . $model;
            if (!isset($this->localProductCache['date_model'][$key])) {
                $this->localProductCache['date_model'][$key] = [];
            }
            $this->localProductCache['date_model'][$key][] = $localId;
        }

        if (!empty($dateAdded) && !empty($model) && !empty($image)) {
            $key = $dateAdded . '_' . $model . '_' . $image;
            if (!isset($this->localProductCache['date_model_image'][$key])) {
                $this->localProductCache['date_model_image'][$key] = [];
            }
            $this->localProductCache['date_model_image'][$key][] = $localId;
        }

        if (!empty($dateAdded) && !empty($model) && !empty($image) && !empty($remoteName)) {
            $key = $dateAdded . '_' . $model . '_' . $image . '_' . $remoteName;
            if (!isset($this->localProductCache['date_model_image_name'][$key])) {
                $this->localProductCache['date_model_image_name'][$key] = [];
            }
            $this->localProductCache['date_model_image_name'][$key][] = $localId;
        }

        $this->localProductCache['products'][$localId] = (object) [
                    'product_id' => $localId,
                    'model' => $model,
                    'name' => $remoteName ?? '',
                    'date_added' => $dateAdded,
                    'date_modified' => $dateModified,
                    'image' => $image,
        ];
    }

    /**
     * 更新本地分类缓存
     */
    private function updateLocalCategoryCacheFromRemote(int $localId, array $categoryData, ?string $remoteName): void {
        $parentId = $categoryData['parent_id'] ?? 0;
        $dateAdded = $categoryData['date_added'] ?? '';
        $dateModified = $categoryData['date_modified'] ?? '';

        if (!empty($dateAdded) && !empty($remoteName)) {
            $key = $dateAdded . '_' . $remoteName;
            if (!isset($this->localCategoryCache['date_name'][$key])) {
                $this->localCategoryCache['date_name'][$key] = [];
            }
            $this->localCategoryCache['date_name'][$key][] = $localId;
        }

        if (!empty($dateAdded) && !empty($remoteName)) {
            $key = $dateAdded . '_' . $remoteName . '_' . $parentId;
            if (!isset($this->localCategoryCache['date_name_parent'][$key])) {
                $this->localCategoryCache['date_name_parent'][$key] = [];
            }
            $this->localCategoryCache['date_name_parent'][$key][] = $localId;
        }

        $this->localCategoryCache['categories'][$localId] = (object) [
                    'category_id' => $localId,
                    'parent_id' => $parentId,
                    'name' => $remoteName ?? '',
                    'date_added' => $dateAdded,
                    'date_modified' => $dateModified,
        ];
    }

    /**
     * 同步产品子表
     */
    private function syncProductChildTables(string $remoteConnection, string $dbName): void {
        foreach ($this->productChildTables as $table => $config) {
            $this->info("  【同步 {$table}】");
            $this->syncProductChildTable($remoteConnection, $dbName, $table, $config);
        }
    }

    /**
     * 同步分类子表
     */
    private function syncCategoryChildTables(string $remoteConnection, string $dbName): void {
        foreach ($this->categoryChildTables as $table => $config) {
            $this->info("  【同步 {$table}】");
            $this->syncCategoryChildTable($remoteConnection, $dbName, $table, $config);
        }
    }

    /**
     * 同步选项子表
     */
    private function syncOptionChildTables(string $remoteConnection, string $dbName): void {
        foreach ($this->optionChildTables as $table => $config) {
            $this->info("  【同步 {$table}】");
            $this->syncOptionChildTable($remoteConnection, $dbName, $table, $config);
        }
    }

    /**
     * 同步产品子表（支持 category_id 映射）
     */
    private function syncProductChildTable(
            string $remoteConnection,
            string $dbName,
            string $table,
            array $config
    ): void {
        // 获取本次同步涉及的远程产品ID
        $currentRemoteIds = $this->currentSyncRemoteIds[$dbName]['oc_product'] ?? [];

        if (empty($currentRemoteIds)) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        // 从临时映射和持久映射中构建产品ID映射
        $productMappings = [];

        // 优先使用临时映射（本次同步新增的）
        if (isset($this->tempMappings[$dbName]['oc_product'])) {
            foreach ($this->tempMappings[$dbName]['oc_product'] as $remoteId => $localId) {
                $productMappings[(int) $remoteId] = (int) $localId;
            }
        }

        // 补充持久映射
        if (isset($this->idMappings[$dbName]['oc_product'])) {
            foreach ($this->idMappings[$dbName]['oc_product'] as $remoteId => $localId) {
                $remoteId = (int) $remoteId;
                if (!isset($productMappings[$remoteId])) {
                    $productMappings[$remoteId] = (int) $localId;
                }
            }
        }

        // 只保留本次同步涉及的ID映射
        $parentIds = [];
        foreach ($currentRemoteIds as $remoteId) {
            $remoteId = (int) $remoteId;
            if (isset($productMappings[$remoteId])) {
                $parentIds[] = $remoteId;
            }
        }

        if (empty($parentIds)) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        // 需要先删除历史数据的表
//        $tablesToClean = ['oc_product_image', 'oc_product_option', 'oc_product_option_value', 'oc_product_to_category', 'oc_product_to_layout', 'oc_product_to_store'];
        $tablesToClean = ['oc_product_image', 'oc_product_option', 'oc_product_option_value', 'oc_product_to_category'];
        
        // 如果是需要清理的表，先删除本地历史数据
        if (in_array($table, $tablesToClean)) {
            // 获取本地产品ID列表
            $localProductIds = [];
            foreach ($parentIds as $remoteId) {
                if (isset($productMappings[$remoteId])) {
                    $localProductIds[] = $productMappings[$remoteId];
                }
            }
            
            if (!empty($localProductIds)) {
                // 删除子表中的历史数据
                $deleted = DB::table($table)->whereIn('product_id', $localProductIds)->delete();
                $this->info("      🗑️  删除 {$table} 历史数据 {$deleted} 条");
                
                // 删除 oc_sync_record 中相关的记录
                $syncDeleted = DB::table('oc_sync_record')
                    ->where('source_site', $dbName)
                    ->where('source_table', $table)
                    ->whereIn('target_id', $localProductIds)
                    ->delete();
                $this->info("      🗑️  删除 oc_sync_record 相关记录 {$syncDeleted} 条");
            }
        }

        // 获取分类映射（用于 oc_product_to_category 表）
        $categoryMappings = [];
        if ($table === 'oc_product_to_category') {
            // 从数据库 oc_sync_record 表获取分类映射
            $categorySyncRecords = DB::table('oc_sync_record')
                    ->where('source_site', $dbName)
                    ->where('source_table', 'oc_category')
                    ->where('status', 'success')
                    ->get();
            foreach ($categorySyncRecords as $record) {
                $categoryMappings[(int) $record->source_id] = (int) $record->target_id;
            }

            // 从 id_mappings.json 文件补充分类映射
            if (isset($this->idMappings[$dbName]['oc_category'])) {
                foreach ($this->idMappings[$dbName]['oc_category'] as $remoteId => $localId) {
                    $remoteId = (int) $remoteId;
                    if (!isset($categoryMappings[$remoteId])) {
                        $categoryMappings[$remoteId] = (int) $localId;
                    }
                }
            }
        }

        // 处理 oc_product_option 和 oc_product_option_value 的 option_id 映射
        $optionMappings = [];
        if ($table === 'oc_product_option' || $table === 'oc_product_option_value') {
            $optionMappings = $this->getOptionMappings($dbName);
        }

        // 处理 oc_product_option_value 的 option_value_id 映射
        $optionValueMappings = [];
        if ($table === 'oc_product_option_value') {
            $optionValueMappings = $this->getOptionValueMappings($dbName);
        }

        // 处理 oc_product_option_value 的 product_option_id 映射（从 oc_product_option 的映射中获取）
        $productOptionMappings = [];
        if ($table === 'oc_product_option_value') {
            if (isset($this->tempMappings[$dbName]['oc_product_option'])) {
                $productOptionMappings = $this->tempMappings[$dbName]['oc_product_option'];
            }
        }


        $total = DB::connection($remoteConnection)
                ->table($table)
                ->whereIn('product_id', $parentIds)
                ->count();

        if ($total === 0) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        $this->info("      📊 发现 {$total} 条记录");

        $totalInserted = 0;

        $offset = 0;
        $batchSize = 1000;

        while ($offset < $total) {
            $remoteRecords = DB::connection($remoteConnection)
                    ->table($table)
                    ->whereIn('product_id', $parentIds)
                    ->orderBy('product_id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

            if ($remoteRecords->isEmpty()) {
                break;
            }

            $insertData = [];
            $batchRemoteIds = [];

            foreach ($remoteRecords as $record) {
                $recordData = (array) $record;
                $originalProductId = $recordData['product_id'];

                $localProductId = null;
                if (isset($productMappings[$originalProductId])) {
                    $localProductId = $productMappings[$originalProductId];
                } elseif (isset($productMappings[(string) $originalProductId])) {
                    $localProductId = $productMappings[(string) $originalProductId];
                } elseif (isset($productMappings[(int) $originalProductId])) {
                    $localProductId = $productMappings[(int) $originalProductId];
                }

                if ($localProductId === null) {
                    continue;
                }

                // 记录远程 ID 用于映射：使用各自表的主键
                if ($table === 'oc_product_option' && isset($record->product_option_id)) {
                    $batchRemoteIds[] = $record->product_option_id;
                } elseif ($table === 'oc_product_option_value' && isset($record->product_option_value_id)) {
                    $batchRemoteIds[] = $record->product_option_value_id;
                } else {
                    $batchRemoteIds[] = $originalProductId;
                }

                $recordData['product_id'] = $localProductId;

                // 处理 oc_product_to_category 表中的 category_id 映射
                if ($table === 'oc_product_to_category' && isset($recordData['category_id'])) {
                    $remoteCategoryId = $recordData['category_id'];
                    $localCategoryId = null;
                    if (isset($categoryMappings[$remoteCategoryId])) {
                        $localCategoryId = $categoryMappings[$remoteCategoryId];
                    } elseif (isset($categoryMappings[(string) $remoteCategoryId])) {
                        $localCategoryId = $categoryMappings[(string) $remoteCategoryId];
                    } elseif (isset($categoryMappings[(int) $remoteCategoryId])) {
                        $localCategoryId = $categoryMappings[(int) $remoteCategoryId];
                    }

                    // 如果找不到分类映射，跳过这条记录
                    if ($localCategoryId === null) {
                        $this->warn("      ⚠️  未找到分类映射 category_id: {$remoteCategoryId}，跳过");
                        continue;
                    }

                    $recordData['category_id'] = $localCategoryId;
                }

                // 处理 oc_product_option 的 option_id 映射
                if ($table === 'oc_product_option' && isset($recordData['option_id'])) {
                    $remoteOptionId = $recordData['option_id'];
                    $localOptionId = $optionMappings[$remoteOptionId] ?? $remoteOptionId;
                    $recordData['option_id'] = $localOptionId;
                }

                // 处理 oc_product_option_value 的 option_id、option_value_id 和 product_option_id 映射
                if ($table === 'oc_product_option_value') {
                    if (isset($recordData['option_id'])) {
                        $remoteOptionId = $recordData['option_id'];
                        $localOptionId = $optionMappings[$remoteOptionId] ?? $remoteOptionId;
                        $recordData['option_id'] = $localOptionId;
                    }
                    if (isset($recordData['option_value_id'])) {
                        $remoteOptionValueId = $recordData['option_value_id'];
                        $localOptionValueId = $optionValueMappings[$remoteOptionValueId] ?? $remoteOptionValueId;
                        $recordData['option_value_id'] = $localOptionValueId;
                    }
                    if (isset($recordData['product_option_id'])) {
                        $remoteProductOptionId = $recordData['product_option_id'];
                        $localProductOptionId = $productOptionMappings[(string) $remoteProductOptionId] 
                            ?? $productOptionMappings[$remoteProductOptionId] 
                            ?? $remoteProductOptionId;
                        $recordData['product_option_id'] = $localProductOptionId;
                    }
                }

                $recordData = $this->processImageUrls($table, $recordData);

                foreach ($config['primary_key'] as $pk) {
                    if (strpos($pk, '_id') !== false && !in_array($pk, ['product_id', 'language_id', 'store_id', 'category_id', 'filter_id', 'attribute_id', 'recurring_id', 'customer_group_id', 'related_id', 'download_id', 'option_id', 'option_value_id'])) {
                        unset($recordData[$pk]);
                    }
                }

                $insertData[] = $recordData;
            }

            if (!empty($insertData)) {
                $inserted = $this->smartBatchInsert($table, $insertData, $dbName, $batchRemoteIds, 'product');
                $totalInserted += $inserted;
            }

            $offset += $batchSize;
            $this->showProgress(min($offset, $total), $total, '同步中');
        }

        $this->showProgress($total, $total, '同步中');

        if ($totalInserted > 0) {
            $this->info("      ✅ 插入 {$totalInserted} 条");
        }
    }

    /**
     * 同步分类子表
     */
    private function syncCategoryChildTable(
            string $remoteConnection,
            string $dbName,
            string $table,
            array $config
    ): void {
        // 获取本次同步涉及的远程分类ID
        $currentRemoteIds = $this->currentSyncRemoteIds[$dbName]['oc_category'] ?? [];

        if (empty($currentRemoteIds)) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        // 从临时映射和持久映射中构建分类ID映射
        $categoryMappings = [];

        // 优先使用临时映射（本次同步新增的）
        if (isset($this->tempMappings[$dbName]['oc_category'])) {
            foreach ($this->tempMappings[$dbName]['oc_category'] as $remoteId => $localId) {
                $categoryMappings[(int) $remoteId] = (int) $localId;
            }
        }

        // 补充持久映射
        if (isset($this->idMappings[$dbName]['oc_category'])) {
            foreach ($this->idMappings[$dbName]['oc_category'] as $remoteId => $localId) {
                $remoteId = (int) $remoteId;
                if (!isset($categoryMappings[$remoteId])) {
                    $categoryMappings[$remoteId] = (int) $localId;
                }
            }
        }

        // 只保留本次同步涉及的ID映射
        $parentIds = [];
        foreach ($currentRemoteIds as $remoteId) {
            $remoteId = (int) $remoteId;
            if (isset($categoryMappings[$remoteId])) {
                $parentIds[] = $remoteId;
            }
        }

        if (empty($parentIds)) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        $total = DB::connection($remoteConnection)
                ->table($table)
                ->whereIn('category_id', $parentIds)
                ->count();

        if ($total === 0) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        $this->info("      📊 发现 {$total} 条记录");

        $totalInserted = 0;

        $offset = 0;
        $batchSize = 1000;

        while ($offset < $total) {
            $remoteRecords = DB::connection($remoteConnection)
                    ->table($table)
                    ->whereIn('category_id', $parentIds)
                    ->orderBy('category_id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

            if ($remoteRecords->isEmpty()) {
                break;
            }

            $insertData = [];
            $batchRemoteIds = [];

            foreach ($remoteRecords as $record) {
                $recordData = (array) $record;
                $originalCategoryId = $recordData['category_id'];

                $localId = null;
                if (isset($categoryMappings[$originalCategoryId])) {
                    $localId = $categoryMappings[$originalCategoryId];
                } elseif (isset($categoryMappings[(string) $originalCategoryId])) {
                    $localId = $categoryMappings[(string) $originalCategoryId];
                } elseif (isset($categoryMappings[(int) $originalCategoryId])) {
                    $localId = $categoryMappings[(int) $originalCategoryId];
                }

                if ($localId === null) {
                    continue;
                }

                $batchRemoteIds[] = $originalCategoryId;

                $recordData['category_id'] = $localId;

                // 处理 oc_category_path 中的 path_id（需要映射）
                if ($table === 'oc_category_path' && isset($recordData['path_id'])) {
                    $remotePathId = $recordData['path_id'];
                    $localPathId = null;
                    if (isset($categoryMappings[$remotePathId])) {
                        $localPathId = $categoryMappings[$remotePathId];
                    } elseif (isset($categoryMappings[(string) $remotePathId])) {
                        $localPathId = $categoryMappings[(string) $remotePathId];
                    } elseif (isset($categoryMappings[(int) $remotePathId])) {
                        $localPathId = $categoryMappings[(int) $remotePathId];
                    }
                    if ($localPathId !== null) {
                        $recordData['path_id'] = $localPathId;
                    }
                }

                foreach ($config['primary_key'] as $pk) {
                    if (strpos($pk, '_id') !== false && !in_array($pk, ['category_id', 'language_id', 'store_id', 'filter_id', 'path_id', 'layout_id'])) {
                        unset($recordData[$pk]);
                    }
                }

                $insertData[] = $recordData;
            }

            if (!empty($insertData)) {
                $inserted = $this->smartBatchInsert($table, $insertData, $dbName, $batchRemoteIds, 'category');
                $totalInserted += $inserted;
            }

            $offset += $batchSize;
            $this->showProgress(min($offset, $total), $total, '同步中');
        }

        $this->showProgress($total, $total, '同步中');

        if ($totalInserted > 0) {
            $this->info("      ✅ 插入 {$totalInserted} 条");
        }
    }

    /**
     * 同步选项子表（全量ID匹配模式）
     */
    private function syncOptionChildTable(
            string $remoteConnection,
            string $dbName,
            string $table,
            array $config
    ): void {
        $optionMappings = $this->getOptionMappings($dbName);
        $optionValueMappings = $this->getOptionValueMappings($dbName);

        $parentIds = array_keys($optionMappings);

        if (empty($parentIds)) {
            $this->info("      ℹ️  无数据");
            return;
        }

        $total = DB::connection($remoteConnection)
                ->table($table)
                ->count();

        if ($total === 0) {
            $this->info("      ℹ️  无数据");
            return;
        }

        $this->info("      📊 发现 {$total} 条记录");

        $totalInserted = 0;
        $totalUpdated = 0;

        $offset = 0;
        $batchSize = 1000;

        while ($offset < $total) {
            $remoteRecords = DB::connection($remoteConnection)
                    ->table($table)
                    ->orderBy($table === 'oc_option_description' ? 'option_id' : 'option_value_id')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

            if ($remoteRecords->isEmpty()) {
                break;
            }

            foreach ($remoteRecords as $record) {
                $recordData = (array) $record;

                // 处理 oc_option_description 的 option_id
                if ($table === 'oc_option_description' && isset($recordData['option_id'])) {
                    $remoteOptionId = $recordData['option_id'];
                    $localOptionId = $optionMappings[$remoteOptionId] ?? $remoteOptionId;
                    $recordData['option_id'] = $localOptionId;
                }

                // 处理 oc_option_value 的 option_id
                if ($table === 'oc_option_value' && isset($recordData['option_id'])) {
                    $remoteOptionId = $recordData['option_id'];
                    $localOptionId = $optionMappings[$remoteOptionId] ?? $remoteOptionId;
                    $recordData['option_id'] = $localOptionId;

                    $optionValueId = $recordData['option_value_id'];
                    $this->tempMappings[$dbName]['oc_option_value'][$optionValueId] = $optionValueId;
                }

                // 处理 oc_option_value_description 的 option_id 和 option_value_id
                if ($table === 'oc_option_value_description') {
                    if (isset($recordData['option_id'])) {
                        $remoteOptionId = $recordData['option_id'];
                        $localOptionId = $optionMappings[$remoteOptionId] ?? $remoteOptionId;
                        $recordData['option_id'] = $localOptionId;
                    }
                    if (isset($recordData['option_value_id'])) {
                        $remoteOptionValueId = $recordData['option_value_id'];
                        $localOptionValueId = $optionValueMappings[$remoteOptionValueId] ?? $remoteOptionValueId;
                        $recordData['option_value_id'] = $localOptionValueId;
                    }
                }

                // 移除自增主键（如果存在）
                foreach ($config['primary_key'] as $pk) {
                    if (strpos($pk, '_id') !== false && !in_array($pk, ['option_id', 'language_id', 'option_value_id'])) {
                        unset($recordData[$pk]);
                    }
                }

                // 检查是否已存在
                $exists = false;
                if ($table === 'oc_option_description') {
                    $exists = DB::table($table)
                            ->where('option_id', $recordData['option_id'])
                            ->where('language_id', $recordData['language_id'])
                            ->exists();
                } elseif ($table === 'oc_option_value') {
                    $exists = DB::table($table)
                            ->where('option_value_id', $recordData['option_value_id'])
                            ->exists();
                } elseif ($table === 'oc_option_value_description') {
                    $exists = DB::table($table)
                            ->where('option_value_id', $recordData['option_value_id'])
                            ->where('language_id', $recordData['language_id'])
                            ->exists();
                }

                if ($exists) {
                    // 更新
                    if (!$this->isDryRun) {
                        try {
                            if ($table === 'oc_option_description') {
                                DB::table($table)
                                        ->where('option_id', $recordData['option_id'])
                                        ->where('language_id', $recordData['language_id'])
                                        ->update($recordData);
                            } elseif ($table === 'oc_option_value') {
                                DB::table($table)
                                        ->where('option_value_id', $recordData['option_value_id'])
                                        ->update($recordData);
                            } elseif ($table === 'oc_option_value_description') {
                                DB::table($table)
                                        ->where('option_value_id', $recordData['option_value_id'])
                                        ->where('language_id', $recordData['language_id'])
                                        ->update($recordData);
                            }
                            $totalUpdated++;
                        } catch (\Exception $ex) {
                            $this->recordError($dbName, $table, $recordData['option_id'] ?? $recordData['option_value_id'], $ex->getMessage());
                        }
                    } else {
                        $totalUpdated++;
                    }
                } else {
                    // 插入
                    if (!$this->isDryRun) {
                        try {
                            DB::table($table)->insert($recordData);
                            $totalInserted++;
                        } catch (\Exception $ex) {
                            $this->recordError($dbName, $table, $recordData['option_id'] ?? $recordData['option_value_id'], $ex->getMessage());
                        }
                    } else {
                        $totalInserted++;
                    }
                }
            }

            $offset += $batchSize;
            $this->showProgress(min($offset, $total), $total, '同步中');
        }

        $this->showProgress($total, $total, '同步中');

        if ($totalInserted > 0 || $totalUpdated > 0) {
            $this->info("      ✅ 插入 {$totalInserted} 条, 更新 {$totalUpdated} 条");
        }
    }

    /**
     * 获取选项映射
     */
    private function getOptionMappings(string $dbName): array {
        $mappings = [];

        // 从数据库获取
        $syncRecords = DB::table('oc_sync_record')
                ->where('source_site', $dbName)
                ->where('source_table', 'oc_option')
                ->where('status', 'success')
                ->get();
        foreach ($syncRecords as $record) {
            $mappings[(int) $record->source_id] = (int) $record->target_id;
        }

        // 从文件获取
        if (isset($this->idMappings[$dbName]['oc_option'])) {
            foreach ($this->idMappings[$dbName]['oc_option'] as $remoteId => $localId) {
                $remoteId = (int) $remoteId;
                if (!isset($mappings[$remoteId])) {
                    $mappings[$remoteId] = (int) $localId;
                }
            }
        }

        // 从内存临时映射获取（本次同步新增的）
        if (isset($this->tempMappings[$dbName]['oc_option'])) {
            foreach ($this->tempMappings[$dbName]['oc_option'] as $remoteId => $localId) {
                $remoteId = (int) $remoteId;
                if (!isset($mappings[$remoteId])) {
                    $mappings[$remoteId] = (int) $localId;
                }
            }
        }

        // 如果没有映射，使用直接映射（option_id相同）
        return $mappings;
    }

    /**
     * 获取选项值映射
     */
    private function getOptionValueMappings(string $dbName): array {
        $mappings = [];

        // 从文件获取
        if (isset($this->idMappings[$dbName]['oc_option_value'])) {
            foreach ($this->idMappings[$dbName]['oc_option_value'] as $remoteId => $localId) {
                $mappings[(int) $remoteId] = (int) $localId;
            }
        }

        // 从临时映射获取
        if (isset($this->tempMappings[$dbName]['oc_option_value'])) {
            foreach ($this->tempMappings[$dbName]['oc_option_value'] as $remoteId => $localId) {
                $remoteId = (int) $remoteId;
                if (!isset($mappings[$remoteId])) {
                    $mappings[$remoteId] = (int) $localId;
                }
            }
        }

        return $mappings;
    }

    /**
     * 智能批量插入
     */
    private function smartBatchInsert(string $table, array $insertData, string $dbName = '', array $remoteIds = [], string $type = 'product'): int {
        $count = count($insertData);

        if ($count === 0) {
            return 0;
        }

        if ($this->isDryRun) {
            return $count;
        }

        $hasAutoIncrement = in_array($table, ['oc_product_image', 'oc_product_option', 'oc_product_option_value']);

        if ($hasAutoIncrement && !empty($dbName)) {
            $inserted = 0;
            foreach ($insertData as $index => $data) {
                try {
                    $newId = DB::table($table)->insertGetId($data);
                    if (isset($remoteIds[$index])) {
                        $this->tempMappings[$dbName][$table][(string) $remoteIds[$index]] = $newId;
                    }
                    $inserted++;
                } catch (\Exception $ex) {
                    // 忽略重复错误
                }
            }
            return $inserted;
        }

        try {
            DB::table($table)->insert($insertData);

            if (!empty($dbName) && !empty($remoteIds)) {
                $parentTable = $type === 'product' ? 'oc_product' : 'oc_category';
                foreach ($remoteIds as $remoteId) {
                    $stringKey = (string) $remoteId;
                    $intKey = (int) $remoteId;

                    if (isset($this->tempMappings[$dbName][$parentTable][$stringKey])) {
                        $localId = $this->tempMappings[$dbName][$parentTable][$stringKey];
                        $this->tempMappings[$dbName][$table][$stringKey] = $localId;
                    } elseif (isset($this->tempMappings[$dbName][$parentTable][$intKey])) {
                        $localId = $this->tempMappings[$dbName][$parentTable][$intKey];
                        $this->tempMappings[$dbName][$table][(string) $intKey] = $localId;
                    }
                }
            }

            return $count;
        } catch (\Exception $e) {
            $inserted = 0;
            foreach ($insertData as $data) {
                try {
                    DB::table($table)->insert($data);
                    $inserted++;
                } catch (\Exception $ex) {
                    // 忽略重复错误
                }
            }
            return $inserted;
        }
    }

    /**
     * 处理图片URL
     */
    private function processImageUrls(string $table, array $data): array {
        $imageFields = [
            'oc_product' => ['image'],
            'oc_product_image' => ['image'],
            'oc_category' => ['image'],
            'oc_option_value' => ['image'],
        ];

        $fields = $imageFields[$table] ?? [];

        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->convertImageUrl($data[$field]);
            }
        }

        if ($table === 'oc_product_description' && isset($data['description']) && !empty($data['description'])) {
            $data['description'] = $this->convertDescriptionImages($data['description']);
        }

        if ($table === 'oc_category_description' && isset($data['description']) && !empty($data['description'])) {
            $data['description'] = $this->convertDescriptionImages($data['description']);
        }

        return $data;
    }

    /**
     * 转换 description 中的图片URL
     */
    private function convertDescriptionImages(string $description): string {
        $cdnUrl = 'https://img.saveb.link/image';
        $description = htmlspecialchars_decode($description);
        $description = preg_replace_callback(
                '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
                function ($matches) use ($cdnUrl) {
                    $src = $matches[1];

                    if (strpos($src, 'catalog/') === 0) {
                        $newSrc = $cdnUrl . '/' . $src;
                    } else {
                        $pos = strpos($src, 'catalog/');
                        if ($pos !== false) {
                            $newSrc = $cdnUrl . '/' . substr($src, $pos);
                        } else {
                            $newSrc = $cdnUrl . '/' . $src;
                        }
                    }

                    return str_replace($matches[1], $newSrc, $matches[0]);
                },
                $description
        );

        $description = preg_replace_callback(
                '/background\s*:\s*url\(["\']?([^"\')]+)["\']?\)/i',
                function ($matches) use ($cdnUrl) {
                    $url = $matches[1];

                    if (strpos($url, 'catalog/') === 0) {
                        $newUrl = $cdnUrl . '/' . $url;
                    } else {
                        $pos = strpos($url, 'catalog/');
                        if ($pos !== false) {
                            $newUrl = $cdnUrl . '/' . substr($url, $pos);
                        } else {
                            $newUrl = $cdnUrl . '/' . $url;
                        }
                    }

                    return 'background: url(' . $newUrl . ')';
                },
                $description
        );

        return $description;
    }

    /**
     * 转换图片URL
     */
    private function convertImageUrl(string $imageUrl): string {
        $cdnUrl = 'https://img.saveb.link/image';

        if (strpos($imageUrl, 'catalog/') === 0) {
            return $cdnUrl . '/' . $imageUrl;
        }

        $pos = strpos($imageUrl, 'catalog/');
        if ($pos !== false) {
            return $cdnUrl . '/' . substr($imageUrl, $pos);
        }

        return $cdnUrl . '/' . $imageUrl;
    }

    // ========== 同步记录缓冲区操作 ==========

    private function addSyncRecord(string $dbName, string $table, string $sourceId, string $targetId, string $type): void {
        if ($this->isDryRun) {
            return;
        }

        DB::table('oc_sync_record')->insert([
            'source_site' => $dbName,
            'source_table' => $table,
            'source_id' => (string) $sourceId,
            'target_id' => (string) $targetId,
            'sync_type' => $type,
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function addSyncDead(string $dbName, string $table, string $sourceId, string $error, array $data): void {
        $this->syncDeadBuffer[] = [
            'sync_id' => 0,
            'source_site' => $dbName,
            'source_table' => $table,
            'source_id' => (string) $sourceId,
            'error_message' => $error,
            'retry_count' => 0,
            'last_attempt_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->flushSyncDeadBuffer();
    }


    private function flushSyncDeadBuffer(): void {
        if (count($this->syncDeadBuffer) >= self::SYNC_BUFFER_LIMIT) {
            if (!$this->isDryRun) {
                DB::table('oc_sync_dead')->insert($this->syncDeadBuffer);
            }
            $this->syncDeadBuffer = [];
        }
    }

    private function flushAllSyncBuffers(): void {
        if (!empty($this->syncRecordBuffer) && !$this->isDryRun) {
            DB::table('oc_sync_record')->insert($this->syncRecordBuffer);
        }
        if (!empty($this->syncDeadBuffer) && !$this->isDryRun) {
            DB::table('oc_sync_dead')->insert($this->syncDeadBuffer);
        }
        $this->syncRecordBuffer = [];
        $this->syncDeadBuffer = [];
    }

    // ========== 辅助方法 ==========

    /**
     * 获取上次同步的最大ID
     *
     * 从进度文件中读取上次同步时的最大ID，用于增量同步。
     *
     * @param string $dbName 数据库名称
     * @param string $table 表名
     * @return int 上次同步的最大ID
     */
    private function getLastMaxId(string $dbName, string $table): int {
        $progressFile = storage_path('app/remote_product_sync/progress.json');
        if (!file_exists($progressFile))
            return 0;
        $progress = json_decode(file_get_contents($progressFile), true) ?? [];
        return $progress[$dbName][$table]['max_id'] ?? 0;
    }

    /**
     * 更新同步进度
     *
     * 将当前同步的最大ID写入进度文件，用于下次增量同步。
     *
     * @param string $dbName 数据库名称
     * @param string $table 表名
     * @param int $maxId 当前同步的最大ID
     * @return void
     */
    private function updateProgress(string $dbName, string $table, int $maxId): void {
        if ($this->isDryRun)
            return;
        $progressFile = storage_path('app/remote_product_sync/progress.json');
        $dir = dirname($progressFile);
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) ?? [] : [];
        $progress[$dbName][$table] = ['max_id' => $maxId, 'sync_time' => date('Y-m-d H:i:s')];
        file_put_contents($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    }

    private function saveIdMappings(): void {
        if ($this->isDryRun)
            return;

        $mappingFile = storage_path('app/remote_product_sync/id_mappings.json');
        $dir = dirname($mappingFile);
        if (!is_dir($dir))
            mkdir($dir, 0755, true);

        foreach ($this->tempMappings as $dbName => $tables) {
            foreach ($tables as $table => $mappings) {
                foreach ($mappings as $remoteId => $localId) {
                    $this->idMappings[$dbName][$table][$remoteId] = $localId;
                }
            }
        }

        $this->info("📝 开始保存映射...");
        foreach ($this->idMappings as $dbName => $tables) {
            $this->info("  处理数据库: {$dbName}");

            if (isset($tables['oc_product'])) {
                $productMappings = $tables['oc_product'];
                $productCount = count($productMappings);
                $this->info("  产品主表映射数: {$productCount}");

                foreach ($this->productChildTables as $childTable => $config) {
                    if (!isset($this->idMappings[$dbName][$childTable])) {
                        $this->idMappings[$dbName][$childTable] = [];
                    }

                    // oc_product_option 和 oc_product_option_value 使用各自的主键映射（product_option_id 和 product_option_value_id）
                    // oc_option_value 使用自己的映射（option_value_id）
                    $tablesWithOwnMappings = ['oc_product_option', 'oc_product_option_value', 'oc_option_value'];
                    if (!in_array($childTable, $tablesWithOwnMappings)) {
                        foreach ($productMappings as $remoteId => $localId) {
                            if (!isset($this->idMappings[$dbName][$childTable][$remoteId])) {
                                $this->idMappings[$dbName][$childTable][$remoteId] = $localId;
                            }
                        }
                    }

                    $childCount = count($this->idMappings[$dbName][$childTable]);
                    $this->info("  产品子表 {$childTable}: {$childCount} 条映射");
                }
            }

            if (isset($tables['oc_category'])) {
                $categoryMappings = $tables['oc_category'];
                $categoryCount = count($categoryMappings);
                $this->info("  分类主表映射数: {$categoryCount}");

                foreach ($this->categoryChildTables as $childTable => $config) {
                    if (!isset($this->idMappings[$dbName][$childTable])) {
                        $this->idMappings[$dbName][$childTable] = [];
                    }

                    foreach ($categoryMappings as $remoteId => $localId) {
                        if (!isset($this->idMappings[$dbName][$childTable][$remoteId])) {
                            $this->idMappings[$dbName][$childTable][$remoteId] = $localId;
                        }
                    }

                    $childCount = count($this->idMappings[$dbName][$childTable]);
                    $this->info("  分类子表 {$childTable}: {$childCount} 条映射");
                }
            }

            if (isset($tables['oc_option'])) {
                $optionMappings = $tables['oc_option'];
                $optionCount = count($optionMappings);
                $this->info("  选项主表映射数: {$optionCount}");

                foreach ($this->optionChildTables as $childTable => $config) {
                    if (!isset($this->idMappings[$dbName][$childTable])) {
                        $this->idMappings[$dbName][$childTable] = [];
                    }

                    // oc_option_value 使用自己的 option_value_id 映射，不需要从 oc_option 复制
                    if ($childTable !== 'oc_option_value') {
                        foreach ($optionMappings as $remoteId => $localId) {
                            if (!isset($this->idMappings[$dbName][$childTable][$remoteId])) {
                                $this->idMappings[$dbName][$childTable][$remoteId] = $localId;
                            }
                        }
                    }

                    $childCount = count($this->idMappings[$dbName][$childTable]);
                    $this->info("  选项子表 {$childTable}: {$childCount} 条映射");
                }
            }

            // 保存 oc_product_option 的映射（product_option_id 映射）
            if (isset($this->idMappings[$dbName]['oc_product_option'])) {
                $poCount = count($this->idMappings[$dbName]['oc_product_option']);
                $this->info("  oc_product_option 自身映射: {$poCount} 条");
            }

            // 保存 oc_product_option_value 的映射（product_option_value_id 映射）
            if (isset($this->idMappings[$dbName]['oc_product_option_value'])) {
                $povCount = count($this->idMappings[$dbName]['oc_product_option_value']);
                $this->info("  oc_product_option_value 自身映射: {$povCount} 条");
            }

            // 保存 oc_option_value 的映射（option_value_id 映射）
            if (isset($this->idMappings[$dbName]['oc_option_value'])) {
                $ovCount = count($this->idMappings[$dbName]['oc_option_value']);
                $this->info("  oc_option_value 自身映射: {$ovCount} 条");
            }
        }

        file_put_contents($mappingFile, json_encode($this->idMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✅ 映射保存完成");
    }

    private function executeWithRetry(string $connectionName, callable $queryCallback, int $maxRetries = 3) {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $queryCallback(DB::connection($connectionName));
            } catch (QueryException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $maxRetries) {
                    $this->warn("    ⚠️  查询失败，重试 {$attempt}/{$maxRetries}...");
                    sleep(1);
                }
            }
        }

        throw $lastException;
    }

    private function showProgress(int $current, int $total, string $label = '', int $barLength = 30): void {
        if ($total === 0)
            return;

        $percentage = min(100, (int) (($current / $total) * 100));
        $filledLength = (int) ($barLength * $current / $total);
        $emptyLength = $barLength - $filledLength;

        $progressBar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $emptyLength) . ']';

        $output = sprintf("\r      %s %s %d/%d (%d%%)", $label, $progressBar, $current, $total, $percentage);

        fwrite(STDOUT, $output);

        if ($current >= $total) {
            fwrite(STDOUT, "\n");
        }

        fflush(STDOUT);
    }

    private function recordError(string $dbName, string $table, $sourceId, string $errorMessage): void {
        if ($this->isDryRun)
            return;

        $logFile = storage_path('logs/sync_error.log');
        $logMessage = date('Y-m-d H:i:s') . " [ERROR] {$dbName} {$table} {$sourceId}: {$errorMessage}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    private function generateReport(): void {
        $duration = round(microtime(true) - $this->stats['start_time'], 2);
        $this->info("\n========== 同步报告 ==========");
        $this->info("耗时: {$duration} 秒");
        $this->info("总新增: " . ($this->stats['total_inserted'] ?? 0));
        $this->info("总更新: " . ($this->stats['total_updated'] ?? 0));
        $this->info("总跳过: " . ($this->stats['total_skipped'] ?? 0));

        if (!empty($this->stats['tables'])) {
            $this->info("\n各表详情:");
            foreach ($this->stats['tables'] as $table => $data) {
                $inserted = $data['inserted'] ?? 0;
                $updated = $data['updated'] ?? 0;
                $skipped = $data['skipped'] ?? 0;
                $this->info("  {$table}: 新增 {$inserted}，更新 {$updated}，跳过 {$skipped}");
            }
        }
    }
}
