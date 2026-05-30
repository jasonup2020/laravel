<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

/**
 * 产品增量同步命令 V9
 * 
 * V9版本核心特性：
 * 1. 继承V6的产品增量同步功能
 * 2. 新增 oc_category 系列表的同步支持
 * 3. 支持产品和分类的同步映射
 * 4. 仅同步「远程有、本地无」的数据
 * 5. 绝对不删除、不修改、不覆盖本地已存在的数据
 * 6. 远程新增数据映射为本地全新ID
 * 7. 所有子表以本地新ID关联存储
 * 8. oc_product_to_category 的 category_id 会正确映射为本地ID
 * 
 * @author CodeArts Agent
 * @version 9.0 
 * @date 2026-04-27
 */
class ProductIncrementalSyncV9 extends Command
{
    protected $signature = 'product:sync-v9
        {--dry-run : 模拟运行}
        {--force : 强制全量同步}
        {--site= : 指定站点}
        {--date= : 指定日期（格式：Y-m-d）}
        {--days= : 最近N天}
        {--timeout=300 : 超时时间}
        {--sync-type= : 同步类型：product（仅产品）、category（仅分类）、both（两者都同步，默认）}';

    // 配置
    private array $config = [];
    private bool $isDryRun = false;
    private int $queryTimeout = 300;
    private const BATCH_SIZE = 3000; // 固定每次提取3000条

    // 映射数据
    private array $idMappings = [];
    private array $tempMappings = [];

    // 统计数据
    private array $stats = [];

    // 本地产品缓存（优化版：包含多级索引）
    private array $localProductCache = [];

    // 本地分类缓存
    private array $localCategoryCache = [];

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

    // 同步记录批量缓冲区
    private array $syncRecordBuffer = [];
    private array $syncLogBuffer = [];
    private array $syncConflictBuffer = [];
    private array $syncDeadBuffer = [];
    private const SYNC_BUFFER_LIMIT = 100;

    public function handle(): int
    {
        $startTime = microtime(true);

        // 设置PHP运行参数
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);

        $this->isDryRun = $this->option('dry-run');
        $this->queryTimeout = (int)$this->option('timeout');

        $this->info('=== 产品增量同步 V9 ===');
        $this->info('核心原则：仅新增、不修改、不删除本地数据');

        if ($this->isDryRun) {
            $this->warn('⚠️  【模拟运行模式】');
        }

        // 初始化
        $this->loadConfig();
        $this->loadIdMappings();
        $this->initStats();

        // 构建缓存
        $this->buildLocalCategoryCache();
        $this->buildLocalProductCache();

        // 获取同步类型
        $syncType = $this->option('sync-type') ?? 'both';

        // 根据同步类型执行同步
        $this->info("同步类型: {$syncType}");

        if ($syncType === 'category' || $syncType === 'both') {
            $this->syncCategories();
        }

        if ($syncType === 'product' || $syncType === 'both') {
            // 如果仅同步产品，需要确保分类映射已加载
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
     * 加载配置
     */
    private function loadConfig(): void
    {
        $this->config = config('remote_databases_products');
    }

    /**
     * 加载ID映射
     */
    private function loadIdMappings(): void
    {
        $mappingFile = storage_path('app/remote_product_sync/id_mappings.json');
        if (file_exists($mappingFile)) {
            $this->idMappings = json_decode(file_get_contents($mappingFile), true) ?? [];
            $productCount = count($this->idMappings['savebull_cp2026']['oc_product'] ?? []);
            $categoryCount = count($this->idMappings['savebull_cp2026']['oc_category'] ?? []);
            $this->info("✅ 加载已有映射: 产品 {$productCount} 条, 分类 {$categoryCount} 条");
        }
        $this->tempMappings = [];
    }

    /**
     * 初始化统计
     */
    private function initStats(): void
    {
        $this->stats = [
            'start_time' => microtime(true),
            'tables' => [],
            'total_inserted' => 0,
            'total_skipped' => 0,
        ];
    }

    /**
     * 构建本地产品缓存（包含多级索引）
     */
    private function buildLocalProductCache(): void
    {
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
            'date_model' => [],              // date_added + model
            'date_model_image' => [],        // date_added + model + image
            'date_model_image_name' => [],   // date_added + model + image + name
            'products' => [],                // 产品详情
        ];

        $batchSize = 1000;
        $offset = 0;
        $totalCount = 0;

        $this->info("  📦 开始构建缓存，每批 {$batchSize} 条");

        while (true) {
            $products = DB::table('oc_product as p')
                ->leftJoin('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
                ->where(function($query) {
                    $query->where('pd.language_id', 1)->orWhereNull('pd.language_id');
                })
                ->select(
                    'p.product_id',
                    'p.model',
                    'p.image',
                    'p.date_added',
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

                $this->localProductCache['products'][$product->product_id] = (object)[
                    'product_id' => $product->product_id,
                    'model' => $product->model,
                    'name' => $product->name,
                    'date_added' => $product->date_added,
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
    private function buildLocalCategoryCache(): void
    {
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
            'date_name' => [],              // date_added + name
            'date_name_parent' => [],       // date_added + name + parent_id
            'categories' => [],             // 分类详情
        ];

        $batchSize = 1000;
        $offset = 0;
        $totalCount = 0;

        $this->info("  📦 开始构建分类缓存，每批 {$batchSize} 条");

        while (true) {
            $categories = DB::table('oc_category as c')
                ->leftJoin('oc_category_description as cd', 'c.category_id', '=', 'cd.category_id')
                ->where(function($query) {
                    $query->where('cd.language_id', 1)->orWhereNull('cd.language_id');
                })
                ->select(
                    'c.category_id',
                    'c.parent_id',
                    'c.date_added',
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

                $this->localCategoryCache['categories'][$category->category_id] = (object)[
                    'category_id' => $category->category_id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'date_added' => $category->date_added,
                ];
            }

            $totalCount += $categories->count();
            $offset += $batchSize;
            unset($categories);
        }

        $this->info("  ✅ 分类缓存构建完成: {$totalCount} 个分类");
    }

    /**
     * 同步产品数据
     */
    private function syncProducts(): void
    {
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
    private function syncCategories(): void
    {
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
     * 创建远程连接
     */
    private function createRemoteConnection(array $remoteDb): string
    {
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
     * 同步产品主表
     */
    private function syncProductTable(string $remoteConnection, string $dbName): void
    {
        $this->info('  【同步 oc_product 主表】');

        $table = 'oc_product';
        $primaryKey = 'product_id';

        $lastMaxId = $this->option('force') ? 0 : $this->getLastMaxId($dbName, $table);

        $total = $this->executeWithRetry($remoteConnection, function($db) use ($table, $primaryKey, $lastMaxId) {
            $query = $db->table($table);
            if (!$this->option('force')) {
                $query->where($primaryKey, '>', $lastMaxId);
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
        $skipped = 0;
        $maxId = $lastMaxId;

        $offset = 0;
        while ($offset < $total) {
            $batch = $this->executeWithRetry($remoteConnection, function($db) use ($table, $primaryKey, $lastMaxId, $offset) {
                return $db->table($table)
                    ->when(!$this->option('force'), fn($q) => $q->where($primaryKey, '>', $lastMaxId))
                    ->orderBy($primaryKey)
                    ->skip($offset)
                    ->take(self::BATCH_SIZE)
                    ->get();
            });

            if ($batch->isEmpty()) {
                break;
            }

            $remoteIds = $batch->pluck('product_id')->toArray();
            $remoteDescriptions = $this->executeWithRetry($remoteConnection, function($db) use ($remoteIds) {
                return $db->table('oc_product_description')
                    ->whereIn('product_id', $remoteIds)
                    ->where('language_id', 1)
                    ->get()
                    ->keyBy('product_id');
            });

            $batchWithName = $batch->map(function($product) use ($remoteDescriptions) {
                $product->name = $remoteDescriptions[$product->product_id]->name ?? '';
                return $product;
            });

            $batchResult = $this->syncProductBatch($remoteConnection, $dbName, $batchWithName);

            $inserted += $batchResult['inserted'];
            $skipped += $batchResult['skipped'];

            foreach ($batch as $product) {
                $maxId = max($maxId, $product->$primaryKey);
            }

            $offset += self::BATCH_SIZE;
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 已存在: {$skipped}");

        $this->stats['tables'][$table] = compact('inserted', 'skipped');
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_skipped'] = ($this->stats['total_skipped'] ?? 0) + $skipped;

        $this->updateProgress($dbName, $table, $maxId);
    }

    /**
     * 同步分类主表
     */
    private function syncCategoryTable(string $remoteConnection, string $dbName): void
    {
        $this->info('  【同步 oc_category 主表】');

        $table = 'oc_category';
        $primaryKey = 'category_id';

        $lastMaxId = $this->option('force') ? 0 : $this->getLastMaxId($dbName, $table);

        $total = $this->executeWithRetry($remoteConnection, function($db) use ($table, $primaryKey, $lastMaxId) {
            $query = $db->table($table);
            if (!$this->option('force')) {
                $query->where($primaryKey, '>', $lastMaxId);
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
        $skipped = 0;
        $maxId = $lastMaxId;

        $offset = 0;
        while ($offset < $total) {
            $batch = $this->executeWithRetry($remoteConnection, function($db) use ($table, $primaryKey, $lastMaxId, $offset) {
                return $db->table($table)
                    ->when(!$this->option('force'), fn($q) => $q->where($primaryKey, '>', $lastMaxId))
                    ->orderBy($primaryKey)
                    ->skip($offset)
                    ->take(self::BATCH_SIZE)
                    ->get();
            });

            if ($batch->isEmpty()) {
                break;
            }

            $remoteIds = $batch->pluck('category_id')->toArray();
            $remoteDescriptions = $this->executeWithRetry($remoteConnection, function($db) use ($remoteIds) {
                return $db->table('oc_category_description')
                    ->whereIn('category_id', $remoteIds)
                    ->where('language_id', 1)
                    ->get()
                    ->keyBy('category_id');
            });

            $batchWithName = $batch->map(function($category) use ($remoteDescriptions) {
                $category->name = $remoteDescriptions[$category->category_id]->name ?? '';
                return $category;
            });

            $batchResult = $this->syncCategoryBatch($remoteConnection, $dbName, $batchWithName);

            $inserted += $batchResult['inserted'];
            $skipped += $batchResult['skipped'];

            foreach ($batch as $category) {
                $maxId = max($maxId, $category->$primaryKey);
            }

            $offset += self::BATCH_SIZE;
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 已存在: {$skipped}");

        $this->stats['tables'][$table] = compact('inserted', 'skipped');
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_skipped'] = ($this->stats['total_skipped'] ?? 0) + $skipped;

        $this->updateProgress($dbName, $table, $maxId);
    }

    /**
     * 批量同步产品
     */
    private function syncProductBatch(
        string $remoteConnection,
        string $dbName,
        \Illuminate\Support\Collection $batch
    ): array {
        $inserted = 0;
        $skipped = 0;
        $insertData = [];

        foreach ($batch as $product) {
            $localProduct = $this->findLocalProduct($product);

            if ($localProduct) {
                $this->tempMappings[$dbName]['oc_product'][$product->product_id] = $localProduct->product_id;
                $skipped++;
                continue;
            }

            $productData = (array)$product;
            $remoteId = $productData['product_id'];
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
            $inserted = $this->batchInsertWithIdMapping($dbName, 'oc_product', $insertData);
        }

        return compact('inserted', 'skipped');
    }

    /**
     * 批量同步分类
     */
    private function syncCategoryBatch(
        string $remoteConnection,
        string $dbName,
        \Illuminate\Support\Collection $batch
    ): array {
        $inserted = 0;
        $skipped = 0;
        $insertData = [];

        foreach ($batch as $category) {
            $localCategory = $this->findLocalCategory($category);

            if ($localCategory) {
                $this->tempMappings[$dbName]['oc_category'][$category->category_id] = $localCategory->category_id;
                $skipped++;
                continue;
            }

            $categoryData = (array)$category;
            $remoteId = $categoryData['category_id'];
            unset($categoryData['category_id']);
            unset($categoryData['name']);

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

        return compact('inserted', 'skipped');
    }

    /**
     * 查找本地产品
     */
    private function findLocalProduct(object $product): ?object
    {
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
                    if ($similarity >= 95) {
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
    private function findLocalCategory(object $category): ?object
    {
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
    private function getProductFromCache(int $productId): ?object
    {
        return $this->localProductCache['products'][$productId] ?? null;
    }

    /**
     * 从缓存获取分类对象
     */
    private function getCategoryFromCache(int $categoryId): ?object
    {
        return $this->localCategoryCache['categories'][$categoryId] ?? null;
    }

    /**
     * 计算名称相似度
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
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
    private function extractImagePath(?string $image): string
    {
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
    private function batchInsertWithIdMapping(string $dbName, string $table, array $insertData): int
    {
        if ($this->isDryRun) {
            return count($insertData);
        }

        $count = count($insertData);
        $inserted = 0;

        foreach ($insertData as $item) {
            try {
                $newId = DB::table($table)->insertGetId($item['data']);
                $this->tempMappings[$dbName][$table][$item['remote_id']] = $newId;
                $this->updateLocalProductCacheFromRemote($newId, $item['data'], $item['remote_name'] ?? null);
                $this->addSyncRecord($dbName, $table, $item['remote_id'], $newId, 'insert');
                $inserted++;
            } catch (\Exception $ex) {
                $this->recordError($dbName, $table, $item['remote_id'], $ex->getMessage());
                $this->addSyncDead($dbName, $table, $item['remote_id'], $ex->getMessage(), $item['data']);
            }
        }

        return $inserted;
    }

    /**
     * 批量插入分类并建立ID映射
     */
    private function batchInsertCategoryWithIdMapping(string $dbName, string $table, array $insertData): int
    {
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
                $this->recordError($dbName, $table, $item['remote_id'], $ex->getMessage());
                $this->addSyncDead($dbName, $table, $item['remote_id'], $ex->getMessage(), $item['data']);
            }
        }

        return $inserted;
    }

    /**
     * 更新本地产品缓存
     */
    private function updateLocalProductCacheFromRemote(int $localId, array $productData, ?string $remoteName): void
    {
        $model = $productData['model'] ?? '';
        $dateAdded = $productData['date_added'] ?? '';
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

        $this->localProductCache['products'][$localId] = (object)[
            'product_id' => $localId,
            'model' => $model,
            'name' => $remoteName ?? '',
            'date_added' => $dateAdded,
            'image' => $image,
        ];
    }

    /**
     * 更新本地分类缓存
     */
    private function updateLocalCategoryCacheFromRemote(int $localId, array $categoryData, ?string $remoteName): void
    {
        $parentId = $categoryData['parent_id'] ?? 0;
        $dateAdded = $categoryData['date_added'] ?? '';

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

        $this->localCategoryCache['categories'][$localId] = (object)[
            'category_id' => $localId,
            'parent_id' => $parentId,
            'name' => $remoteName ?? '',
            'date_added' => $dateAdded,
        ];
    }

    /**
     * 同步产品子表
     */
    private function syncProductChildTables(string $remoteConnection, string $dbName): void
    {
        foreach ($this->productChildTables as $table => $config) {
            $this->info("  【同步 {$table}】");
            $this->syncProductChildTable($remoteConnection, $dbName, $table, $config);
        }
    }

    /**
     * 同步分类子表
     */
    private function syncCategoryChildTables(string $remoteConnection, string $dbName): void
    {
        foreach ($this->categoryChildTables as $table => $config) {
            $this->info("  【同步 {$table}】");
            $this->syncCategoryChildTable($remoteConnection, $dbName, $table, $config);
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
        // 从数据库 oc_sync_record 表获取产品映射
        $productMappings = [];
        $syncRecords = DB::table('oc_sync_record')
            ->where('source_site', $dbName)
            ->where('source_table', 'oc_product')
            ->where('status', 'success')
            ->get();
        foreach ($syncRecords as $record) {
            $productMappings[(int)$record->source_id] = (int)$record->target_id;
        }
        
        // 从 id_mappings.json 文件补充产品映射
        if (isset($this->idMappings[$dbName]['oc_product'])) {
            foreach ($this->idMappings[$dbName]['oc_product'] as $remoteId => $localId) {
                $remoteId = (int)$remoteId;
                if (!isset($productMappings[$remoteId])) {
                    $productMappings[$remoteId] = (int)$localId;
                }
            }
        }
        
        $parentIds = array_keys($productMappings);

        if (empty($parentIds)) {
            $this->info("      ℹ️  无新数据");
            return;
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
                $categoryMappings[(int)$record->source_id] = (int)$record->target_id;
            }
            
            // 从 id_mappings.json 文件补充分类映射
            if (isset($this->idMappings[$dbName]['oc_category'])) {
                foreach ($this->idMappings[$dbName]['oc_category'] as $remoteId => $localId) {
                    $remoteId = (int)$remoteId;
                    if (!isset($categoryMappings[$remoteId])) {
                        $categoryMappings[$remoteId] = (int)$localId;
                    }
                }
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
                $recordData = (array)$record;
                $originalProductId = $recordData['product_id'];
                
                $localProductId = null;
                if (isset($productMappings[$originalProductId])) {
                    $localProductId = $productMappings[$originalProductId];
                } elseif (isset($productMappings[(string)$originalProductId])) {
                    $localProductId = $productMappings[(string)$originalProductId];
                } elseif (isset($productMappings[(int)$originalProductId])) {
                    $localProductId = $productMappings[(int)$originalProductId];
                }
                
                if ($localProductId === null) {
                    continue;
                }
                
                $batchRemoteIds[] = $originalProductId;
                
                $recordData['product_id'] = $localProductId;

                // 处理 oc_product_to_category 表中的 category_id 映射
                if ($table === 'oc_product_to_category' && isset($recordData['category_id'])) {
                    $remoteCategoryId = $recordData['category_id'];
                    $localCategoryId = null;
                    if (isset($categoryMappings[$remoteCategoryId])) {
                        $localCategoryId = $categoryMappings[$remoteCategoryId];
                    } elseif (isset($categoryMappings[(string)$remoteCategoryId])) {
                        $localCategoryId = $categoryMappings[(string)$remoteCategoryId];
                    } elseif (isset($categoryMappings[(int)$remoteCategoryId])) {
                        $localCategoryId = $categoryMappings[(int)$remoteCategoryId];
                    }
                    
                    // 如果找不到分类映射，跳过这条记录
                    if ($localCategoryId === null) {
                        $this->warn("      ⚠️  未找到分类映射 category_id: {$remoteCategoryId}，跳过");
                        continue;
                    }
                    
                    $recordData['category_id'] = $localCategoryId;
                }

                $recordData = $this->processImageUrls($table, $recordData);

                foreach ($config['primary_key'] as $pk) {
                    if (strpos($pk, '_id') !== false && !in_array($pk, ['product_id', 'language_id', 'store_id', 'category_id', 'filter_id', 'attribute_id', 'recurring_id', 'customer_group_id', 'related_id', 'download_id'])) {
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
        $syncRecords = DB::table('oc_sync_record')
            ->where('source_site', $dbName)
            ->where('source_table', 'oc_category')
            ->where('status', 'success')
            ->get();
        
        $categoryMappings = [];
        foreach ($syncRecords as $record) {
            $categoryMappings[(int)$record->source_id] = (int)$record->target_id;
        }
        
        $parentIds = array_keys($categoryMappings);

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
                $recordData = (array)$record;
                $originalCategoryId = $recordData['category_id'];
                
                $localId = null;
                if (isset($categoryMappings[$originalCategoryId])) {
                    $localId = $categoryMappings[$originalCategoryId];
                } elseif (isset($categoryMappings[(string)$originalCategoryId])) {
                    $localId = $categoryMappings[(string)$originalCategoryId];
                } elseif (isset($categoryMappings[(int)$originalCategoryId])) {
                    $localId = $categoryMappings[(int)$originalCategoryId];
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
                    } elseif (isset($categoryMappings[(string)$remotePathId])) {
                        $localPathId = $categoryMappings[(string)$remotePathId];
                    } elseif (isset($categoryMappings[(int)$remotePathId])) {
                        $localPathId = $categoryMappings[(int)$remotePathId];
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
     * 智能批量插入
     */
    private function smartBatchInsert(string $table, array $insertData, string $dbName = '', array $remoteIds = [], string $type = 'product'): int
    {
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
                        $this->tempMappings[$dbName][$table][(string)$remoteIds[$index]] = $newId;
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
                    $stringKey = (string)$remoteId;
                    $intKey = (int)$remoteId;
                    
                    if (isset($this->tempMappings[$dbName][$parentTable][$stringKey])) {
                        $localId = $this->tempMappings[$dbName][$parentTable][$stringKey];
                        $this->tempMappings[$dbName][$table][$stringKey] = $localId;
                    } elseif (isset($this->tempMappings[$dbName][$parentTable][$intKey])) {
                        $localId = $this->tempMappings[$dbName][$parentTable][$intKey];
                        $this->tempMappings[$dbName][$table][(string)$intKey] = $localId;
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
    private function processImageUrls(string $table, array $data): array
    {
        $imageFields = [
            'oc_product' => ['image'],
            'oc_product_image' => ['image'],
            'oc_category' => ['image'],
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
    private function convertDescriptionImages(string $description): string
    {
        $cdnUrl = 'https://img.saveb.link/image';
        
        $description = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
            function($matches) use ($cdnUrl) {
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
            function($matches) use ($cdnUrl) {
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
    private function convertImageUrl(string $imageUrl): string
    {
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

    private function addSyncRecord(string $dbName, string $table, string $sourceId, string $targetId, string $type): void
    {
        if ($this->isDryRun) {
            return;
        }
        
        DB::table('oc_sync_record')->insert([
            'source_site' => $dbName,
            'source_table' => $table,
            'source_id' => (string)$sourceId,
            'target_id' => (string)$targetId,
            'sync_type' => $type,
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function addSyncDead(string $dbName, string $table, string $sourceId, string $error, array $data): void
    {
        $this->syncDeadBuffer[] = [
            'sync_id' => 0,
            'source_site' => $dbName,
            'source_table' => $table,
            'source_id' => (string)$sourceId,
            'error_message' => $error,
            'retry_count' => 0,
            'last_attempt_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->flushSyncDeadBuffer();
    }

    private function flushSyncRecordBuffer(): void
    {
        if (count($this->syncRecordBuffer) >= self::SYNC_BUFFER_LIMIT) {
            if (!$this->isDryRun) {
                DB::table('oc_sync_record')->insert($this->syncRecordBuffer);
            }
            $this->syncRecordBuffer = [];
        }
    }

    private function flushSyncDeadBuffer(): void
    {
        if (count($this->syncDeadBuffer) >= self::SYNC_BUFFER_LIMIT) {
            if (!$this->isDryRun) {
                DB::table('oc_sync_dead')->insert($this->syncDeadBuffer);
            }
            $this->syncDeadBuffer = [];
        }
    }

    private function flushAllSyncBuffers(): void
    {
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

    private function getLastMaxId(string $dbName, string $table): int
    {
        $progressFile = storage_path('app/remote_product_sync/progress.json');
        if (!file_exists($progressFile)) return 0;
        $progress = json_decode(file_get_contents($progressFile), true) ?? [];
        return $progress[$dbName][$table]['max_id'] ?? 0;
    }

    private function updateProgress(string $dbName, string $table, int $maxId): void
    {
        if ($this->isDryRun) return;
        $progressFile = storage_path('app/remote_product_sync/progress.json');
        $dir = dirname($progressFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) ?? [] : [];
        $progress[$dbName][$table] = ['max_id' => $maxId, 'sync_time' => date('Y-m-d H:i:s')];
        file_put_contents($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    }

    private function saveIdMappings(): void
    {
        if ($this->isDryRun) return;

        $mappingFile = storage_path('app/remote_product_sync/id_mappings.json');
        $dir = dirname($mappingFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

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
                    
                    foreach ($productMappings as $remoteId => $localId) {
                        if (!isset($this->idMappings[$dbName][$childTable][$remoteId])) {
                            $this->idMappings[$dbName][$childTable][$remoteId] = $localId;
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
        }

        file_put_contents($mappingFile, json_encode($this->idMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("✅ 映射保存完成");
    }

    private function executeWithRetry(string $connectionName, callable $queryCallback, int $maxRetries = 3)
    {
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

    private function showProgress(int $current, int $total, string $label = '', int $barLength = 30): void
    {
        if ($total === 0) return;

        $percentage = min(100, (int)(($current / $total) * 100));
        $filledLength = (int)($barLength * $current / $total);
        $emptyLength = $barLength - $filledLength;

        $progressBar = '[' . str_repeat('█', $filledLength) . str_repeat('░', $emptyLength) . ']';

        $output = sprintf("\r      %s %s %d/%d (%d%%)", $label, $progressBar, $current, $total, $percentage);

        fwrite(STDOUT, $output);

        if ($current >= $total) {
            fwrite(STDOUT, "\n");
        }

        fflush(STDOUT);
    }

    private function recordError(string $dbName, string $table, $sourceId, string $errorMessage): void
    {
        if ($this->isDryRun) return;

        $logFile = storage_path('logs/sync_error.log');
        $logMessage = date('Y-m-d H:i:s') . " [ERROR] {$dbName} {$table} {$sourceId}: {$errorMessage}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    private function generateReport(): void
    {
        $duration = round(microtime(true) - $this->stats['start_time'], 2);
        $this->info("\n========== 同步报告 ==========");
        $this->info("耗时: {$duration} 秒");
        $this->info("总新增: " . ($this->stats['total_inserted'] ?? 0));
        $this->info("总跳过: " . ($this->stats['total_skipped'] ?? 0));

        if (!empty($this->stats['tables'])) {
            $this->info("\n各表详情:");
            foreach ($this->stats['tables'] as $table => $data) {
                $this->info("  {$table}: 新增 {$data['inserted']}，跳过 {$data['skipped']}");
            }
        }
    }
}