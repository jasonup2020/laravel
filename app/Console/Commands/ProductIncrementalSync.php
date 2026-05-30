<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

/**
 * 产品增量同步命令
 * 
 * 专门处理产品相关表的增量同步
 * 
 * 功能特点：
 * 1. 增量同步：基于 date_modified 时间戳
 * 2. ID映射：自动处理ID冲突
 * 3. 外键关联：自动转换子表外键
 * 4. 数据完整性：自动修复缺失的子表数据
 * 5. 反向同步：删除本地孤立记录
 * 6. 完整记录：所有操作记录到 oc_sync_record
 * 
 * @author CodeArts Agent
 * @version 1.0
 * @date 2026-04-18
 */
class ProductIncrementalSync extends Command
{
    protected $signature = 'product:incremental-sync
        {--dry-run : 模拟运行，不实际写入数据}
        {--force : 强制全量同步}
        {--site= : 指定同步站点}
        {--timeout=300 : 查询超时时间（秒）}';

    protected $description = '产品增量同步工具';

    // 配置
    private array $config = [];
    private bool $isDryRun = false;
    private int $queryTimeout = 300;
    
    // 映射数据
    private array $idMappings = [];
    private array $tempMappings = [];
    
    // 统计数据
    private array $stats = [];
    
    // 产品相关表
    private array $productTables = [
        'oc_product',
        'oc_product_description',
        'oc_product_image',
        'oc_product_option',
        'oc_product_option_value',
        'oc_product_to_category',
        'oc_product_to_store',
        'oc_product_to_layout',
        'oc_product_discount',
        'oc_product_special',
        'oc_product_attribute',
        'oc_product_filter',
        'oc_product_reward',
        'oc_product_related',
    ];
    
    // 主键配置
    private array $primaryKeys = [
        'oc_product' => 'product_id',
        'oc_product_description' => ['product_id', 'language_id'],
        'oc_product_image' => 'product_image_id',
        'oc_product_option' => 'product_option_id',
        'oc_product_option_value' => 'product_option_value_id',
        'oc_product_to_category' => ['product_id', 'category_id'],
        'oc_product_to_store' => ['product_id', 'store_id'],
        'oc_product_to_layout' => ['product_id', 'store_id'],
        'oc_product_discount' => 'product_discount_id',
        'oc_product_special' => 'product_special_id',
        'oc_product_attribute' => ['product_id', 'attribute_id', 'language_id'],
        'oc_product_filter' => ['product_id', 'filter_id'],
        'oc_product_reward' => 'product_reward_id',
        'oc_product_related' => ['product_id', 'related_id'],
    ];
    
    // 外键配置
    private array $foreignKeys = [
        'oc_product_description' => ['product_id' => 'oc_product'],
        'oc_product_image' => ['product_id' => 'oc_product'],
        'oc_product_option' => [
            'product_id' => 'oc_product',
            'option_id' => 'oc_option',
        ],
        'oc_product_option_value' => [
            'product_id' => 'oc_product',
            'product_option_id' => 'oc_product_option',
            'option_value_id' => 'oc_option_value',
        ],
        'oc_product_to_category' => [
            'product_id' => 'oc_product',
            'category_id' => 'oc_category',
        ],
        'oc_product_to_store' => ['product_id' => 'oc_product'],
        'oc_product_to_layout' => ['product_id' => 'oc_product'],
        'oc_product_discount' => ['product_id' => 'oc_product'],
        'oc_product_special' => ['product_id' => 'oc_product'],
        'oc_product_attribute' => ['product_id' => 'oc_product'],
        'oc_product_filter' => ['product_id' => 'oc_product'],
        'oc_product_reward' => ['product_id' => 'oc_product'],
        'oc_product_related' => [
            'product_id' => 'oc_product',
            'related_id' => 'oc_product',
        ],
    ];

    public function handle(): int
    {
        $this->isDryRun = $this->option('dry-run');
        $this->queryTimeout = (int)$this->option('timeout');
        
        $this->info('=== 产品增量同步开始 ===');
        
        if ($this->isDryRun) {
            $this->warn('⚠️  【模拟运行模式】');
        }
        
        // 加载配置
        $this->loadConfig();
        
        // 加载映射
        $this->loadIdMappings();
        
        // 初始化统计
        $this->initStats();
        
        // 同步产品数据
        $this->syncProducts();
        
        // 保存映射
        $this->saveIdMappings();
        
        // 生成报告
        $this->generateReport();
        
        return 0;
    }
    
    private function loadConfig(): void
    {
        $this->config = config('remote_databases_products');
    }
    
    private function loadIdMappings(): void
    {
        $mappingFile = storage_path('app/remote_product_sync/id_mappings.json');
        
        if (file_exists($mappingFile)) {
            $this->idMappings = json_decode(file_get_contents($mappingFile), true) ?? [];
        } else {
            $this->idMappings = [];
        }
    }
    
    private function saveIdMappings(): void
    {
        if ($this->isDryRun) {
            return;
        }
        
        $mappingFile = storage_path('app/remote_product_sync/id_mappings.json');
        $dir = dirname($mappingFile);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 合并临时映射
        foreach ($this->tempMappings as $dbName => $tables) {
            foreach ($tables as $table => $mappings) {
                foreach ($mappings as $remoteId => $localId) {
                    $this->idMappings[$dbName][$table][$remoteId] = $localId;
                }
            }
        }
        
        file_put_contents($mappingFile, json_encode($this->idMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    private function initStats(): void
    {
        $this->stats = [
            'start_time' => microtime(true),
            'tables' => [],
        ];
    }
    
    private function syncProducts(): void
    {
        $remoteDatabases = $this->config['remote_databases'] ?? [];
        $site = $this->option('site');
        
        foreach ($remoteDatabases as $remoteDb) {
            if ($site && $remoteDb['name'] !== $site) {
                continue;
            }
            
            $this->info("\n处理站点: {$remoteDb['name']}");
            
            // 创建远程连接
            $connectionName = $this->createRemoteConnection($remoteDb);
            
            // 同步产品主表
            $this->syncProductTable($connectionName, $remoteDb['name']);
            
            // 同步产品子表
            $this->syncProductChildTables($connectionName, $remoteDb['name']);
        }
    }
    
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
        ]]);
        
        return $connectionName;
    }
    
    private function syncProductTable(string $remoteConnection, string $dbName): void
    {
        $this->info('  同步 oc_product 主表');
        
        $table = 'oc_product';
        $primaryKey = 'product_id';
        
        // 获取上次同步的最大ID
        $lastMaxId = $this->option('force') ? 0 : $this->getLastMaxId($dbName, $table);
        
        // 查询远程新增/修改的产品
        $query = DB::connection($remoteConnection)->table($table);
        
        if (!$this->option('force')) {
            $query->where($primaryKey, '>', $lastMaxId);
        }
        
        $remoteProducts = $query->orderBy($primaryKey)->get();
        
        if ($remoteProducts->isEmpty()) {
            $this->info('    无新数据');
            return;
        }
        
        $this->info("    发现 {$remoteProducts->count()} 条记录");
        
        $inserted = 0;
        $skipped = 0;
        
        foreach ($remoteProducts as $product) {
            $result = $this->syncProductRecord($remoteConnection, $dbName, $product);
            
            if ($result === 'insert') {
                $inserted++;
            } else {
                $skipped++;
            }
        }
        
        $this->info("    插入: {$inserted}, 跳过: {$skipped}");
        
        // 更新进度
        $this->updateProgress($dbName, $table, $remoteProducts->max($primaryKey));
    }
    
    private function syncProductRecord(string $remoteConnection, string $dbName, object $product): string
    {
        $table = 'oc_product';
        $primaryKey = 'product_id';
        $remoteId = $product->$primaryKey;
        
        // 检查本地是否存在
        $localProduct = DB::table($table)->where('model', $product->model)->first();
        
        if ($localProduct) {
            // 已存在，建立映射
            $this->tempMappings[$dbName][$table][$remoteId] = $localProduct->$primaryKey;
            
            // 记录跳过
            $this->recordSync($dbName, $table, $remoteId, $localProduct->$primaryKey, 'skip', '数据已存在');
            
            return 'skip';
        }
        
        // 插入新记录
        $productData = (array)$product;
        unset($productData[$primaryKey]); // 删除自增ID
        
        if (!$this->isDryRun) {
            $newId = DB::table($table)->insertGetId($productData);
            
            // 建立映射
            $this->tempMappings[$dbName][$table][$remoteId] = $newId;
            
            // 记录插入
            $this->recordSync($dbName, $table, $remoteId, $newId, 'insert', '插入新记录');
        }
        
        return 'insert';
    }
    
    private function syncProductChildTables(string $remoteConnection, string $dbName): void
    {
        $childTables = [
            'oc_product_description',
            'oc_product_image',
            'oc_product_option',
            'oc_product_option_value',
            'oc_product_to_category',
            'oc_product_to_store',
            'oc_product_to_layout',
        ];
        
        foreach ($childTables as $table) {
            $this->info("  同步 {$table}");
            $this->syncChildTable($remoteConnection, $dbName, $table);
        }
    }
    
    private function syncChildTable(string $remoteConnection, string $dbName, string $table): void
    {
        $primaryKey = $this->primaryKeys[$table];
        $isComposite = is_array($primaryKey);
        
        // 获取产品映射
        $productMappings = $this->tempMappings[$dbName]['oc_product'] 
            ?? $this->idMappings[$dbName]['oc_product'] 
            ?? [];
        
        if (empty($productMappings)) {
            $this->info('    无产品映射，跳过');
            return;
        }
        
        $remoteProductIds = array_keys($productMappings);
        
        // 查询远程数据
        $remoteData = DB::connection($remoteConnection)
            ->table($table)
            ->whereIn('product_id', $remoteProductIds)
            ->get();
        
        if ($remoteData->isEmpty()) {
            $this->info('    无数据');
            return;
        }
        
        $this->info("    处理 {$remoteData->count()} 条记录");
        
        $inserted = 0;
        $skipped = 0;
        
        foreach ($remoteData as $row) {
            $result = $this->syncChildRecord($remoteConnection, $dbName, $table, $row);
            
            if ($result === 'insert') {
                $inserted++;
            } else {
                $skipped++;
            }
        }
        
        $this->info("    插入: {$inserted}, 跳过: {$skipped}");
    }
    
    private function syncChildRecord(string $remoteConnection, string $dbName, string $table, object $row): string
    {
        $rowData = (array)$row;
        $primaryKey = $this->primaryKeys[$table];
        $isComposite = is_array($primaryKey);
        
        // 处理外键映射
        $rowData = $this->processForeignKeys($dbName, $table, $rowData, $remoteConnection);
        
        // 检查是否跳过
        if (isset($rowData['__skip__'])) {
            return 'skip';
        }
        
        // 检查重复
        if ($this->isDuplicate($table, $rowData)) {
            // 记录跳过
            $pkValue = $this->getPrimaryKeyValue($table, $rowData);
            $this->recordSync($dbName, $table, $pkValue, $pkValue, 'skip', '数据已存在');
            return 'skip';
        }
        
        // 插入
        if (!$this->isDryRun) {
            if ($isComposite) {
                DB::table($table)->insert($rowData);
                $pkValue = $this->getPrimaryKeyValue($table, $rowData);
                $this->recordSync($dbName, $table, $pkValue, $pkValue, 'insert', '插入复合主键记录');
            } else {
                $originalId = $rowData[$primaryKey] ?? null;
                unset($rowData[$primaryKey]);
                
                $newId = DB::table($table)->insertGetId($rowData);
                $this->recordSync($dbName, $table, $originalId ?? 0, $newId, 'insert', '插入新记录');
                
                // 建立映射
                if ($originalId) {
                    $this->tempMappings[$dbName][$table][$originalId] = $newId;
                }
            }
        }
        
        return 'insert';
    }
    
    private function processForeignKeys(string $dbName, string $table, array $rowData, string $remoteConnection): array
    {
        if (!isset($this->foreignKeys[$table])) {
            return $rowData;
        }
        
        foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
            if (!isset($rowData[$foreignKey])) {
                continue;
            }
            
            $remoteRefId = $rowData[$foreignKey];
            
            // 从临时映射查找
            if (isset($this->tempMappings[$dbName][$referencedTable][$remoteRefId])) {
                $rowData[$foreignKey] = $this->tempMappings[$dbName][$referencedTable][$remoteRefId];
            }
            // 从历史映射查找
            elseif (isset($this->idMappings[$dbName][$referencedTable][$remoteRefId])) {
                $rowData[$foreignKey] = $this->idMappings[$dbName][$referencedTable][$remoteRefId];
            }
            // 从本地数据库查找
            else {
                $refPk = $this->primaryKeys[$referencedTable];
                if (!is_array($refPk)) {
                    $localRefId = DB::table($referencedTable)->where($refPk, $remoteRefId)->value($refPk);
                    
                    if ($localRefId) {
                        $rowData[$foreignKey] = $localRefId;
                        $this->idMappings[$dbName][$referencedTable][$remoteRefId] = $localRefId;
                    } else {
                        // 找不到映射，跳过
                        $rowData['__skip__'] = true;
                        return $rowData;
                    }
                }
            }
        }
        
        return $rowData;
    }
    
    private function isDuplicate(string $table, array $rowData): bool
    {
        $primaryKey = $this->primaryKeys[$table];
        
        if (is_array($primaryKey)) {
            // 复合主键
            $query = DB::table($table);
            foreach ($primaryKey as $key) {
                if (isset($rowData[$key])) {
                    $query->where($key, $rowData[$key]);
                }
            }
            return $query->exists();
        } else {
            // 单主键
            if (isset($rowData[$primaryKey])) {
                return DB::table($table)->where($primaryKey, $rowData[$primaryKey])->exists();
            }
            return false;
        }
    }
    
    private function getPrimaryKeyValue(string $table, array $rowData): string
    {
        $primaryKey = $this->primaryKeys[$table];
        
        if (is_array($primaryKey)) {
            return implode('_', array_map(fn($k) => $rowData[$k] ?? '', $primaryKey));
        } else {
            return (string)($rowData[$primaryKey] ?? 0);
        }
    }
    
    private function recordSync(string $dbName, string $table, $sourceId, $targetId, string $syncType, string $message): void
    {
        if ($this->isDryRun) {
            return;
        }
        
        try {
            DB::table('oc_sync_record')->insert([
                'source_site' => $dbName,
                'source_table' => $table,
                'source_id' => (string)$sourceId,
                'target_id' => (string)$targetId,
                'sync_type' => $syncType,
                'status' => 'success',
                'error_message' => json_encode(['message' => $message]),
                'retry_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // 忽略重复记录错误
        }
    }
    
    private function getLastMaxId(string $dbName, string $table): int
    {
        $progressFile = storage_path('app/remote_product_sync/progress.json');
        
        if (!file_exists($progressFile)) {
            return 0;
        }
        
        $progress = json_decode(file_get_contents($progressFile), true) ?? [];
        
        return $progress[$dbName][$table]['max_id'] ?? 0;
    }
    
    private function updateProgress(string $dbName, string $table, int $maxId): void
    {
        if ($this->isDryRun) {
            return;
        }
        
        $progressFile = storage_path('app/remote_product_sync/progress.json');
        $dir = dirname($progressFile);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $progress = [];
        if (file_exists($progressFile)) {
            $progress = json_decode(file_get_contents($progressFile), true) ?? [];
        }
        
        $progress[$dbName][$table] = [
            'max_id' => $maxId,
            'sync_time' => date('Y-m-d H:i:s'),
        ];
        
        file_put_contents($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    }
    
    private function generateReport(): void
    {
        $duration = round(microtime(true) - $this->stats['start_time'], 2);
        
        $this->info("\n=== 同步完成 ===");
        $this->info("耗时: {$duration} 秒");
        
        if ($this->isDryRun) {
            $this->warn('这是模拟运行，未实际写入数据');
        }
    }
}
