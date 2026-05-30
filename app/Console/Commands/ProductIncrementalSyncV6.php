<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

/**
 * 产品增量同步命令 V6
 * 
 * V6版本核心特性：
 * 1. 适配「本地产品ID不定时新增」场景
 * 2. 仅同步「远程有、本地无」的产品数据
 * 3. 绝对不删除、不修改、不覆盖本地已存在的数据
 * 4. 远程新增数据映射为本地全新ID
 * 5. 所有子表以本地新ID关联存储
 * 6. 采用「批量提取+预加载+集合运算」模式
 * 7. 固定远程数据单次提取3000条
 * 
 * @author CodeArts Agent
 * @version 6.0
 * @date 2026-04-21
 */
class ProductIncrementalSyncV6 extends Command
{
    protected $signature = 'product:sync-v6
        {--dry-run : 模拟运行}
        {--force : 强制全量同步}
        {--site= : 指定站点}
        {--date= : 指定日期（格式：Y-m-d）}
        {--days= : 最近N天}
        {--timeout=300 : 超时时间}';

    protected $description = '产品增量同步 V6 - 适配本地ID动态新增场景';

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

    // 子表配置（从SQL文件获取的所有相关表）
    private array $childTables = [
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

        $this->info('=== 产品增量同步 V6 ===');
        $this->info('核心原则：仅新增、不修改、不删除本地数据');

        if ($this->isDryRun) {
            $this->warn('⚠️  【模拟运行模式】');
        }

        // 初始化
        $this->loadConfig();
        $this->loadIdMappings();
        $this->initStats();

        // 构建本地产品缓存（包含多级索引）
        $this->buildLocalProductCache();

        // 同步产品数据
        $this->syncProducts();

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
            $count = count($this->idMappings['savebull_cp2026']['oc_product'] ?? []);
            $this->info("✅ 加载已有映射: {$count} 条");
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
            $this->info('  ℹ️  本地数据库为空，跳过缓存构建');
            $this->localProductCache = [
                'date_model' => [],
                'date_model_image' => [],
                'date_model_image_name' => [],
                'products' => [],
            ];
            return;
        }

        // 初始化缓存结构（支持V6的多级匹配）
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

                // 索引1：date_added + model
                if (!empty($product->date_added) && !empty($product->model)) {
                    $key1 = $product->date_added . '_' . $product->model;
                    if (!isset($this->localProductCache['date_model'][$key1])) {
                        $this->localProductCache['date_model'][$key1] = [];
                    }
                    $this->localProductCache['date_model'][$key1][] = $product->product_id;
                }

                // 索引2：date_added + model + image
                if (!empty($product->date_added) && !empty($product->model) && !empty($imagePath)) {
                    $key2 = $product->date_added . '_' . $product->model . '_' . $imagePath;
                    if (!isset($this->localProductCache['date_model_image'][$key2])) {
                        $this->localProductCache['date_model_image'][$key2] = [];
                    }
                    $this->localProductCache['date_model_image'][$key2][] = $product->product_id;
                }

                // 索引3：date_added + model + image + name
                if (!empty($product->date_added) && !empty($product->model) && !empty($imagePath) && !empty($product->name)) {
                    $key3 = $product->date_added . '_' . $product->model . '_' . $imagePath . '_' . $product->name;
                    if (!isset($this->localProductCache['date_model_image_name'][$key3])) {
                        $this->localProductCache['date_model_image_name'][$key3] = [];
                    }
                    $this->localProductCache['date_model_image_name'][$key3][] = $product->product_id;
                }

                // 产品信息
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

        $this->info("  ✅ 缓存构建完成: {$totalCount} 个产品");
    }

    /**
     * 同步产品数据
     */
    private function syncProducts(): void
    {
        $remoteDatabases = $this->config['remote_databases'] ?? [];
        $site = $this->option('site');

        foreach ($remoteDatabases as $remoteDb) {
            if ($site && $remoteDb['name'] !== $site) {
                continue;
            }

            $this->info("\n========== 处理站点: {$remoteDb['name']} ==========");

            $connectionName = $this->createRemoteConnection($remoteDb);
            $dbName = $remoteDb['name'];

            // 同步产品主表
            $this->syncProductTable($connectionName, $dbName);

            // 同步产品子表
            $this->syncProductChildTables($connectionName, $dbName);
        }
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

        // 获取上次同步的最大ID
        $lastMaxId = $this->option('force') ? 0 : $this->getLastMaxId($dbName, $table);

        // 查询远程产品总量
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

        // 分批处理（固定3000条/批）
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

            // 预加载远程产品描述（批量获取）
            $remoteIds = $batch->pluck('product_id')->toArray();
            $remoteDescriptions = $this->executeWithRetry($remoteConnection, function($db) use ($remoteIds) {
                return $db->table('oc_product_description')
                    ->whereIn('product_id', $remoteIds)
                    ->where('language_id', 1)
                    ->get()
                    ->keyBy('product_id');
            });

            // 为远程产品添加name字段
            $batchWithName = $batch->map(function($product) use ($remoteDescriptions) {
                $product->name = $remoteDescriptions[$product->product_id]->name ?? '';
                return $product;
            });

            // 批量对比和插入
            $batchResult = $this->syncProductBatch($remoteConnection, $dbName, $batchWithName);

            $inserted += $batchResult['inserted'];
            $skipped += $batchResult['skipped'];

            // 更新最大ID
            foreach ($batch as $product) {
                $maxId = max($maxId, $product->$primaryKey);
            }

            $offset += self::BATCH_SIZE;

            // 显示进度条
            $current = min($offset, $total);
            $this->showProgress($current, $total, '处理中');
        }

        $this->showProgress($total, $total, '处理中');

        $this->info("    ✅ 新增: {$inserted}, 已存在: {$skipped}");

        // 更新统计
        $this->stats['tables'][$table] = compact('inserted', 'skipped');
        $this->stats['total_inserted'] = ($this->stats['total_inserted'] ?? 0) + $inserted;
        $this->stats['total_skipped'] = ($this->stats['total_skipped'] ?? 0) + $skipped;

        // 更新进度
        $this->updateProgress($dbName, $table, $maxId);
    }

    /**
     * 批量同步产品（V6核心逻辑）
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
            // V6核心：查找本地是否存在（4级渐进式匹配）
            $localProduct = $this->findLocalProduct($product);

            if ($localProduct) {
                // 已存在，建立映射，跳过插入
                $this->tempMappings[$dbName]['oc_product'][$product->product_id] = $localProduct->product_id;
                $skipped++;
                continue;
            }

            // 不存在，准备插入
            $productData = (array)$product;
            $remoteId = $productData['product_id'];
            unset($productData['product_id']); // 移除远程ID，使用本地自增
            unset($productData['name']); // 移除name字段，name属于oc_product_description表

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
     * V6核心：4级渐进式精确匹配
     *
     * 匹配策略（与SQL逻辑一致）：
     * 01. date_added + model（必须匹配）
     * 02. image 提纯后匹配（必须匹配）
     * 03. name 完全一致（必须匹配）
     * 04. name 相似度≥95%（满足即可）
     */
    private function findLocalProduct(object $product): ?object
    {
        $imagePath = null;
        if (!empty($product->image)) {
            $imagePath = $this->extractImagePath($product->image);
        }

        $remoteName = $product->name ?? '';

        // ========== 01级：date_added + model ==========
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

        // ========== 02级：image 提纯后匹配 ==========
        if (!empty($imagePath)) {
            $key2 = $product->date_added . '_' . $product->model . '_' . $imagePath;

            if (isset($this->localProductCache['date_model_image'][$key2])) {
                $candidateIds = $this->localProductCache['date_model_image'][$key2];
                if (!is_array($candidateIds)) {
                    $candidateIds = [$candidateIds];
                }
            }
        }

        // ========== 03级：name 完全一致 ==========
        if (!empty($remoteName)) {
            $key3 = $product->date_added . '_' . $product->model . '_' . ($imagePath ?? '') . '_' . $remoteName;

            if (isset($this->localProductCache['date_model_image_name'][$key3])) {
                $ids = $this->localProductCache['date_model_image_name'][$key3];
                if (!is_array($ids)) {
                    $ids = [$ids];
                }
                return $this->getProductFromCache($ids[0]);
            }

            // ========== 04级：name 相似度≥95% ==========
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

        // 没有name匹配，返回第一个候选
        if (!empty($candidateIds)) {
            return $this->getProductFromCache($candidateIds[0]);
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
     * 计算名称相似度（百分比）
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
     * 提取图片路径（提纯）
     */
    private function extractImagePath(?string $image): string
    {
        if (empty($image)) {
            return '';
        }

        // 截取catalog/及之后部分
        $pos = strpos($image, 'catalog/');
        if ($pos !== false) {
            return substr($image, $pos);
        }

        return $image;
    }

    /**
     * 批量插入并建立ID映射
     */
    private function batchInsertWithIdMapping(string $dbName, string $table, array $insertData): int
    {
        if ($this->isDryRun) {
            return count($insertData);
        }

        $count = count($insertData);
        $inserted = 0;

        // V6核心：使用逐条插入确保ID映射正确
        foreach ($insertData as $item) {
            try {
                $newId = DB::table($table)->insertGetId($item['data']);
                $this->tempMappings[$dbName][$table][$item['remote_id']] = $newId;
                $this->updateLocalCacheFromRemote($newId, $item['data'], $item['remote_name'] ?? null);
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
     * 从远程数据更新本地缓存
     */
    private function updateLocalCacheFromRemote(int $localId, array $productData, ?string $remoteName): void
    {
        $model = $productData['model'] ?? '';
        $dateAdded = $productData['date_added'] ?? '';
        $image = $this->extractImagePath($productData['image'] ?? '');

        // 更新索引1：date_added + model
        if (!empty($dateAdded) && !empty($model)) {
            $key = $dateAdded . '_' . $model;
            if (!isset($this->localProductCache['date_model'][$key])) {
                $this->localProductCache['date_model'][$key] = [];
            }
            $this->localProductCache['date_model'][$key][] = $localId;
        }

        // 更新索引2：date_added + model + image
        if (!empty($dateAdded) && !empty($model) && !empty($image)) {
            $key = $dateAdded . '_' . $model . '_' . $image;
            if (!isset($this->localProductCache['date_model_image'][$key])) {
                $this->localProductCache['date_model_image'][$key] = [];
            }
            $this->localProductCache['date_model_image'][$key][] = $localId;
        }

        // 更新索引3：date_added + model + image + name
        if (!empty($dateAdded) && !empty($model) && !empty($image) && !empty($remoteName)) {
            $key = $dateAdded . '_' . $model . '_' . $image . '_' . $remoteName;
            if (!isset($this->localProductCache['date_model_image_name'][$key])) {
                $this->localProductCache['date_model_image_name'][$key] = [];
            }
            $this->localProductCache['date_model_image_name'][$key][] = $localId;
        }

        // 更新产品信息
        $this->localProductCache['products'][$localId] = (object)[
            'product_id' => $localId,
            'model' => $model,
            'name' => $remoteName ?? '',
            'date_added' => $dateAdded,
            'image' => $image,
        ];
    }

    /**
     * 同步产品子表
     */
    private function syncProductChildTables(string $remoteConnection, string $dbName): void
    {
        foreach ($this->childTables as $table => $config) {
            $this->info("  【同步 {$table}】");
            $this->syncChildTable($remoteConnection, $dbName, $table, $config);
        }
    }

    /**
     * 同步子表
     */
    private function syncChildTable(
        string $remoteConnection,
        string $dbName,
        string $table,
        array $config
    ): void {
        // V6核心：从同步记录中获取最新的映射（确保包含刚刚插入的产品）
        $syncRecords = DB::table('oc_sync_record')
            ->where('source_site', $dbName)
            ->where('source_table', 'oc_product')
            ->where('status', 'success')
            ->get();
        
        $allMappings = [];
        foreach ($syncRecords as $record) {
            $allMappings[(int)$record->source_id] = (int)$record->target_id;
        }
        
        $parentIds = array_keys($allMappings);

        if (empty($parentIds)) {
            $this->info("      ℹ️  无新数据");
            return;
        }

        // 获取远程数据总量
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

        // 分批获取远程数据
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
                
                // V6核心：替换为本地ID（从合并后的映射中查找）
                // 处理类型转换问题：远程ID可能是整数，映射键可能是字符串
                $localId = null;
                if (isset($allMappings[$originalProductId])) {
                    $localId = $allMappings[$originalProductId];
                } elseif (isset($allMappings[(string)$originalProductId])) {
                    $localId = $allMappings[(string)$originalProductId];
                } elseif (isset($allMappings[(int)$originalProductId])) {
                    $localId = $allMappings[(int)$originalProductId];
                }
                
                if ($localId === null) {
                    continue;
                }
                
                // 只有当记录被处理时才记录远程ID
                $batchRemoteIds[] = $originalProductId;
                
                $recordData['product_id'] = $localId;

                // 处理图片URL
                $recordData = $this->processImageUrls($table, $recordData);

                // 移除自增主键（如果存在）
                foreach ($config['primary_key'] as $pk) {
                    if (strpos($pk, '_id') !== false && !in_array($pk, ['product_id', 'language_id', 'store_id', 'category_id'])) {
                        unset($recordData[$pk]);
                    }
                }

                $insertData[] = $recordData;
            }

            // 批量插入（传递数据库名和远程ID用于记录映射）
            if (!empty($insertData)) {
                $inserted = $this->smartBatchInsert($table, $insertData, $dbName, $batchRemoteIds);
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
     * 智能批量插入（带映射记录）
     */
    private function smartBatchInsert(string $table, array $insertData, string $dbName = '', array $remoteIds = []): int
    {
        $count = count($insertData);

        if ($count === 0) {
            return 0;
        }

        if ($this->isDryRun) {
            return $count;
        }

        // 对于有自增主键的表，使用逐条插入以获取新ID并记录映射
        $hasAutoIncrement = in_array($table, ['oc_product_image', 'oc_product_option', 'oc_product_option_value']);
        
        if ($hasAutoIncrement && !empty($dbName)) {
            $inserted = 0;
            foreach ($insertData as $index => $data) {
                try {
                    $newId = DB::table($table)->insertGetId($data);
                    // 记录映射
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
            
            // 对于没有自增主键的表（如oc_product_description），记录product_id的映射
            // 这些表的映射实际上是通过product_id关联的，与主表映射相同
            if (!empty($dbName) && $table === 'oc_product_description' && !empty($remoteIds)) {
                $this->info("      📝 记录 {$table} 映射, 远程ID数: " . count($remoteIds));
                foreach ($remoteIds as $remoteId) {
                    $stringKey = (string)$remoteId;
                    $intKey = (int)$remoteId;
                    
                    // 尝试多种类型的键
                    if (isset($this->tempMappings[$dbName]['oc_product'][$stringKey])) {
                        $localId = $this->tempMappings[$dbName]['oc_product'][$stringKey];
                        $this->tempMappings[$dbName][$table][$stringKey] = $localId;
                    } elseif (isset($this->tempMappings[$dbName]['oc_product'][$intKey])) {
                        $localId = $this->tempMappings[$dbName]['oc_product'][$intKey];
                        $this->tempMappings[$dbName][$table][(string)$intKey] = $localId;
                    } else {
                        $this->info("      ⚠️  未找到映射 for remote_id: " . $remoteId);
                    }
                }
                $this->info("      ✅ 记录了 " . count($this->tempMappings[$dbName][$table] ?? []) . " 条 {$table} 映射");
            }
            
            return $count;
        } catch (\Exception $e) {
            // 回退到逐条插入
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
        ];

        $fields = $imageFields[$table] ?? [];

        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->convertImageUrl($data[$field]);
            }
        }

        // 处理 oc_product_description.description 中的图片URL
        if ($table === 'oc_product_description' && isset($data['description']) && !empty($data['description'])) {
            $data['description'] = $this->convertDescriptionImages($data['description']);
        }

        return $data;
    }

    /**
     * 转换 description 字段中的图片URL
     */
    private function convertDescriptionImages(string $description): string
    {
        $cdnUrl = 'https://img.saveb.link/image';
        
        // 先解码HTML转义字符，确保能正确匹配<img>标签
        $description = htmlspecialchars_decode($description);
        // 替换 <img> 标签中的 src 属性
        $description = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
            function($matches) use ($cdnUrl) {
                $src = $matches[1];
                
                // 提取 catalog/ 部分
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

        // 替换背景图片样式中的URL
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
        
        // 如果已经是相对路径(catalog/开头)，添加CDN前缀
        if (strpos($imageUrl, 'catalog/') === 0) {
            return $cdnUrl . '/' . $imageUrl;
        }

        // 提取catalog/后面的部分并添加CDN前缀
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

        // 首先合并新增的映射
        foreach ($this->tempMappings as $dbName => $tables) {
            foreach ($tables as $table => $mappings) {
                foreach ($mappings as $remoteId => $localId) {
                    $this->idMappings[$dbName][$table][$remoteId] = $localId;
                }
            }
        }

        // V6核心：确保所有子表都有完整的映射信息
        $this->info("📝 开始保存映射...");
        foreach ($this->idMappings as $dbName => $tables) {
            $this->info("  处理数据库: {$dbName}");
            if (isset($tables['oc_product'])) {
                $productMappings = $tables['oc_product'];
                $productCount = count($productMappings);
                $this->info("  主表映射数: {$productCount}");
                
                // 为每个子表创建映射（如果不存在）
                foreach ($this->childTables as $childTable => $config) {
                    // 如果子表还没有映射，从主表映射复制
                    if (!isset($this->idMappings[$dbName][$childTable])) {
                        $this->idMappings[$dbName][$childTable] = [];
                    }
                    
                    // 对于通过product_id关联的表（如oc_product_description），映射与主表相同
                    // 确保所有主表映射都同步到子表
                    foreach ($productMappings as $remoteId => $localId) {
                        if (!isset($this->idMappings[$dbName][$childTable][$remoteId])) {
                            $this->idMappings[$dbName][$childTable][$remoteId] = $localId;
                        }
                    }
                    
                    $childCount = count($this->idMappings[$dbName][$childTable]);
                    $this->info("  子表 {$childTable}: {$childCount} 条映射");
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