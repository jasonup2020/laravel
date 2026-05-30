<?php 

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * 远程数据库产品增量同步命令类
 *
 * 核心功能：  
 * 1. 并发同步多个远程数据库的产品数据
 * 2. 高可用IP连接：并发测试多个IP，自动选择最快响应
 * 3. ID映射机制：JSON运行时映射 + CSV历史备份
 * 4. 增量同步：CSV进度记录，支持断点续传
 * 5. 外键关联：自动转换子表外键为本地ID
 * 6. 自动建表：从远程库复制表结构
 * 7. 数据去重：检测远程表数据与本地表数据是否相同
 * 8. 异常处理：完善的容错机制
 * 
 * 同步的产品相关表：
 * - oc_product (主产品表)
 * - oc_product_description (产品描述)
 * - oc_product_attribute (产品属性)
 * - oc_product_discount (产品折扣)
 * - oc_product_filter (产品过滤器)
 * - oc_product_image (产品图片)
 * - oc_product_option (产品选项)
 * - oc_product_option_value (产品选项值)
 * - oc_product_recurring (产品周期)
 * - oc_product_related (产品关联)
 * - oc_product_reward (产品奖励)
 * - oc_product_special (产品特价)
 * - oc_product_to_category (产品分类)
 * - oc_product_to_download (产品下载)
 * - oc_product_to_layout (产品布局)
 * - oc_product_to_store (产品商店)
 * - oc_category (分类表)
 * - oc_category_description (分类描述)
 * - oc_category_filter (分类过滤器)
 * - oc_category_path (分类路径)
 * - oc_category_to_layout (分类布局)
 * - oc_category_to_store (分类商店)
 *
 * @author CodeArts Agent
 * @version 1.0
 * @date 2026-04-15
 */
#[Signature('remote-database:product-sync
    {--dry-run : 模拟运行，不实际写入数据}
    {--db= : 只同步指定数据库（按name字段）}
    {--table= : 只同步指定表}
    {--init : 初始化同步进度，读取各远程库当前最大ID}
    {--timeout=300 : 每个数据库查询超时时间（秒）}
    {--connection-timeout=3 : 数据库连接超时时间（秒）}
    {--force : 强制全量同步，忽略CSV中的最大ID记录}
    {--concurrency=13 : 并发数据库数量（默认13）}')]
#[Description('远程数据库产品增量同步工具 (基于JSON+CSV+数据去重)')]
class RemoteDatabaseProductSync extends Command
{
    // ========== 日志文件路径 ==========
    /** @var string 同步日志文件路径 */
    private string $logFile;

    /** @var string 错误日志文件路径 */
    private string $errorLogFile;

    // ========== 运行模式 ==========
    /** @var bool 是否为模拟运行模式（不实际写入数据） */
    private bool $isDryRun = false;

    // ========== 配置数据 ==========
    /** @var array 完整配置数组 */
    private array $config = [];

    /** @var array 远程数据库配置列表 */
    private array $remoteDatabases = [];

    /** @var array 需要同步的产品表列表 */
    private array $tablesToSync = [];

    /** @var array 各表主键配置（单主键或复合主键） */
    private array $primaryKeys = [];

    /** @var array 表同步依赖顺序（必须按此顺序同步） */
    private array $syncOrder = [];

    /** @var array 外键关联配置（子表外键 => 主表） */
    private array $foreignKeys = [];

    /** @var array 非自增主键表列表（主键不是自增ID的表） */
    private array $nonAutoIncrementTables = [];

    // ========== 超时配置 ==========
    /** @var int IP连接超时时间（秒），默认3秒 */
    private int $connectionTimeout = 3;

    /** @var int 数据库查询超时时间（秒），默认300秒 */
    private int $queryTimeout = 300;

    // ========== 并发配置 ==========
    /** @var int 最大并发数据库数，默认13 */
    private int $maxConcurrentDbs = 13;

    // ========== 当前上下文 ==========
    /** @var string 当前处理的数据库名 */
    private string $currentDbName = '';

    /** @var string 当前远程数据库连接名 */
    private string $currentRemoteConnection = '';

    // ========== ID映射数据 ==========
    /**
     * 运行时映射数据（内存）
     *
     * 结构：{库名 -> 表名 -> 远程ID -> 本地ID}
     * 用于同步过程中快速查询和替换外键
     *
     * @var array
     */
    private array $idMappings = [];

    /**
     * 临时映射数组（本次新增）
     *
     * 存储当前同步批次产生的新映射关系
     * 同步完成后合并到主映射并备份到CSV
     *
     * @var array
     */
    private array $tempMappings = [];

    // ========== CSV进度数据 ==========
    /**
     * CSV进度数据
     *
     * 结构：{库名 -> 表名 -> {max_id: 最大ID, sync_time: 同步时间}}
     * 用于增量同步和断点续传
     *
     * @var array
     */
    private array $progressData = [];

    // ========== 同步统计 ==========
    /**
     * 同步统计数据
     *
     * @var array
     */
    private array $stats = [
        'total_dbs' => 0,           // 处理的数据库总数
        'total_tables' => 0,        // 处理的表总数
        'total_inserted' => 0,      // 总插入记录数
        'total_skipped' => 0,       // 总跳过记录数
        'total_errors' => 0,        // 总错误记录数
        'new_mappings' => 0,        // 新增映射数
        'total_duplicates' => 0,    // 总重复记录数
        'start_time' => 0,          // 任务开始时间
        'db_stats' => [],           // 各数据库详细统计
    ];

    // ========== IP缓存 ==========
    /**
     * IP缓存数据
     *
     * 结构：{数据库名 -> {host: 最快IP, time: 响应时间, cached_at: 缓存时间}}
     * 用于避免重复测试IP，提升性能
     *
     * @var array
     */
    private array $ipCache = [];

    // ========== 数据库表支持 ==========
    /** @var bool 是否启用数据库表记录 */
    private bool $useDatabaseTables = true;

    /** @var array 映射缓存（减少数据库查询） */
    private array $mappingCache = [];

    /** @var int 最大重试次数 */
    private int $maxRetryCount = 3;

    // ========== 图片URL配置 ==========
    /** @var string 图片CDN域名 */
//    private string $imageCdnUrl = 'https://img.saveb.net/image';
    private string $imageCdnUrl = 'https://img.saveb.link/image';

    /** @var array 需要处理图片的字段 */
    private array $imageFields = [
        'oc_product' => ['image'],
        'oc_product_description' => ['description'],
        'oc_product_image' => ['image'],
        'oc_category' => ['image'],
    ];

    // ========== 关联查询缓存 ==========
    /** @var array 远程产品描述缓存 {product_id => [description_data]} */
    private array $productDescriptionCache = [];

    /** @var array 本地产品描述缓存 {product_id => [description_data]} */
    private array $localProductDescriptionCache = [];

    /** @var array 本地产品 model 缓存 {product_id => model} */
    private array $localProductModelCache = [];

    /** @var array 远程产品 model 缓存 {product_id => model} */
    private array $remoteProductModelCache = [];

    /** @var bool 是否启用关联查询优化 */
    private bool $enableRelationOptimization = true;

    // ========== 主表变更记录 ==========
    /**
     * 主表变更记录
     *
     * 结构: {数据库名 => {表名 => {远程ID => 本地ID}}}
     * 用于记录主表的新增和修改,驱动子表同步
     *
     * @var array
     */
    private array $masterTableChanges = [];

    /**
     * 主表新增记录ID
     *
     * 结构: {数据库名 => {表名 => {远程ID => 本地ID}}}
     * 用于记录主表的新增记录,子表优先处理
     *
     * @var array
     */
    private array $masterTableNewIds = [];

    // ========== 同步模式配置 ==========
    /** @var bool 是否为首次同步 */
    private bool $isFirstSync = false;

    /** @var array 同步时间范围 ['start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s'] */
    private array $syncTimeRange = [];

    /**
     * 命令主入口
     *
     * @return int 返回码（0表示成功）
     */
    public function handle(): int
    {
        // 设置PHP运行参数
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 0);

        // 解析命令行参数
        $this->isDryRun = (bool) $this->option('dry-run');

        // 加载配置文件
        $this->loadConfig();

        // 覆盖配置参数（命令行优先）
        if ($this->option('timeout')) {
            $this->queryTimeout = (int) $this->option('timeout');
        }
        if ($this->option('connection-timeout')) {
            $this->connectionTimeout = (int) $this->option('connection-timeout');
        }
        if ($this->option('concurrency')) {
            $this->maxConcurrentDbs = (int) $this->option('concurrency');
        }

        // 初始化日志文件和目录
        $this->initLogFiles();
        $this->initDirectories();

        // 输出欢迎信息
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║          远程数据库产品增量同步工具 (JSON+CSV优化版)        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->info("连接超时: {$this->connectionTimeout}秒 | 查询超时: {$this->queryTimeout}秒 | 并发数: {$this->maxConcurrentDbs}");
        $this->log('info', "开始执行 - 连接超时: {$this->connectionTimeout}秒, 查询超时: {$this->queryTimeout}秒, 并发数: {$this->maxConcurrentDbs}");

        // 模拟运行模式提示
        if ($this->isDryRun) {
            $this->warn('⚠️  【模拟运行模式】不会实际写入数据库');
        }

        // 初始化模式：读取各远程库当前最大ID
        if ($this->option('init')) {
            return $this->initProgress();
        }

        // 记录开始时间
        $this->stats['start_time'] = microtime(true);
        $this->stats['total_updated_duplicates'] = 0;

        // 加载CSV进度文件
        $this->loadProgress();

        // 加载JSON映射文件
        $this->loadIdMappings();
        $this->determineSyncMode();

        // 初始化临时映射数组（存储本次新增映射）
        $this->tempMappings = [];

        // 自动建表检测
        $this->ensureLocalTablesExist();

        // 并发同步远程数据库
        $this->syncRemoteDatabases();

        // 映射持久化与历史备份
        $this->saveMappingsAndBackup();

        // 生成同步报告
        $this->generateReport();

        return 0;
    }

    /**
     * 加载配置文件
     */
    private function loadConfig(): void
    {
        $this->config = config('remote_databases_products');
        $this->remoteDatabases = $this->config['remote_databases'] ?? [];
        $this->tablesToSync = $this->config['tables_to_sync'] ?? [];
        $this->primaryKeys = $this->config['primary_keys'] ?? [];
        $this->syncOrder = $this->config['sync_order'] ?? [];
        $this->foreignKeys = $this->config['foreign_keys'] ?? [];
        $this->nonAutoIncrementTables = $this->config['non_auto_increment_tables'] ?? [];
        $this->connectionTimeout = $this->config['connection_timeout'] ?? 3;
        $this->queryTimeout = $this->config['query_timeout'] ?? 300;
        $this->maxConcurrentDbs = $this->config['concurrency']['max_concurrent_dbs'] ?? 13;
    }

    /**
     * 初始化日志文件
     */
    private function initLogFiles(): void
    {
        $timestamp = date('Ymd_His');
        $logDir = $this->config['logging']['log_dir'] ?? storage_path('logs/remote_product_sync');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->logFile = $logDir . "/sync_{$timestamp}.log";
        $this->errorLogFile = $logDir . "/sync_error_{$timestamp}.log";
    }

    /**
     * 初始化必要的目录
     */
    private function initDirectories(): void
    {
        $mappingDir = dirname($this->config['mappings']['json_file']);
        if (!is_dir($mappingDir)) {
            mkdir($mappingDir, 0755, true);
        }

        $historyDir = $this->config['mappings']['history_dir'];
        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0755, true);
        }

        $this->cleanHistoryBackups();
    }

    /**
     * 清理过期的历史备份文件
     */
    private function cleanHistoryBackups(): void
    {
        $historyDir = $this->config['mappings']['history_dir'];
        $retentionDays = $this->config['mappings']['history_retention_days'] ?? 7;
        $cutoffTime = time() - ($retentionDays * 86400);

        foreach (glob($historyDir . '/history_*.csv') as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $this->log('info', "清理过期历史备份: {$file}");
            }
        }
    }

    /**
     * 初始化同步进度
     *
     * @return int 返回码
     */
    private function initProgress(): int
    {
        $this->info('🔄 初始化同步进度，读取各远程库当前最大ID...');

        $progressData = [];

        foreach ($this->remoteDatabases as $remoteDb) {
            if ($this->option('db') && $remoteDb['name'] !== $this->option('db')) continue;

            $this->info("📡 连接: {$remoteDb['name']}");

            try {
                $remoteConnection = $this->createRemoteConnection($remoteDb);
            } catch (\Exception $e) {
                $this->error("❌ 连接失败: " . $e->getMessage());
                continue;
            }

            foreach ($this->tablesToSync as $table) {
                if ($this->option('table') && $table !== $this->option('table')) continue;

                $pk = $this->getPrimaryKey($table);

                if (is_array($pk)) {
                    $progressData[$remoteDb['name']][$table] = [
                        'max_id' => 0,
                        'sync_time' => date('Y-m-d H:i:s'),
                    ];
                    $this->line("  {$table}: [复合主键] → 0");
                    continue;
                }

                try {
                    $maxVal = DB::connection($remoteConnection)->table($table)->max($pk);
                    $progressData[$remoteDb['name']][$table] = [
                        'max_id' => $maxVal ?? 0,
                        'sync_time' => date('Y-m-d H:i:s'),
                    ];
                    $this->line("  {$table}: 最大ID = " . ($maxVal ?? 0));
                } catch (\Exception $e) {
                    $progressData[$remoteDb['name']][$table] = [
                        'max_id' => 0,
                        'sync_time' => date('Y-m-d H:i:s'),
                    ];
                    $this->warn("  {$table}: 读取失败 - " . substr($e->getMessage(), 0, 60));
                }
            }

            $this->disconnectRemote($remoteDb['name']);
        }

        $this->saveProgress($progressData);
        $this->info("✅ 进度文件已保存: {$this->config['mappings']['progress_file']}");

        return 0;
    }

    /**
     * 加载CSV进度文件
     */
    private function loadProgress(): void
    {
        $progressFile = $this->config['mappings']['progress_file'];

        if (!file_exists($progressFile)) {
            $this->warn("⚠️ 进度文件不存在: {$progressFile}");
            $this->info("💡 提示: 使用 --init 初始化");
            $this->progressData = [];
            return;
        }

        try {
            $handle = fopen($progressFile, 'r');
            if ($handle === false) {
                throw new \Exception("无法打开文件: {$progressFile}");
            }

            $headers = fgetcsv($handle);
            $this->progressData = [];

            while (($row = fgetcsv($handle)) !== false) {
                $db = $row[0] ?? null;
                $table = $row[1] ?? null;
                $maxId = $row[2] ?? 0;
                $syncTime = $row[3] ?? '';

                if ($db && $table) {
                    $this->progressData[$db][$table] = [
                        'max_id' => (int) $maxId,
                        'sync_time' => $syncTime,
                    ];
                }
            }

            fclose($handle);

            $count = 0;
            foreach ($this->progressData as $db => $tables) {
                $count += count($tables);
            }
            $this->info("📂 已加载进度: {$count} 条记录");

        } catch (\Exception $e) {
            $this->error("❌ 加载进度失败: " . $e->getMessage());
            $this->progressData = [];
        }
    }

    /**
     * 保存CSV进度文件
     */
    private function saveProgress(array $progressData): void
    {
        $progressFile = $this->config['mappings']['progress_file'];

        try {
            $handle = fopen($progressFile, 'w');
            if ($handle === false) {
                throw new \Exception("无法写入文件: {$progressFile}");
            }

            fputcsv($handle, ['db_name', 'table_name', 'max_id', 'sync_time']);

            foreach ($progressData as $dbName => $tables) {
                foreach ($tables as $tableName => $data) {
                    fputcsv($handle, [
                        $dbName,
                        $tableName,
                        $data['max_id'],
                        $data['sync_time'],
                    ]);
                }
            }

            fclose($handle);

            $this->log('info', "进度已保存: {$progressFile}");
        } catch (\Exception $e) {
            $this->log('error', "保存进度失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 加载JSON映射文件
     */
    private function loadIdMappings(): void
    {
        $jsonFile = $this->config['mappings']['json_file'];

        if (!file_exists($jsonFile)) {
            $this->idMappings = [];
            $this->log('info', "映射文件不存在，初始化空映射");
            return;
        }

        try {
            $content = file_get_contents($jsonFile);
            $this->idMappings = json_decode($content, true) ?? [];
            $this->log('info', "已加载ID映射: {$jsonFile}");
        } catch (\Exception $e) {
            $this->error("❌ 加载映射失败: " . $e->getMessage());
            $this->log('error', "加载映射失败: " . $e->getMessage());
            $this->idMappings = [];
        }
    }

    /**
     * 确定同步模式
     *
     * 根据命令行参数 --force 确定是增量同步还是全量同步
     */
    private function determineSyncMode(): void
    {
        if ($this->option('force')) {
            $this->warn('⚠️  【强制全量同步模式】将忽略CSV中的最大ID记录');
            $this->log('info', '强制全量同步模式已启用');
        } else {
            $this->info('📋 增量同步模式');
            $this->log('info', '增量同步模式已启用');
        }
    }

    /**
     * 保存JSON映射文件
     */
    private function saveIdMappings(): void
    {
        $jsonFile = $this->config['mappings']['json_file'];
        $tempFile = $jsonFile . '.tmp';

        try {
            foreach ($this->tempMappings as $dbName => $tables) {
                foreach ($tables as $tableName => $mappings) {
                    foreach ($mappings as $remoteId => $localId) {
                        $this->idMappings[$dbName][$tableName][$remoteId] = $localId;
                    }
                }
            }

            file_put_contents($tempFile, json_encode($this->idMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $tempContent = file_get_contents($tempFile);
            json_decode($tempContent);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("临时文件JSON格式错误");
            }

            rename($tempFile, $jsonFile);

            $this->log('info', "ID映射已保存: {$jsonFile}");

            // 新增：同步写入数据库表
            if ($this->useDatabaseTables) {
                $this->saveMappingsToDatabase();
            }
        } catch (\Exception $e) {
            $this->log('error', "保存映射失败: " . $e->getMessage());
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }

    /**
     * 保存映射到数据库表
     */
    private function saveMappingsToDatabase(): void
    {
        try {
            foreach ($this->tempMappings as $dbName => $tables) {
                foreach ($tables as $tableName => $mappings) {
                    $records = [];

                    foreach ($mappings as $remoteId => $localId) {
                        // 检查是否已存在
                        $exists = DB::table('oc_sync_record')
                            ->where('source_site', $dbName)
                            ->where('source_table', $tableName)
                            ->where('source_id', $remoteId)
                            ->exists();

                        if (!$exists) {
                            $records[] = [
                                'source_site' => $dbName,
                                'source_table' => $tableName,
                                'source_id' => $remoteId,
                                'target_id' => $localId,
                                'sync_type' => 'insert',
                                'status' => 'success',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    // 批量插入（每次1000条）
                    if (!empty($records)) {
                        $chunks = array_chunk($records, 1000);
                        foreach ($chunks as $chunk) {
                            DB::table('oc_sync_record')->insert($chunk);
                        }
                    }
                }
            }

            $this->log('info', "映射已同步到数据库表");
        } catch (\Exception $e) {
            $this->log('error', "保存映射到数据库失败: " . $e->getMessage());
        }
    }

    /**
     * 保存CSV历史备份
     */
    private function saveHistoryBackup(): void
    {
        if (empty($this->tempMappings)) {
            return;
        }

        $historyDir = $this->config['mappings']['history_dir'];
        $timestamp = date('Ymd_His');
        $backupFile = $historyDir . "/history_{$timestamp}.csv";

        try {
            $handle = fopen($backupFile, 'w');
            if ($handle === false) {
                throw new \Exception("无法创建备份文件: {$backupFile}");
            }

            fputcsv($handle, ['db_name', 'table_name', 'remote_id', 'local_id', 'timestamp']);

            foreach ($this->tempMappings as $dbName => $tables) {
                foreach ($tables as $tableName => $mappings) {
                    foreach ($mappings as $remoteId => $localId) {
                        fputcsv($handle, [
                            $dbName,
                            $tableName,
                            $remoteId,
                            $localId,
                            date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            fclose($handle);

            $this->log('info', "历史备份已保存: {$backupFile}");
        } catch (\Exception $e) {
            $this->log('error', "保存历史备份失败: " . $e->getMessage());
        }
    }

    /**
     * 映射持久化与历史备份
     */
    private function saveMappingsAndBackup(): void
    {
        if ($this->isDryRun) {
            $this->info("✅ [模拟] 映射数据已保存");
            return;
        }

        $this->saveIdMappings();
        $this->saveHistoryBackup();
        $this->info("✅ 映射数据已保存");
    }

    /**
     * 确保本地表存在
     */
    private function ensureLocalTablesExist(): void
    {
        $this->info("🔍 检测本地表结构...");

        $localDbName = DB::getDatabaseName();

        foreach ($this->tablesToSync as $table) {
            $exists = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                [$localDbName, $table]
            );

            if ($exists->cnt > 0) {
                $this->line("  ✓ {$table} 已存在");
                continue;
            }

            $this->warn("  ⚠️ 本地表 {$table} 不存在，需要从远程库复制结构");

            if ($this->isDryRun) {
                $this->line("     [模拟] 将创建表 {$table}");
                continue;
            }

            $copied = false;
            foreach ($this->remoteDatabases as $remoteDb) {
                try {
                    $remoteConnection = $this->createRemoteConnection($remoteDb);
                    $createTable = DB::connection($remoteConnection)
                        ->selectOne("SHOW CREATE TABLE `{$table}`");

                    if ($createTable && isset($createTable->{'Create Table'})) {
                        $sql = $createTable->{'Create Table'};
                        DB::statement($sql);
                        $this->line("  ✅ 表 {$table} 创建成功 (从 {$remoteDb['name']})");
                        $this->log('info', "从 {$remoteDb['name']} 创建本地表 {$table}");
                        $copied = true;
                        $this->disconnectRemote($remoteDb['name']);
                        break;
                    }

                    $this->disconnectRemote($remoteDb['name']);
                } catch (\Exception $e) {
                    $this->disconnectRemote($remoteDb['name']);
                    continue;
                }
            }

            if (!$copied) {
                $this->error("  ❌ 无法创建表 {$table}，所有远程库连接失败");
            }
        }
    }

    /**
     * 并发同步远程数据库
     */
    private function syncRemoteDatabases(): void
    {
        $this->newLine();
        $this->info("🚀 开始并发同步远程数据库...");

        $dbsToSync = $this->remoteDatabases;
        if ($this->option('db')) {
            $dbsToSync = array_filter($dbsToSync, fn($db) => $db['name'] === $this->option('db'));
        }

        $chunks = array_chunk($dbsToSync, $this->maxConcurrentDbs);

        foreach ($chunks as $chunk) {
            $this->syncDatabaseBatch($chunk);
        }
    }

    /**
     * 同步一批数据库
     */
    private function syncDatabaseBatch(array $databases): void
    {
        foreach ($databases as $remoteDb) {
            $this->stats['total_dbs']++;
            $this->stats['db_stats'][$remoteDb['name']] = [
                'inserted' => 0,
                'skipped' => 0,
                'errors' => 0,
                'tables' => 0,
                'duplicates' => 0,
                'updated_duplicates' => 0,
                'start_time' => microtime(true),
                'ip_used' => '',
                'ip_response_time' => 0,
            ];

            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📦 处理数据库: {$remoteDb['name']} ({$remoteDb['website']})");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            try {
                $remoteConnection = $this->createRemoteConnection($remoteDb);
                $this->syncSingleDatabase($remoteConnection, $remoteDb);
                $this->disconnectRemote($remoteDb['name']);
            } catch (\Exception $e) {
                $this->error("❌ 同步失败: " . $e->getMessage());
                $this->log('error', "[{$remoteDb['name']}] 同步失败: " . $e->getMessage());
                $this->stats['total_errors']++;
                $this->stats['db_stats'][$remoteDb['name']]['errors']++;
            }

            if (!$this->isDryRun) {
                $this->saveProgress($this->progressData);
            }
        }
    }

    /**
     * 同步单个数据库
     */
    private function syncSingleDatabase(string $remoteConnection, array $remoteDb): void
    {
        $dbName = $remoteDb['name'];

        // 初始化主表变更记录
        if (!isset($this->masterTableChanges[$dbName])) {
            $this->masterTableChanges[$dbName] = [];
        }

        foreach ($this->syncOrder as $table) {
            if (!in_array($table, $this->tablesToSync)) continue;
            if ($this->option('table') && $table !== $this->option('table')) continue;

            // 设置当前上下文（用于去重逻辑中访问远程数据）
            $this->currentDbName = $dbName;
            $this->currentRemoteConnection = $remoteConnection;

            $this->newLine();
            $this->info("  📋 同步表: {$table}");

            $tableStartTime = microtime(true);

            try {
                $result = $this->syncTable($remoteConnection, $dbName, $table);

                $tableDuration = round((microtime(true) - $tableStartTime) * 1000, 2); // 毫秒

                $this->stats['total_inserted'] += $result['inserted'];
                $this->stats['total_skipped'] += $result['skipped'];
                $this->stats['total_errors'] += $result['errors'];
                $this->stats['total_tables']++;
                $this->stats['new_mappings'] += $result['new_mappings'];
                $this->stats['total_duplicates'] += $result['duplicates'] ?? 0;

                $this->stats['db_stats'][$dbName]['inserted'] += $result['inserted'];
                $this->stats['db_stats'][$dbName]['skipped'] += $result['skipped'];
                $this->stats['db_stats'][$dbName]['errors'] += $result['errors'];
                $this->stats['db_stats'][$dbName]['tables']++;
                $this->stats['db_stats'][$dbName]['duplicates'] = ($this->stats['db_stats'][$dbName]['duplicates'] ?? 0) + ($result['duplicates'] ?? 0);
                $this->stats['db_stats'][$dbName]['updated_duplicates'] = ($this->stats['db_stats'][$dbName]['updated_duplicates'] ?? 0) + ($result['updated_duplicates'] ?? 0);

                $duplicates = $result['duplicates'] ?? 0;
                $updatedDuplicates = $result['updated_duplicates'] ?? 0;
                $changedIdsCount = isset($result['changed_ids']) ? count($result['changed_ids']) : 0;

                // 判断表类型
                $isMasterTable = in_array($table, ['oc_product', 'oc_category', 'oc_option', 'oc_option_value']);
                $tableType = $isMasterTable ? '主表' : '子表';

                // 输出单行紧凑格式的执行结果
                $this->line("     ✅ {$tableType} | 新增:{$result['inserted']} | 跳过:{$result['skipped']} | 更新:{$duplicates} | 错误:{$result['errors']} | 映射:{$result['new_mappings']}" . ($isMasterTable ? " | 变更ID:{$changedIdsCount}" : "") . " | 耗时:{$tableDuration}ms");
                // 输出更新的重复记录数（如果有）
                $summary = "";
                if ($isMasterTable) {
                    $summary .= " | 变更ID:{$changedIdsCount}";
                }
                $summary .= " | 耗时:{$tableDuration}ms";
                $this->line($summary);

                // 记录同步操作日志到 oc_sync_log
                if ($this->useDatabaseTables) {
                    $this->logSyncOperation(
                        $dbName,
                        $table,
                        0,
                        'sync_table',
                        json_encode([
                            'table_type' => $tableType,
                            'inserted' => $result['inserted'],
                            'skipped' => $result['skipped'],
                            'updated' => $duplicates,
                            'errors' => $result['errors'],
                            'new_mappings' => $result['new_mappings'],
                            'changed_ids' => $changedIdsCount,
                        ]),
                        (int) $tableDuration
                    );
                }

                // 记录主表变更（用于驱动子表同步）
                if (isset($result['changed_ids']) && !empty($result['changed_ids'])) {
                    $this->masterTableChanges[$dbName][$table] = $result['changed_ids'];
                    $this->log('info', "[{$dbName}][{$table}] 记录变更ID: " . count($result['changed_ids']) . " 个");
                }

                // 记录主表新增ID（用于子表优先处理）
                if (isset($result['new_ids']) && !empty($result['new_ids'])) {
                    if (!isset($this->masterTableNewIds[$dbName])) {
                        $this->masterTableNewIds[$dbName] = [];
                    }
                    $this->masterTableNewIds[$dbName][$table] = $result['new_ids'];
                    $this->log('info', "[{$dbName}][{$table}] 记录新增ID: " . count($result['new_ids']) . " 个");

                    // 如果是 oc_product 表,记录详细信息
                    if ($table === 'oc_product') {
                        $newIdsCount = count($result['new_ids'] ?? []);
                        $this->log('info', "[{$dbName}][{$table}] 新增ID将传递给所有子表: oc_product_description, oc_product_to_category 等，共 {$newIdsCount} 个");
                }
                }

                // ========== 反向同步：删除本地多余的记录 ==========
                if ($isMasterTable) {
                    $this->deleteOrphanedRecords($remoteConnection, $dbName, $table, $result);
                }

            } catch (\Exception $e) {
                $tableDuration = round((microtime(true) - $tableStartTime) * 1000, 2);

                $this->line("     ❌ 同步失败 | 错误: " . substr($e->getMessage(), 0, 60) . "... | 耗时:{$tableDuration}ms");

                // 记录失败日志
                if ($this->useDatabaseTables) {
                    $this->logSyncOperation(
                        $dbName,
                        $table,
                        0,
                        'sync_failed',
                        $e->getMessage(),
                        (int) $tableDuration
                    );
                }

                $this->log('error', "[{$dbName}][{$table}] " . $e->getMessage());
                $this->stats['total_errors']++;
                $this->stats['db_stats'][$dbName]['errors']++;
            }
        }
    }

    /**
     * 同步单张表
     */
    private function syncTable(string $remoteConnection, string $dbName, string $table): array
    {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0, 'new_mappings' => 0, 'duplicates' => 0, 'updated_duplicates' => 0, 'changed_ids' => [], 'new_ids' => []];
        $startTime = time();

        $primaryKey = $this->getPrimaryKey($table);

        // ========== 判断是否为子表同步 ==========
        $isChildTable = $this->isChildTable($table);
        $parentTable = $isChildTable ? $this->getParentTable($table) : null;

        // ========== 复合主键表特殊处理 ==========
        // 如果是复合主键表,且是子表,且主表有变更,则使用主表变更驱动的子表同步
        if (is_array($primaryKey) && $isChildTable && $parentTable && isset($this->masterTableChanges[$dbName][$parentTable])) {
            $changedParentIds = array_keys($this->masterTableChanges[$dbName][$parentTable]);

            if (empty($changedParentIds)) {
                $this->line("     ℹ️ 主表无变更,跳过子表同步");
                return $result;
            }

            $parentForeignKey = $this->getParentForeignKey($table, $parentTable);

            // 提取远程ID用于查询
            $remoteParentIds = array_keys($changedParentIds);

            $newCount = DB::connection($remoteConnection)
                ->table($table)
                ->whereIn($parentForeignKey, $remoteParentIds)
                ->count();

            if ($newCount === 0) {
                $this->line("     ℹ️ 子表无数据 (主表变更: " . count($changedParentIds) . " 个)");
                return $result;
            }

            $this->line("     📊 子表同步: {$newCount} 条记录 (主表变更: " . count($changedParentIds) . " 个)");

            // 子表同步逻辑
            return $this->syncChildTable($remoteConnection, $dbName, $table, $parentTable, $parentForeignKey, $this->masterTableChanges[$dbName][$parentTable]);
        }

        // ========== 检查是否为复合主键表或全量同步表 ==========
        $fullSyncTables = $this->config['full_sync_tables'] ?? [];
        if (is_array($primaryKey)) {
            // 复合主键表
            return $this->syncCompositeKeyTable($remoteConnection, $dbName, $table, $primaryKey);
        } elseif (in_array($table, $fullSyncTables)) {
            // 全量同步表(单主键)
            return $this->syncCompositeKeyTable($remoteConnection, $dbName, $table, [$primaryKey]);
        }

        // ========== 确定查询条件 ==========
        $lastMaxId = $this->option('force') ? 0 : ($this->progressData[$dbName][$table]['max_id'] ?? 0);

        // 如果是子表,且主表有变更,则基于主表变更同步
        if ($isChildTable && $parentTable && isset($this->masterTableChanges[$dbName][$parentTable])) {
            $changedParentIds = array_keys($this->masterTableChanges[$dbName][$parentTable]);

            if (empty($changedParentIds)) {
                $this->line("     ℹ️ 主表无变更,跳过子表同步");
                return $result;
            }

            $parentForeignKey = $this->getParentForeignKey($table, $parentTable);

            $newCount = DB::connection($remoteConnection)
                ->table($table)
                ->whereIn($parentForeignKey, $changedParentIds)
                ->count();

            if ($newCount === 0) {
                $this->line("     ℹ️ 子表无数据 (主表变更: " . count($changedParentIds) . " 个)");
                return $result;
            }

            $this->line("     📊 子表同步: {$newCount} 条记录 (主表变更: " . count($changedParentIds) . " 个 )");

            // 子表同步逻辑
            return $this->syncChildTable($remoteConnection, $dbName, $table, $parentTable, $parentForeignKey, $changedParentIds);
        }

        // ========== 常规增量同步 ==========
        // 构建查询条件
        $query = DB::connection($remoteConnection)->table($table);

        // 判断是否使用 date_modified 时间范围查询
        $useDateModified = !$this->isFirstSync && !empty($this->syncTimeRange) && in_array($table, ['oc_product', 'oc_category']);

        if ($useDateModified) {
            // 使用 date_modified 时间范围查询
            $dateField = $table === 'oc_product' ? 'date_modified' : 'date_modified';
            $query->whereBetween($dateField, [$this->syncTimeRange['start'], $this->syncTimeRange['end']]);
            $this->line("     📅 时间范围: {$this->syncTimeRange['start']} ~ {$this->syncTimeRange['end']}");
        } else {
            // 使用 ID 增量查询
            $query->where($primaryKey, '>', $lastMaxId);
        }

        $newCount = $query->count();

        if ($newCount === 0) {
            $this->line("     ℹ️ 无新数据" . ($useDateModified ? " (时间范围查询)" : " (当前最大ID: {$lastMaxId})"));
            return $result;
        }

        $this->line("     📊 发现 {$newCount} 条新记录" . ($useDateModified ? " (时间范围查询)" : " (ID > {$lastMaxId})"));

        // 判断是否使用批量插入及批次大小
        if ($newCount > 50000) {
            $useBatchInsert = true;
            $batchSize = 5000;
            $this->line("     🚀 启用批量插入模式 (每批 {$batchSize} 条)");
        } elseif ($newCount > 10000) {
            $useBatchInsert = true;
            $batchSize = 1000;
            $this->line("     🚀 启用批量插入模式 (每批 {$batchSize} 条)");
        } else {
            $useBatchInsert = false;
            $batchSize = 100;
        }

        $offset = 0;
        $currentMaxId = $lastMaxId;
        $batchData = [];  // 批量插入数据缓存
        $maxFetchSize = 3000;  // 远程查询最大条数限制
        $maxRetries = 3;  // 最大重试次数
        $retryCount = 0;  // 当前重试次数
        $retryDelay = 2;  // 重试延迟(秒)

        while (true) {
            if (time() - $startTime > $this->queryTimeout) {
                $retryCount++;

                if ($retryCount <= $maxRetries) {
                    $this->warn("     ⚠️ 查询超时，已处理 {$offset}/{$newCount}，等待 {$retryDelay} 秒后重试 (第 {$retryCount}/{$maxRetries} 次)");
                    sleep($retryDelay);
                    $startTime = time();  // 重置开始时间
                    continue;  // 继续处理
                } else {
                    $this->error("     ❌ 查询超时，已达到最大重试次数 {$maxRetries}，停止处理");
                    break;
                }
            }

            // 限制每次查询最多3000条
            $fetchSize = min($batchSize, $maxFetchSize);

            $rows = $query->orderBy($primaryKey, 'asc')
                ->skip($offset)
                ->take($fetchSize)
                ->get();

            if ($rows->isEmpty()) break;

            // 关联查询优化：预加载关联数据
            if ($this->enableRelationOptimization && $table === 'oc_product') {
                $this->preloadProductDescriptions($remoteConnection, $rows);
            }

            if ($useBatchInsert) {
                // 批量插入模式
                foreach ($rows as $row) {
                    $remoteId = $row->$primaryKey;

                    try {
                        $this->prepareBatchRecord($remoteConnection, $dbName, $table, $row, $result, $batchData);
                    } catch (\Exception $e) {
                        $result['errors']++;
                        $this->log('error', "[{$dbName}][{$table}] ID={$remoteId}: " . $e->getMessage());
                    }

                    if ($remoteId > $currentMaxId) {
                        $currentMaxId = $remoteId;
                    }
                }

                // 执行批量插入
                if (!empty($batchData)) {
                    $this->executeBatchInsert($dbName, $table, $batchData, $result);
                    $batchData = [];
                }
            } else {
                // 逐条插入模式
                foreach ($rows as $row) {
                    $remoteId = $row->$primaryKey;

                    try {
                        $this->syncSingleRecord($remoteConnection, $dbName, $table, $row, $result);
                    } catch (\Exception $e) {
                        $result['errors']++;
                        $this->log('error', "[{$dbName}][{$table}] ID={$remoteId}: " . $e->getMessage());
                    }

                    if ($remoteId > $currentMaxId) {
                        $currentMaxId = $remoteId;
                    }
                }
            }

            $offset += $fetchSize;
            $progress = min($offset, $newCount);
            $percent = round(($progress / $newCount) * 100, 1);
            $this->line("     ⏳ 进度: {$progress}/{$newCount} ({$percent}%)");
        }

        // 清空关联查询缓存
        $this->clearProductDescriptionCache();

        if ($currentMaxId > $lastMaxId) {
            $this->progressData[$dbName][$table] = [
                'max_id' => $currentMaxId,
                'sync_time' => date('Y-m-d H:i:s'),
            ];
            $this->line("     📈 更新最大ID: {$lastMaxId} → {$currentMaxId}");
        }

        return $result;
    }

    /**
     * 构建同步查询条件（智能增量同步策略）
     *
     * @param string $remoteConnection 远程连接名
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param int $lastMaxId 上次同步的最大ID
     * @return array|null ['count' => int, 'query' => Builder, 'description' => string]
     */
    private function buildSyncQueryCondition(string $remoteConnection, string $dbName, string $table, int $lastMaxId): ?array
    {
        $primaryKey = $this->getPrimaryKey($table);
        $isFirstSync = !$this->progressData[$dbName][$table] || $lastMaxId === 0;

        // 判断同步模式
        if ($isFirstSync) {
            // 首次同步模式: 查询所有数据
            $query = DB::connection($remoteConnection)
                ->table($table)
                ->where($primaryKey, '>', 0);
            
            $count = $query->count();
            $description = "首次同步模式: 发现 {$count} 条记录";
        } else {
            // 判断是否为周一（每周首次同步）
            $today = date('N'); // 1=周一, 7=周日
            
            if ($today == 1) {
                // 每周同步模式: 提取上周数据
                $lastWeekStart = date('Y-m-d 00:00:01', strtotime('last week Monday'));
                $lastWeekEnd = date('Y-m-d 23:59:59', strtotime('last week Sunday'));
                
                $query = DB::connection($remoteConnection)
                    ->table($table)
                    ->whereBetween('date_modified', [$lastWeekStart, $lastWeekEnd]);
                
                $count = $query->count();
                $description = "每周同步模式: 提取 {$lastWeekStart} ~ {$lastWeekEnd} 的数据，共 {$count} 条";
            } else {
                // 日常同步模式: 提取当天数据
                $todayStart = date('Y-m-d 00:00:01');
                $todayEnd = date('Y-m-d 23:59:59');
                
                $query = DB::connection($remoteConnection)
                    ->table($table)
                    ->whereBetween('date_modified', [$todayStart, $todayEnd]);
                
                $count = $query->count();
                $description = "日常同步模式: 提取 {$todayStart} ~ {$todayEnd} 的数据，共 {$count} 条";
            }
        }

        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            'query' => $query,
            'description' => $description,
        ];
    }

    /**
     * 预加载产品描述数据（关联查询优化）
     *
     * @param string $remoteConnection 远程连接
     * @param \Illuminate\Support\Collection $products 产品数据集合
     */
    private function preloadProductDescriptions(string $remoteConnection, $products): void
    {
        // 提取所有 product_id
        $productIds = $products->pluck('product_id')->unique()->toArray();

        if (empty($productIds)) {
            return;
        }

        // 一次性查询所有远程相关的产品描述
        $remoteDescriptions = DB::connection($remoteConnection)
            ->table('oc_product_description')
            ->whereIn('product_id', $productIds)
            ->get();

        // 按 product_id 分组缓存远程数据
        foreach ($remoteDescriptions as $desc) {
            $productId = $desc->product_id;
            if (!isset($this->productDescriptionCache[$productId])) {
                $this->productDescriptionCache[$productId] = [];
            }
            $this->productDescriptionCache[$productId][] = $desc;
        }

        // 一次性查询远程产品的 model 数据
        $remoteProducts = DB::connection($remoteConnection)
            ->table('oc_product')
            ->whereIn('product_id', $productIds)
            ->select('product_id', 'model')
            ->get();

        // 缓存远程产品 model
        foreach ($remoteProducts as $product) {
            $this->remoteProductModelCache[$product->product_id] = $product->model ?? '';
        }

        // 一次性查询所有本地相关的产品描述（用于对比）
        $localDescriptions = DB::table('oc_product_description')
            ->whereIn('product_id', $productIds)
            ->get();

        // 按 product_id 分组缓存本地数据
        foreach ($localDescriptions as $desc) {
            $productId = $desc->product_id;
            if (!isset($this->localProductDescriptionCache[$productId])) {
                $this->localProductDescriptionCache[$productId] = [];
            }
            $this->localProductDescriptionCache[$productId][] = $desc;
        }

        // 一次性查询本地产品的 model 数据（用于对比）
        $localProducts = DB::table('oc_product')
            ->whereIn('product_id', $productIds)
            ->select('product_id', 'model')
            ->get();

        // 缓存本地产品 model
        foreach ($localProducts as $product) {
            $this->localProductModelCache[$product->product_id] = $product->model ?? '';
        }

        $this->log('info', "预加载产品描述: " . count($productIds) . " 个产品, 远程描述 " . count($remoteDescriptions) . " 条, 本地描述 " . count($localDescriptions) . " 条");
    }

    /**
     * 获取缓存的远程产品描述
     *
     * @param int $productId 产品ID
     * @return array 描述数据数组
     */
    private function getCachedProductDescriptions(int $productId): array
    {
        return $this->productDescriptionCache[$productId] ?? [];
    }

    /**
     * 获取缓存的本地产品描述
     *
     * @param int $productId 产品ID
     * @return array 描述数据数组
     */
    private function getLocalCachedProductDescriptions(int $productId): array
    {
        return $this->localProductDescriptionCache[$productId] ?? [];
    }

    /**
     * 清空产品描述缓存
     */
    private function clearProductDescriptionCache(): void
    {
        $this->productDescriptionCache = [];
        $this->localProductDescriptionCache = [];
    }

    /**
     * 同步单条记录
     */
    private function syncSingleRecord(
        string $remoteConnection,
        string $dbName,
        string $table,
        object $row,
        array &$result
    ): void {
        $primaryKey = $this->getPrimaryKey($table);
        $remoteId = $row->$primaryKey;

        if (isset($this->idMappings[$dbName][$table][$remoteId])) {
            $result['skipped']++;
            return;
        }

        $rowData = (array) $row;

        $rowData = $this->processForeignKeys($dbName, $table, $rowData, $remoteConnection);

        if (isset($rowData['__skip_record__']) && $rowData['__skip_record__'] === true) {
            $result['skipped']++;
            return;
        }

        // 处理图片URL转换
        $rowData = $this->processImageUrls($table, $rowData);

        $rowData = $this->fixInvalidDatetimeValues($rowData);

        // 检查重复记录（返回检测结果）
        // 注意：在检查重复之前不删除主键，因为 checkDuplicateRecord 需要主键进行去重判断
        $duplicateCheck = $this->checkDuplicateRecord($table, $rowData);

        // 检查完重复后，再删除自增主键（如果需要）
        if (!in_array($table, $this->nonAutoIncrementTables)) {
            unset($rowData[$primaryKey]);
        }

        if ($duplicateCheck['is_duplicate']) {
            // 如果只是排除字段不同，更新排除字段
            if ($duplicateCheck['image_diff_only'] && !empty($duplicateCheck['existing_record'])) {
                if (!$this->isDryRun) {
                    $this->updateImageFields($table, $duplicateCheck['existing_record'], $rowData);
                    $this->stats['total_updated_duplicates']++;
                    $result['updated_duplicates']++;

                    // 记录更新操作到同步表
                    if ($this->useDatabaseTables) {
                        $this->logUpdateToSyncRecord($dbName, $table, $duplicateCheck['existing_record']->{$primaryKey}, $rowData);
                    }

                    $result['duplicates']++;

                    // 记录变更ID（修改也算变更）
                    $localId = $duplicateCheck['existing_record']->{$primaryKey};
                    $result['changed_ids'][$remoteId] = $localId;

                    // 将映射写入 tempMappings，供后续子表同步使用
                    $this->idMappings[$dbName][$table][$remoteId] = $localId;
                    $this->tempMappings[$dbName][$table][$remoteId] = $localId;

                    $this->log('info', "[{$dbName}][{$table}] 更新图片: ID={$localId}");
                } else {
                    $result['duplicates']++;
                    $result['changed_ids'][$remoteId] = $remoteId; // 模拟模式
                }
            } else {
                // 完全重复，跳过
                $result['duplicates']++;
                $this->logDuplicateRecord($dbName, $table, $remoteId, $rowData);
            }
            return;
        }

        if ($this->isDryRun) {
            $result['inserted']++;
            $result['new_mappings']++;
            $result['changed_ids'][$remoteId] = $remoteId; // 模拟模式
            return;
        }

        try {
            if (in_array($table, $this->nonAutoIncrementTables)) {
                DB::table($table)->insert($rowData);
                $newId = $remoteId;
            } else {
                $newId = DB::table($table)->insertGetId($rowData);
            }

            $this->idMappings[$dbName][$table][$remoteId] = $newId;
            $this->tempMappings[$dbName][$table][$remoteId] = $newId;

            // 记录变更ID
            $result['changed_ids'][$remoteId] = $newId;

            // 记录新增ID（用于子表优先处理）
            $result['new_ids'][$remoteId] = $newId;

            $result['inserted']++;
            $result['new_mappings']++;

        } catch (QueryException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $result['skipped']++;
            } else {
                throw $e;
            }
        }
    }

    /**
     * 准备批量插入记录
     *
     * @param string $remoteConnection 远程连接
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param object $row 行数据
     * @param array $result 结果统计
     * @param array $batchData 批量数据缓存
     */
    private function prepareBatchRecord(
        string $remoteConnection,
        string $dbName,
        string $table,
        object $row,
        array &$result,
        array &$batchData
    ): void {
        $primaryKey = $this->getPrimaryKey($table);
        $remoteId = $row->$primaryKey;

        // 检查是否已存在映射
        if (isset($this->idMappings[$dbName][$table][$remoteId])) {
            $result['skipped']++;
            return;
        }

        $rowData = (array) $row;

        // 处理外键
        $rowData = $this->processForeignKeys($dbName, $table, $rowData, $remoteConnection);

        // 检查是否需要跳过
        if (isset($rowData['__skip_record__']) && $rowData['__skip_record__'] === true) {
            $result['skipped']++;
            return;
        }

        // 处理图片URL
        $rowData = $this->processImageUrls($table, $rowData);

        // 修复日期
        $rowData = $this->fixInvalidDatetimeValues($rowData);

        // 检查重复（返回检测结果）
        // 注意：在检查重复之前不删除主键，因为 checkDuplicateRecord 需要主键进行去重判断
        $duplicateCheck = $this->checkDuplicateRecord($table, $rowData);

        // 检查完重复后，再删除自增主键（如果需要）
        if (!in_array($table, $this->nonAutoIncrementTables)) {
            unset($rowData[$primaryKey]);
        }

        if ($duplicateCheck['is_duplicate']) {
            // 如果只是排除字段不同，更新排除字段
            if ($duplicateCheck['image_diff_only'] && !empty($duplicateCheck['existing_record'])) {
                $this->updateImageFields($table, $duplicateCheck['existing_record'], $rowData);
                $this->stats['total_updated_duplicates']++;
                $result['updated_duplicates']++;
                $result['duplicates']++;
            } else {
                $result['duplicates']++;
            }
            return;
        }

        // 添加到批量数据
        $batchData[] = [
            'remote_id' => $remoteId,
            'data' => $rowData,
        ];
    }

    /**
     * 执行批量插入
     *
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param array $batchData 批量数据
     * @param array $result 结果统计
     */
    private function executeBatchInsert(string $dbName, string $table, array $batchData, array &$result): void
    {
        if (empty($batchData)) {
            return;
        }

        $primaryKey = $this->getPrimaryKey($table);

        try {
            // 提取纯数据
            $insertData = array_column($batchData, 'data');

            // 批量插入
            DB::table($table)->insert($insertData);

            // 获取插入后的ID（如果是自增表）
            if (!in_array($table, $this->nonAutoIncrementTables)) {
                $lastInsertId = DB::getPdo()->lastInsertId();
                $firstInsertId = $lastInsertId - count($insertData) + 1;

                // 更新映射
                foreach ($batchData as $index => $item) {
                    $newId = $firstInsertId + $index;
                    $remoteId = $item['remote_id'];

                    // 更新映射关系
                    $this->idMappings[$dbName][$table][$remoteId] = $newId;
                    $this->tempMappings[$dbName][$table][$remoteId] = $newId;

                    // 将新插入的记录添加到 changed_ids，供子表同步使用
                    $result['changed_ids'][$remoteId] = $newId;

                    // 记录新增ID（用于子表优先处理）
                    $result['new_ids'][$remoteId] = $newId;

                    $result['new_mappings']++;
                }
            } else {
                // 非自增表，使用原ID
                foreach ($batchData as $item) {
                    $remoteId = $item['remote_id'];
                    $this->idMappings[$dbName][$table][$remoteId] = $remoteId;
                    $this->tempMappings[$dbName][$table][$remoteId] = $remoteId;

                    // 将新插入的记录添加到 changed_ids，供子表同步使用
                    $result['changed_ids'][$remoteId] = $remoteId;

                    // 记录新增ID（用于子表优先处理）
                    $result['new_ids'][$remoteId] = $remoteId;

                    $result['new_mappings']++;
                }
            }

            $result['inserted'] += count($insertData);

        } catch (QueryException $e) {
            // 批量插入失败，回退到逐条插入
            $this->log('warning', "批量插入失败，回退到逐条插入: " . $e->getMessage());

            foreach ($batchData as $item) {
                try {
                    if (in_array($table, $this->nonAutoIncrementTables)) {
                        DB::table($table)->insert($item['data']);
                        $newId = $item['remote_id'];
                    } else {
                        $newId = DB::table($table)->insertGetId($item['data']);
                    }

                    // 更新映射
                    $remoteId = $item['remote_id'];
                    $this->idMappings[$dbName][$table][$remoteId] = $newId;
                    $this->tempMappings[$dbName][$table][$remoteId] = $newId;

                    // 将新插入的记录添加到 changed_ids，供子表同步使用
                    $result['changed_ids'][$remoteId] = $newId;

                    $result['inserted']++;
                    $result['new_mappings']++;
                } catch (QueryException $e2) {
                    if (strpos($e2->getMessage(), 'Duplicate entry') !== false) {
                        $result['skipped']++;
                    } else {
                        $result['errors']++;
                        $this->log('error', "[{$dbName}][{$table}] ID={$item['remote_id']}: " . $e2->getMessage());
                    }
                }
            }
        }
    }

    /**
     * 同步复合主键表
     */
    private function syncCompositeKeyTable(
        string $remoteConnection,
        string $dbName,
        string $table,
        array $compositeKey
    ): array {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0, 'new_mappings' => 0, 'duplicates' => 0, 'updated_duplicates' => 0];
        $startTime = time();

        $remoteRows = DB::connection($remoteConnection)->table($table)->get();

        if ($remoteRows->isEmpty()) {
            $this->line("     ℹ️ 远程表无数据");
            return $result;
        }

        $this->line("     📊 远程表 {$remoteRows->count()} 条记录（复合主键全量对比）");

        $localKeys = [];
        foreach (DB::table($table)->get() as $localRow) {
            $keyValues = array_map(fn($key) => $localRow->$key, $compositeKey);
            $localKeys[implode('_', $keyValues)] = true;
        }

        // 批量插入缓存
        $batchInsertData = [];
        $batchSyncRecords = [];
        $batchSize = 1000;
        $processedCount = 0;
        $totalRemote = $remoteRows->count();
        $maxRetries = 3;  // 最大重试次数
        $retryCount = 0;  // 当前重试次数
        $retryDelay = 2;  // 重试延迟(秒)

        foreach ($remoteRows as $row) {
            $processedCount++;

            if (time() - $startTime > $this->queryTimeout) {
                $retryCount++;

                if ($retryCount <= $maxRetries) {
                    $this->warn("     ⚠️ 查询超时，已处理 {$processedCount}/{$totalRemote}，等待 {$retryDelay} 秒后重试 (第 {$retryCount}/{$maxRetries} 次)");
                    sleep($retryDelay);
                    $startTime = time();  // 重置开始时间
                    continue;  // 继续处理
                } else {
                    $this->error("     ❌ 查询超时，已达到最大重试次数 {$maxRetries}，停止处理");
                    break;
                }
            }

            $rowData = (array) $row;

            $rowData = $this->processForeignKeys($dbName, $table, $rowData, $remoteConnection);

            // 检查是否需要跳过该记录
            if (isset($rowData['__skip_record__']) && $rowData['__skip_record__'] === true) {
                $result['skipped']++;
                continue;
            }

            // 移除临时标记字段
            unset($rowData['__skip_record__']);

            // 处理图片URL转换
            $rowData = $this->processImageUrls($table, $rowData);

            $keyValues = array_map(fn($key) => $rowData[$key] ?? $row->$key ?? '', $compositeKey);
            $keyStr = implode('_', $keyValues);

            if (isset($localKeys[$keyStr])) {
                // 复合主键已存在，检查是否需要更新图片
                if (!$this->isDryRun) {
                    $this->updateCompositeKeyImageFields($table, $compositeKey, $keyValues, $rowData);

                    // 记录更新操作
                    if ($this->useDatabaseTables) {
                        $this->logCompositeKeyUpdate($dbName, $table, $compositeKey, $keyValues, $rowData);
                    }
                }
                $result['skipped']++;
                continue;
            }

            if ($this->isDryRun) {
                $result['inserted']++;
                continue;
            }

            // 添加到批量插入缓存
            $batchInsertData[] = $rowData;

            // 准备同步记录
            if ($this->useDatabaseTables) {
                $batchSyncRecords[] = [
                    'source_site' => $dbName,
                    'source_table' => $table,
                    'source_id' => 0,
                    'target_id' => 0,
                    'sync_type' => 'insert',
                    'status' => 'success',
                    'error_message' => json_encode([
                        'composite_key' => array_combine($compositeKey, $keyValues),
                        'key_string' => $keyStr,
                    ]),
                    'retry_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 达到批次大小，执行批量插入
            if (count($batchInsertData) >= $batchSize) {
                $this->executeCompositeKeyBatchInsert($table, $batchInsertData, $batchSyncRecords, $result);
                $batchInsertData = [];
                $batchSyncRecords = [];
            }
        }

        // 处理剩余数据
        if (!empty($batchInsertData)) {
            $this->executeCompositeKeyBatchInsert($table, $batchInsertData, $batchSyncRecords, $result);
        }

        $this->progressData[$dbName][$table] = [
            'max_id' => time(),
            'sync_time' => date('Y-m-d H:i:s'),
        ];

        if ($result['inserted'] > 0 || $this->isDryRun) {
            $this->line("     ✓ 插入: {$result['inserted']} | 跳过: {$result['skipped']}");
        }

        return $result;
    }

    /**
     * 执行复合主键表批量插入
     *
     * @param string $table 表名
     * @param array $batchInsertData 批量插入数据
     * @param array $batchSyncRecords 同步记录数据
     * @param array $result 结果统计
     */
    private function executeCompositeKeyBatchInsert(string $table, array $batchInsertData, array $batchSyncRecords, array &$result): void
    {
        if (empty($batchInsertData)) {
            return;
        }

        try {
            // 批量插入数据
            DB::table($table)->insert($batchInsertData);
            $result['inserted'] += count($batchInsertData);

            // 批量插入同步记录
            if ($this->useDatabaseTables && !empty($batchSyncRecords)) {
                $chunks = array_chunk($batchSyncRecords, 1000);
                foreach ($chunks as $chunk) {
                    DB::table('oc_sync_record')->insert($chunk);
                }
            }
        } catch (QueryException $e) {
            // 批量插入失败，回退到逐条插入
            $this->log('warning', "复合主键表批量插入失败，回退到逐条插入: " . $e->getMessage());

            foreach ($batchInsertData as $index => $rowData) {
                try {
                    DB::table($table)->insert($rowData);
                    $result['inserted']++;

                    // 记录单条同步
                    if ($this->useDatabaseTables && isset($batchSyncRecords[$index])) {
                        DB::table('oc_sync_record')->insert($batchSyncRecords[$index]);
                    }
                } catch (QueryException $e2) {
                    if (strpos($e2->getMessage(), 'Duplicate entry') !== false) {
                        $result['skipped']++;
                    } else {
                        $result['errors']++;
                        $this->log('error', "[{$table}] 复合主键插入失败: " . $e2->getMessage());
                    }
                }
            }
        }
    }

    /**
     * 记录更新操作到同步表
     *
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param int $recordId 记录ID
     * @param array $newData 新数据
     */
    private function logUpdateToSyncRecord(string $dbName, string $table, int $recordId, array $newData): void
    {
        try {
            // 提取图片字段
            $imageFields = ['image', 'description'];
            $updatedFields = [];

            foreach ($imageFields as $field) {
                if (isset($newData[$field])) {
                    $updatedFields[$field] = $newData[$field];
                }
            }

            // 检查是否已存在记录
            $exists = DB::table('oc_sync_record')
                ->where('source_site', $dbName)
                ->where('source_table', $table)
                ->where('source_id', $recordId)
                ->exists();

            if ($exists) {
                // 更新已存在的记录
                DB::table('oc_sync_record')
                    ->where('source_site', $dbName)
                    ->where('source_table', $table)
                    ->where('source_id', $recordId)
                    ->update([
                        'sync_type' => 'update',
                        'status' => 'success',
                        'error_message' => json_encode([
                            'action' => 'image_update',
                            'updated_fields' => array_keys($updatedFields),
                        ]),
                        'updated_at' => now(),
                    ]);
            } else {
                // 插入新记录
                DB::table('oc_sync_record')->insert([
                    'source_site' => $dbName,
                    'source_table' => $table,
                    'source_id' => $recordId,
                    'target_id' => $recordId,
                    'sync_type' => 'update',
                    'status' => 'success',
                    'error_message' => json_encode([
                        'action' => 'image_update',
                        'updated_fields' => array_keys($updatedFields),
                    ]),
                    'retry_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $this->log('warning', "记录更新操作失败: " . $e->getMessage());
        }
    }

    /**
     * 记录复合主键表更新操作
     *
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param array $compositeKey 复合主键字段
     * @param array $keyValues 主键值
     * @param array $rowData 行数据
     */
    private function logCompositeKeyUpdate(string $dbName, string $table, array $compositeKey, array $keyValues, array $rowData): void
    {
        try {
            $keyStr = implode('_', $keyValues);

            // 提取图片字段
            $imageFields = ['image', 'description'];
            $updatedFields = [];

            foreach ($imageFields as $field) {
                if (isset($rowData[$field])) {
                    $updatedFields[$field] = $rowData[$field];
                }
            }

            // 检查是否已存在记录
            $exists = DB::table('oc_sync_record')
                ->where('source_site', $dbName)
                ->where('source_table', $table)
                ->where('source_id', $keyStr)
                ->exists();

            if ($exists) {
                // 更新已存在的记录
                DB::table('oc_sync_record')
                    ->where('source_site', $dbName)
                    ->where('source_table', $table)
                    ->where('source_id', $keyStr)
                    ->update([
                        'sync_type' => 'update',
                        'status' => 'success',
                        'error_message' => json_encode([
                            'action' => 'image_update',
                            'composite_key' => array_combine($compositeKey, $keyValues),
                            'key_string' => $keyStr,
                            'updated_fields' => array_keys($updatedFields),
                        ]),
                        'updated_at' => now(),
                    ]);
            } else {
                // 插入新记录
                DB::table('oc_sync_record')->insert([
                    'source_site' => $dbName,
                    'source_table' => $table,
                    'source_id' => $keyStr,
                    'target_id' => $keyStr,
                    'sync_type' => 'update',
                    'status' => 'success',
                    'error_message' => json_encode([
                        'action' => 'image_update',
                        'composite_key' => array_combine($compositeKey, $keyValues),
                        'key_string' => $keyStr,
                        'updated_fields' => array_keys($updatedFields),
                    ]),
                    'retry_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $this->log('warning', "记录复合主键更新操作失败: " . $e->getMessage());
        }
    }

    /**
     * 记录复合主键表同步到数据库
     *
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param array $compositeKey 复合主键字段
     * @param array $keyValues 主键值
     * @param array $rowData 行数据
     */
    private function logCompositeKeySync(string $dbName, string $table, array $compositeKey, array $keyValues, array $rowData): void
    {
        try {
            // 生成复合主键字符串作为source_id
            $sourceId = implode('_', $keyValues);

            // 检查是否已存在
            $exists = DB::table('oc_sync_record')
                ->where('source_site', $dbName)
                ->where('source_table', $table)
                ->where('source_id', $sourceId)
                ->exists();

            if (!$exists) {
                DB::table('oc_sync_record')->insert([
                    'source_site' => $dbName,
                    'source_table' => $table,
                    'source_id' => 0, // 复合主键表使用0作为标识
                    'target_id' => 0, // 复合主键表没有单一target_id
                    'sync_type' => 'insert',
                    'status' => 'success',
                    'error_message' => json_encode([
                        'composite_key' => array_combine($compositeKey, $keyValues),
                        'key_string' => $sourceId,
                    ]),
                    'retry_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $this->log('warning', "记录复合主键同步失败: " . $e->getMessage());
        }
    }

    /**
     * 处理外键关联
     */
    private function processForeignKeys(string $dbName, string $table, array $rowData, ?string $remoteConnection = null, ?string $skipForeignKey = null): array
    {
        if (!isset($this->foreignKeys[$table])) {
            return $rowData;
        }

        foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
            // 跳过指定的外键(已经映射完成)
            if ($skipForeignKey !== null && $foreignKey === $skipForeignKey) {
                continue;
            }

            if (!isset($rowData[$foreignKey])) {
                continue;
            }

            $remoteRefId = $rowData[$foreignKey];

            // 常规外键处理
            $refPk = $this->getPrimaryKey($referencedTable);

            // 如果引用表是复合主键表，跳过外键映射（复合主键表已全量同步）
            if (is_array($refPk)) {
                $this->log('info', "  引用表 {$referencedTable} 是复合主键，跳过外键 {$foreignKey} 的映射处理");
                continue;
            }

            // 优先从 tempMappings 中查找（本次同步新增的映射）
            if (isset($this->tempMappings[$dbName][$referencedTable][$remoteRefId])) {
                $rowData[$foreignKey] = $this->tempMappings[$dbName][$referencedTable][$remoteRefId];
                $this->log('debug', "  从 tempMappings 获取映射: {$referencedTable}.{$foreignKey} {$remoteRefId} → {$rowData[$foreignKey]}");
            } elseif (isset($this->idMappings[$dbName][$referencedTable][$remoteRefId])) {
                $rowData[$foreignKey] = $this->idMappings[$dbName][$referencedTable][$remoteRefId];
            } else {
                $localRefId = DB::table($referencedTable)->where($refPk, $remoteRefId)->value($refPk);

                if ($localRefId) {
                    $rowData[$foreignKey] = $localRefId;
                    $this->idMappings[$dbName][$referencedTable][$remoteRefId] = $localRefId;
                } else {
                    $this->log('warning', "  未找到 {$referencedTable} ID {$remoteRefId} 的本地映射，跳过该记录");
                    $rowData['__skip_record__'] = true;
                }
            }
        }

        return $rowData;
    }

    /**
     * 创建远程数据库连接
     */
    private function createRemoteConnection(array $remoteDb): string
    {
        $connectionName = 'remote_product_' . $remoteDb['name'];
        $dbName = $remoteDb['name'];

        // 检查IP缓存
        $cacheFile = $this->config['mappings']['ip_cache_file'] ?? storage_path('app/remote_product_sync/ip_cache.json');
        $cacheTTL = $this->config['ip_cache_ttl'] ?? 3600; // 默认缓存1小时

        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true) ?? [];
            if (isset($cacheData[$dbName])) {
                $cached = $cacheData[$dbName];
                $cacheAge = time() - ($cached['cached_at'] ?? 0);

                // 缓存未过期，直接使用缓存的IP
                if ($cacheAge < $cacheTTL) {
                    $this->line("  ✅ 使用缓存IP: {$cached['host']} (缓存时间: " . round($cacheAge / 60, 1) . "分钟前)");

                    // 快速Ping验证缓存的IP是否仍然可用
                    if ($this->pingHost($cached['host'])) {
                        $this->ipCache[$dbName] = $cached;

                        // 创建连接
                        config(["database.connections.{$connectionName}" => [
                            'driver' => 'mysql',
                            'host' => $cached['host'],
                            'port' => $remoteDb['db_port'] ?? '3306',
                            'database' => $remoteDb['db_name'],
                            'username' => $remoteDb['db_username'],
                            'password' => $remoteDb['db_pwd'],
                            'charset' => 'utf8mb4',
                            'collation' => 'utf8mb4_unicode_ci',
                            'prefix' => '',
                            'strict' => false,
                            'engine' => null,
                        ]]);

                        DB::connection($connectionName)->statement("SET SESSION wait_timeout = {$this->queryTimeout}");
                        DB::connection($connectionName)->statement("SET SESSION interactive_timeout = {$this->queryTimeout}");

                        $this->stats['db_stats'][$dbName]['ip_used'] = $cached['host'];
                        $this->stats['db_stats'][$dbName]['ip_response_time'] = $cached['time'];

                        return $connectionName;
                    } else {
                        $this->warn("  ⚠️ 缓存IP不可用，重新测试...");
                    }
                }
            }
        }

        // 无缓存或缓存过期，使用Ping预筛选IP
        $hosts = array_values(array_filter([
            $remoteDb['db_host'] ?? null,
            $remoteDb['server_ip'] ?? null,
            $remoteDb['server_inner_ip'] ?? null,
        ]));

        $this->line("  🔍 Ping测试 " . count($hosts) . " 个IP地址...");

        // 使用Ping预筛选最快的IP
        $pingResults = $this->pingTestHosts($hosts);

        if (empty($pingResults)) {
            throw new \Exception("所有IP地址Ping测试失败");
        }

        // 按响应时间排序，选择最快的IP
        $fastestIP = $pingResults[0]['host'];
        $fastestTime = $pingResults[0]['time'];

        $this->line("  🎯 Ping最快: {$fastestIP} (" . round($fastestTime * 1000, 2) . "ms)");

        // 使用最快的IP建立MySQL连接
        $this->line("  🔌 建立MySQL连接...");

        $maxRetries = $this->config['ip_retry']['max_retries'] ?? 3;
        for ($retry = 1; $retry <= $maxRetries; $retry++) {
            try {
                config(["database.connections.{$connectionName}" => [
                    'driver' => 'mysql',
                    'host' => $fastestIP,
                    'port' => $remoteDb['db_port'] ?? '3306',
                    'database' => $remoteDb['db_name'],
                    'username' => $remoteDb['db_username'],
                    'password' => $remoteDb['db_pwd'],
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => false,
                    'engine' => null,
                ]]);

                DB::connection($connectionName)->getPdo();
                DB::connection($connectionName)->statement("SET SESSION wait_timeout = {$this->queryTimeout}");
                DB::connection($connectionName)->statement("SET SESSION interactive_timeout = {$this->queryTimeout}");

                $this->stats['db_stats'][$dbName]['ip_used'] = $fastestIP;
                $this->stats['db_stats'][$dbName]['ip_response_time'] = $fastestTime;

                // 保存到缓存
                $this->saveIPCache($dbName, $fastestIP, $fastestTime, $cacheFile);

                $this->line("  ✅ MySQL连接成功");

                return $connectionName;
            } catch (\Exception $e) {
                if ($retry < $maxRetries) {
                    $this->warn("  ⚠️ 第 {$retry}/{$maxRetries} 次MySQL连接失败，准备重试...");
                    usleep(500000);
                } else {
                    throw new \Exception("MySQL连接失败（已重试{$maxRetries}次）: " . $e->getMessage());
                }
            }
        }

        throw new \Exception("无法建立MySQL连接");
    }

    /**
     * Ping测试多个主机，返回按响应时间排序的结果
     */
    private function pingTestHosts(array $hosts): array
    {
        $results = [];
        $timeout = $this->connectionTimeout;

        foreach ($hosts as $host) {
            $startTime = microtime(true);
            $pingResult = $this->pingHost($host, $timeout);
            $elapsed = microtime(true) - $startTime;

            if ($pingResult) {
                $results[] = [
                    'host' => $host,
                    'time' => $elapsed,
                ];
                $this->line("    ✅ {$host} - " . round($elapsed * 1000, 2) . "ms");
            } else {
                $this->line("    ❌ {$host} - 超时");
            }
        }

        // 按响应时间排序
        usort($results, fn($a, $b) => $a['time'] <=> $b['time']);

        return $results;
    }

    /**
     * Ping测试单个主机
     */
    private function pingHost(string $host, int $timeout = 3): bool
    {
        // Windows系统使用ping命令
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "ping -n 1 -w " . ($timeout * 1000) . " " . escapeshellarg($host);
            $result = shell_exec($cmd);
            return strpos($result, 'TTL=') !== false || strpos($result, '时间=') !== false;
        }

        // Linux/Unix系统使用ping命令
        $cmd = "ping -c 1 -W " . $timeout . " " . escapeshellarg($host) . " 2>&1";
        $result = shell_exec($cmd);
        return strpos($result, 'ttl=') !== false || strpos($result, '1 received') !== false;
    }

    /**
     * 保存IP缓存
     */
    private function saveIPCache(string $dbName, string $host, float $time, string $cacheFile): void
    {
        $cacheData = [];

        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        $cacheData[$dbName] = [
            'host' => $host,
            'time' => $time,
            'cached_at' => time(),
        ];

        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));

        $this->ipCache[$dbName] = $cacheData[$dbName];
        $this->log('info', "IP缓存已保存: {$dbName} -> {$host}");
    }

    /**
     * 并发测试多个IP主机
     */
    private function connectWithParallelHosts(string $connectionName, array $hosts, array $remoteDb): array
    {
        $results = [];
        $successHost = null;
        $successTime = PHP_FLOAT_MAX;

        foreach ($hosts as $index => $host) {
            $connKey = $connectionName . '_temp_' . $index;

            config(["database.connections.{$connKey}" => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => $remoteDb['db_port'] ?? '3306',
                'database' => $remoteDb['db_name'],
                'username' => $remoteDb['db_username'],
                'password' => $remoteDb['db_pwd'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => null,
            ]]);

            $results[$index] = [
                'host' => $host,
                'connKey' => $connKey,
                'success' => false,
                'time' => 0,
                'error' => null,
            ];
        }

        $startTime = microtime(true);
        $completed = 0;
        $maxWaitTime = $this->connectionTimeout;

        while ($completed < count($hosts) && (microtime(true) - $startTime) < $maxWaitTime) {
            foreach ($results as $index => &$result) {
                if ($result['success'] || $result['error']) continue;

                try {
                    DB::connection($result['connKey'])->getPdo();
                    $elapsed = microtime(true) - $startTime;

                    $result['success'] = true;
                    $result['time'] = $elapsed;

                    $this->line("  ✅ {$result['host']} 响应成功 (" . round($elapsed * 1000, 2) . "ms)");

                    if ($elapsed < $successTime) {
                        $successTime = $elapsed;
                        $successHost = $index;
                    }

                    $completed++;
                } catch (\Exception $e) {
                    $elapsed = microtime(true) - $startTime;
                    $errorMsg = $e->getMessage();

                    $isTimeout = strpos($errorMsg, '2002') !== false ||
                                 strpos($errorMsg, 'timeout') !== false ||
                                 $elapsed >= $maxWaitTime;

                    if ($isTimeout) {
                        $result['error'] = $errorMsg;
                        $this->line("  ❌ {$result['host']} 超时/失败");
                        $completed++;
                        DB::purge($result['connKey']);
                    }
                }
            }

            if ($completed < count($hosts)) {
                usleep(10000);
            }
        }

        foreach ($results as $idx => $result) {
            if ($idx !== $successHost) {
                DB::purge($result['connKey']);
            }
        }

        if ($successHost !== null) {
            $bestResult = $results[$successHost];
            config(["database.connections.{$connectionName}" => config("database.connections.{$bestResult['connKey']}")]);

            $this->line("  🎯 使用最快响应: {$bestResult['host']} (" . round($bestResult['time'] * 1000, 2) . "ms)");

            return ['success' => true, 'host' => $bestResult['host'], 'time' => $bestResult['time']];
        }

        $errors = array_map(fn($r) => "{$r['host']}: " . substr($r['error'] ?? 'Unknown', 0, 80), array_filter($results, fn($r) => $r['error']));

        return ['success' => false, 'error' => implode('; ', $errors)];
    }

    /**
     * 断开远程数据库连接
     */
    private function disconnectRemote(string $dbName): void
    {
        $connectionName = 'remote_product_' . $dbName;
        DB::purge($connectionName);
    }

    /**
     * 获取表的主键
     */
    private function getPrimaryKey(string $table): string|array
    {
        return $this->primaryKeys[$table] ?? 'id';
    }

    /**
     * 生成同步报告
     */
    private function generateReport(): void
    {
        $endTime = microtime(true);
        $duration = round($endTime - $this->stats['start_time'], 2);

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                    同步完成统计报告                         ║');
        $this->info('╠══════════════════════════════════════════════════════════════╣');
        $this->info("║ 处理数据库: {$this->stats['total_dbs']} 个                                               ║");
        $this->info("║ 处理数据表: {$this->stats['total_tables']} 个                                               ║");
        $this->info("║ 新增记录数: {$this->stats['total_inserted']} 条                                              ║");
        $this->info("║ 跳过记录数: {$this->stats['total_skipped']} 条                                              ║");
        $this->info("║ 错误记录数: {$this->stats['total_errors']} 条                                              ║");
        $this->info("║ 新增映射数: {$this->stats['new_mappings']} 条                                              ║");
        $this->info("║ 重复记录数: {$this->stats['total_duplicates']} 条                                              ║");
        $this->info("║ 更新重复数: {$this->stats['total_updated_duplicates']} 条                                              ║");
        $this->info("║ 执行耗时: {$duration} 秒                                                ║");
        $this->info('╚══════════════════════════════════════════════════════════════╝');

        $this->newLine();
        $this->info('📊 各数据库同步详情:');
        foreach ($this->stats['db_stats'] as $dbName => $dbStat) {
            $dbDuration = round(microtime(true) - $dbStat['start_time'], 2);
            $ipInfo = $dbStat['ip_used'] ? " (IP: {$dbStat['ip_used']}, " . round($dbStat['ip_response_time'] * 1000, 2) . "ms)" : '';
            $dupInfo = isset($dbStat['duplicates']) ? " | 重复: {$dbStat['duplicates']}" : '';
            $updatedDupInfo = isset($dbStat['updated_duplicates']) ? " | 更新重复: {$dbStat['updated_duplicates']}" : '';
            $this->line("  {$dbName}: +{$dbStat['inserted']} | 跳过: {$dbStat['skipped']} | 错误: {$dbStat['errors']} | 表: {$dbStat['tables']}{$dupInfo}{$updatedDupInfo} | 耗时: {$dbDuration}s{$ipInfo}");
        }

        $this->newLine();
        $this->info("📁 日志文件: {$this->logFile}");
        $this->info("📊 进度文件: {$this->config['mappings']['progress_file']}");
        $this->info("🔗 映射文件: {$this->config['mappings']['json_file']}");
        $this->info("📜 历史备份: {$this->config['mappings']['history_dir']}");

        // 计算监控指标
        $this->outputMonitoringMetrics();

        $this->log('info', "执行完成 - 插入: {$this->stats['total_inserted']}, 跳过: {$this->stats['total_skipped']}, 错误: {$this->stats['total_errors']}, 新映射: {$this->stats['new_mappings']}, 重复: {$this->stats['total_duplicates']}, 更新重复: {$this->stats['total_updated_duplicates']}, 耗时: {$duration}秒");
    }

    /**
     * 输出监控指标
     */
    private function outputMonitoringMetrics(): void
    {
        $totalRecords = $this->stats['total_inserted'] + $this->stats['total_skipped'] + $this->stats['total_errors'] + $this->stats['total_duplicates'];
        
        if ($totalRecords == 0) {
            return;
        }

        // 计算指标
        $insertRate = round(($this->stats['total_inserted'] / $totalRecords) * 100, 2);
        $updateRate = round(($this->stats['total_updated_duplicates'] / $totalRecords) * 100, 2);
        $successRate = round((($this->stats['total_inserted'] + $this->stats['total_duplicates']) / $totalRecords) * 100, 2);
        $failedRate = round(($this->stats['total_errors'] / $totalRecords) * 100, 2);

        // 判断健康状态
        if ($successRate > 99) {
            $healthStatus = '✅ 健康';
            $statusColor = 'info';
        } elseif ($successRate >= 95) {
            $healthStatus = '⚠️ 警告';
            $statusColor = 'warn';
        } else {
            $healthStatus = '❌ 异常';
            $statusColor = 'error';
        }

        $this->newLine();
        $this->info('📈 监控指标:');
        $this->line("  插入率: {$insertRate}%");
        $this->line("  更新率: {$updateRate}%");
        $this->line("  成功率: {$successRate}%");
        $this->line("  失败率: {$failedRate}%");
        $this->line("  健康状态: {$healthStatus}");

        // 记录监控指标到日志
        $this->log('info', "监控指标 - 插入率: {$insertRate}%, 更新率: {$updateRate}%, 成功率: {$successRate}%, 失败率: {$failedRate}%, 健康状态: {$healthStatus}");
    }

    /**
     * 写入日志
     */
    private function log(string $level, string $message): void
    {
        $ts = date('Y-m-d H:i:s');
        $line = "[{$ts}] [{$level}] {$message}\n";

        file_put_contents($this->logFile, $line, FILE_APPEND);

        if ($level === 'error') {
            file_put_contents($this->errorLogFile, $line, FILE_APPEND);
        }
    }

    /**
     * 检测记录是否重复
     */
    /**
     * 检查重复记录（返回详细信息）
     *
     * @param string $table 表名
     * @param array $rowData 行数据
     * @return array ['is_duplicate' => bool, 'image_diff_only' => bool, 'existing_record' => object|null]
     */
    private function checkDuplicateRecord(string $table, array $rowData): array
    {
        $result = [
            'is_duplicate' => false,
            'image_diff_only' => false,
            'existing_record' => null,
        ];

        try {
            // 记录开始检查
            $primaryKey = isset($rowData['product_id']) ? $rowData['product_id'] : (isset($rowData['category_id']) ? $rowData['category_id'] : 'N/A');
            $this->log('debug', "[{$this->currentDbName}][{$table}] 开始去重检查 - primary_key={$primaryKey}, row_data_keys=" . json_encode(array_keys($rowData)));

            // ========== oc_product 表特殊处理：多阶段对比规则 ==========
            // 对比规则优先级：
            // 01: date_added + model
            // 02: date_added + model + 图片提纯后 “catalog/”
            // 03: date_added + model + 图片提纯后 “catalog/” + name 完全一样
            // 04: date_added + model + 图片提纯后 “catalog/” + name 相似度 ≥95% 视为已存在（需要更新）
            if ($table === 'oc_product') {
                return $this->checkDuplicateProduct($table, $rowData);
            }

            // 不参与匹配的字段（所有表通用）
            $excludeFields = ['sort_order', 'date_modified', 'image', 'status', 'description','price','viewed'];

            // ========== 常规去重逻辑 ==========
            // 构建查询
            $query = DB::table($table);
            $whereConditions = [];
            
            // 排除指定字段进行全字段对比
            foreach ($rowData as $field => $value) {
                if (!in_array($field, $excludeFields)) {
                    $query->where($field, $value);
                    $whereConditions[] = "{$field} = '" . (is_string($value) ? str_replace("'", "''", $value) : $value) . "'";
                }
            }
            
            // 记录SQL查询
            $sqlConditions = implode(' AND ', $whereConditions);
            $this->log('debug', "[{$this->currentDbName}][{$table}] 常规去重查询 - SQL: SELECT * FROM {$table} WHERE {$sqlConditions}");

            $existingRecord = $query->first();

            // 记录查询结果
            if (!$existingRecord) {
                $this->log('debug', "[{$this->currentDbName}][{$table}] 常规去重查询结果: 不存在，标记为新增");
                return $result;
            }

            $result['is_duplicate'] = true;
            $result['existing_record'] = $existingRecord;
            $this->log('debug', "[{$this->currentDbName}][{$table}] 常规去重查询结果: 存在，标记为重复");

            // 检查是否只是排除字段不同（图片、排序、状态等）
            $excludeDiffOnly = true;
            $this->log('debug', "[{$this->currentDbName}][{$table}] 开始检查排除字段差异");
            
            // 需要检查差异的排除字段（用于判断是否需要更新）
            $diffCheckFields = ['image', 'description', 'sort_order', 'date_modified', 'status'];
            
            foreach ($diffCheckFields as $field) {
                if (!isset($rowData[$field])) {
                    $this->log('debug', "[{$this->currentDbName}][{$table}] 排除字段 {$field} 不存在于行数据中，跳过");
                    continue;
                }

                $localValue = $existingRecord->$field ?? '';
                $remoteValue = $rowData[$field];

                if ($field === 'image') {
                    // 图片字段提纯后对比
                    $localPure = $this->extractImagePath($localValue);
                    $remotePure = $this->extractImagePath($remoteValue);
                    $this->log('debug', "[{$this->currentDbName}][{$table}] 排除字段对比 - field={$field}, local_value=" . substr($localValue, 0, 100) . ", remote_value=" . substr($remoteValue, 0, 100));
                    $this->log('debug', "[{$this->currentDbName}][{$table}] 提纯后对比 - local_pure={$localPure}, remote_pure={$remotePure}");
                    
                    if ($localPure !== $remotePure) {
                        $excludeDiffOnly = false;
                        $this->log('debug', "[{$this->currentDbName}][{$table}] 排除字段 {$field} 不同，标记为需要更新");
                        break;
                    }
                } else {
                    // 其他字段直接对比
                    $this->log('debug', "[{$this->currentDbName}][{$table}] 排除字段对比 - field={$field}, local_value=" . substr((string)$localValue, 0, 100) . ", remote_value=" . substr((string)$remoteValue, 0, 100));
                    
                    if ($localValue !== $remoteValue) {
                        $excludeDiffOnly = false;
                        $this->log('debug', "[{$this->currentDbName}][{$table}] 排除字段 {$field} 不同，标记为需要更新");
                        break;
                    }
                }
            }

            // 如果非排除字段都相同，且排除字段也相同，则是完全重复
            // 如果非排除字段都相同，但排除字段不同，则只是排除字段不同
            $result['image_diff_only'] = !$excludeDiffOnly;
            $this->log('debug', "[{$this->currentDbName}][{$table}] 图片差异检查结果 - image_diff_only=" . ($result['image_diff_only'] ? 'true' : 'false') . ", is_duplicate=" . ($result['is_duplicate'] ? 'true' : 'false'));

            $this->log('debug', "[{$this->currentDbName}][{$table}] 去重检查完成 - 结果: " . json_encode(['is_duplicate' => $result['is_duplicate'], 'image_diff_only' => $result['image_diff_only']]));
            return $result;
        } catch (\Exception $e) {
            $this->log('warning', "去重检测失败 [{$table}]: " . $e->getMessage());
            $this->log('error', "去重检测失败详情 [{$table}] - Exception: " . $e->getMessage() . ", StackTrace: " . $e->getTraceAsString());
            return $result;
        }
    }

    /**
     * oc_product 表专用去重检查（多阶段对比规则）
     *
     * 对比规则优先级：
     * 01: date_added + model
     * 02: date_added + model + 图片提纯后 “catalog/”
     * 03: date_added + model + 图片提纯后 “catalog/” + name 完全一样
     * 04: date_added + model + 图片提纯后 “catalog/” + name 相似度 ≥95% 视为已存在（需要更新）
     *
     * @param string $table 表名
     * @param array $rowData 行数据
     * @return array ['is_duplicate' => bool, 'image_diff_only' => bool, 'existing_record' => object|null]
     */
    private function checkDuplicateProduct(string $table, array $rowData): array
    {
        $result = [
            'is_duplicate' => false,
            'image_diff_only' => false,
            'existing_record' => null,
        ];

        try {
            $remoteDateAdded = $rowData['date_added'] ?? '';
            $remoteModel = $rowData['model'] ?? '';
            $remoteImage = $rowData['image'] ?? '';
            $remoteProductId = $rowData['product_id'] ?? 0;

            $this->log('debug', "[{$this->currentDbName}][{$table}] 开始多阶段去重检查 - product_id={$remoteProductId}, model={$remoteModel}, date_added={$remoteDateAdded}");

            // ========== 阶段 01: date_added + model ==========
            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 01: date_added + model 对比");
            
            $stage1Query = DB::table('oc_product')
                ->where('date_added', $remoteDateAdded)
                ->where('model', $remoteModel);

            $stage1Count = $stage1Query->count();
            
            if ($stage1Count === 0) {
                $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 01 未匹配，标记为新增");
                return $result;
            }

            $stage1Records = $stage1Query->get();
            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 01 匹配到 {$stage1Count} 条记录");

            // ========== 阶段 02: + 图片提纯后对比 ==========
            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 02: + 图片提纯后对比");
            
            $remoteImagePure = $this->extractImagePath($remoteImage);
            $stage2Matches = [];

            foreach ($stage1Records as $record) {
                $localImage = $record->image ?? '';
                $localImagePure = $this->extractImagePath($localImage);
                
                if ($localImagePure === $remoteImagePure) {
                    $stage2Matches[] = $record;
                }
            }

            if (empty($stage2Matches)) {
                $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 02 未匹配，标记为新增");
                return $result;
            }

            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 02 匹配到 " . count($stage2Matches) . " 条记录");

            // 如果只有一条匹配，直接返回
            if (count($stage2Matches) === 1) {
                $existingRecord = $stage2Matches[0];
                $result['is_duplicate'] = true;
                $result['existing_record'] = $existingRecord;
                $result['image_diff_only'] = false;
                $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 02 唯一匹配，标记为重复");
                return $result;
            }

            // ========== 阶段 03: + name 完全一样 ==========
            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 03: + name 完全一样");
            
            // 获取远程产品名称
            $remoteName = $this->getRemoteProductName($remoteProductId);
            $stage3Matches = [];

            foreach ($stage2Matches as $record) {
                $localName = $this->getLocalProductName($record->product_id);
                
                if ($localName === $remoteName) {
                    $stage3Matches[] = $record;
                }
            }

            if (!empty($stage3Matches)) {
                $existingRecord = $stage3Matches[0];
                $result['is_duplicate'] = true;
                $result['existing_record'] = $existingRecord;
                $result['image_diff_only'] = false;
                $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 03 匹配到 " . count($stage3Matches) . " 条记录，标记为重复");
                return $result;
            }

            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 03 未匹配，继续下一阶段");

            // ========== 阶段 04: name 相似度 ≥95% 视为已存在（需要更新） ==========
            $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 04: name 相似度 ≥95%");
            
            $maxSimilarity = 0;
            $matchedRecord = null;

            foreach ($stage2Matches as $record) {
                $localName = $this->getLocalProductName($record->product_id);
                
                if (!empty($localName) && !empty($remoteName)) {
                    $similarity = $this->calculateSimilarity($remoteName, $localName);
                    
                    if ($similarity >= 95 && $similarity > $maxSimilarity) {
                        $maxSimilarity = $similarity;
                        $matchedRecord = $record;
                    }
                }
            }

            if ($matchedRecord) {
                $result['is_duplicate'] = true;
                $result['existing_record'] = $matchedRecord;
                $result['image_diff_only'] = true; // 相似度匹配视为需要更新
                $this->log('debug', "[{$this->currentDbName}][{$table}] 阶段 04 匹配成功，相似度 {$maxSimilarity}%，标记为重复（需要更新）");
                return $result;
            }

            $this->log('debug', "[{$this->currentDbName}][{$table}] 所有阶段均未匹配，标记为新增");
            return $result;

        } catch (\Exception $e) {
            $this->log('warning', "oc_product 去重检测失败: " . $e->getMessage());
            $this->log('error', "oc_product 去重检测失败详情 - Exception: " . $e->getMessage() . ", StackTrace: " . $e->getTraceAsString());
            return $result;
        }
    }

    /**
     * 获取远程产品名称
     */
    private function getRemoteProductName(int $productId): string
    {
        if (empty($this->currentRemoteConnection)) {
            return '';
        }

        // 优先从缓存获取
        if (isset($this->productDescriptionCache[$productId]) && !empty($this->productDescriptionCache[$productId])) {
            return $this->productDescriptionCache[$productId][0]->name ?? '';
        }

        // 从数据库查询
        $description = DB::connection($this->currentRemoteConnection)
            ->table('oc_product_description')
            ->where('product_id', $productId)
            ->first();

        return $description->name ?? '';
    }

    /**
     * 获取本地产品名称
     */
    private function getLocalProductName(int $productId): string
    {
        $description = DB::table('oc_product_description')
            ->where('product_id', $productId)
            ->first();

        return $description->name ?? '';
    }

    /**
     * 通过 oc_product_description 和 oc_product.model 查找匹配的本地产品（使用相似度匹配）
     * 
     * 同步 oc_product 时，同时检索远程的 oc_product_description，提取以下字段进行相似度对比：
     * - oc_product.product_id
     * - oc_product.model
     * - oc_product_description.name
     * - oc_product_description.meta_title
     * - oc_product_description.meta_description
     * 
     * @param int $remoteProductId 远程产品ID
     * @return array ['product_id' => int|null, 'similarity' => float]
     */
    private function findMatchingProductByDescription(int $remoteProductId): array
    {
        $result = ['product_id' => null, 'similarity' => 0.0];

        // 需要通过远程连接查询产品描述
        if (empty($this->currentRemoteConnection)) {
            return $result;
        }

        try {
            // ========== 优先使用缓存数据 ==========
            
            // 从缓存获取远程产品描述
            $remoteDescriptions = $this->productDescriptionCache[$remoteProductId] ?? [];
            
            // 如果缓存中没有，再从数据库查询（兼容非批量模式）
            if (empty($remoteDescriptions)) {
                $remoteDescriptions = DB::connection($this->currentRemoteConnection)
                    ->table('oc_product_description')
                    ->where('product_id', $remoteProductId)
                    ->get()
                    ->all();
            }

            if (empty($remoteDescriptions)) {
                $this->log('info', "[{$this->currentDbName}][oc_product] 远程 oc_product_description 不存在 - product_id={$remoteProductId}");
                return $result;
            }

            // 使用第一个描述记录
            $remoteDescription = $remoteDescriptions[0];

            // 从缓存获取远程产品 model
            $remoteModel = $this->remoteProductModelCache[$remoteProductId] ?? '';
            
            // 如果缓存中没有，再从数据库查询
            if (empty($remoteModel)) {
                $remoteProduct = DB::connection($this->currentRemoteConnection)
                    ->table('oc_product')
                    ->where('product_id', $remoteProductId)
                    ->first();
                $remoteModel = $remoteProduct->model ?? '';
            }

            // 组合字段进行相似度对比
            $remoteProductIdStr = (string)$remoteProductId;
            $remoteName = $remoteDescription->name ?? '';
            $remoteMetaTitle = $remoteDescription->meta_title ?? '';
            $remoteMetaDesc = $remoteDescription->meta_description ?? '';
            $remoteDescProductIdStr = (string)($remoteDescription->product_id ?? '');
            
            $remoteText = trim($remoteProductIdStr . ' ' . $remoteModel . ' ' . $remoteDescProductIdStr . ' ' . $remoteName . ' ' . $remoteMetaTitle . ' ' . $remoteMetaDesc);

            if (empty($remoteText)) {
                $this->log('info', "[{$this->currentDbName}][oc_product] 远程字段组合为空 - product_id={$remoteProductId}");
                return $result;
            }

            $this->log('debug', "[{$this->currentDbName}][oc_product] 远程匹配文本: product_id={$remoteProductId}, model={$remoteModel}, name={$remoteName}");

            // ========== 使用缓存的本地数据进行对比 ==========
            
            // 从缓存获取本地产品描述（只对比已存在 product_id 的记录）
            $localDescriptions = $this->localProductDescriptionCache[$remoteProductId] ?? [];
            
            // 如果缓存中没有，再从数据库查询（兼容非批量模式）
            if (empty($localDescriptions)) {
                $localDescriptions = DB::table('oc_product_description')
                    ->where('product_id', $remoteProductId)
                    ->get()
                    ->all();
            }

            if (empty($localDescriptions)) {
                $this->log('debug', "[{$this->currentDbName}][oc_product] 本地 oc_product_description 不存在 - product_id={$remoteProductId}");
                return $result;
            }

            $maxSimilarity = 0;
            $matchedProductId = null;

            foreach ($localDescriptions as $localDesc) {
                // 从缓存获取本地产品的 model
                $localModel = $this->localProductModelCache[$localDesc->product_id] ?? '';
                
                // 如果缓存中没有，再从数据库查询
                if (empty($localModel)) {
                    $localProduct = DB::table('oc_product')
                        ->where('product_id', $localDesc->product_id)
                        ->first();
                    $localModel = $localProduct->model ?? '';
                }
                
                // 获取本地产品的 product_id
                $localProductIdStr = (string)($localDesc->product_id ?? '');
                
                // 组合本地的字段：product_id + model + product_id(description表) + name + meta_title + meta_description
                $localText = trim($localProductIdStr . ' ' . 
                                 $localModel . ' ' . 
                                 $localProductIdStr . ' ' .  // oc_product_description.product_id
                                 ($localDesc->name ?? '') . ' ' . 
                                 ($localDesc->meta_title ?? '') . ' ' . 
                                 ($localDesc->meta_description ?? ''));

                if (empty($localText)) {
                    continue;
                }

                // 计算相似度
                $similarity = $this->calculateSimilarity($remoteText, $localText);

                // 记录最高相似度
                if ($similarity > $maxSimilarity) {
                    $maxSimilarity = $similarity;
                    $matchedProductId = $localDesc->product_id;
                }
            }

            $result['product_id'] = $matchedProductId;
            $result['similarity'] = $maxSimilarity;

            return $result;
        } catch (\Exception $e) {
            $this->log('warning', "通过描述查找产品失败: " . $e->getMessage());
            return $result;
        }
    }

    /**
     * 计算两个字符串的相似度（使用 Jaccard 相似度）
     * 
     * @param string $str1 字符串1
     * @param string $str2 字符串2
     * @return float 相似度（0-100）
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // 转换为小写
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);

        // 分词（使用空格分割）
        // 使用 \p{Han} 匹配中文字符（PCRE2 兼容）
        // 保留撇号 ' 和连字符 -，用于处理类似 D'ANCRE、H104141B-CH 这样的字符串
        $words1 = array_unique(explode(' ', preg_replace('/[^a-zA-Z0-9\p{Han}\s\'\-]/u', ' ', $str1)));
        $words2 = array_unique(explode(' ', preg_replace('/[^a-zA-Z0-9\p{Han}\s\'\-]/u', ' ', $str2)));

        // 过滤空词
        $words1 = array_filter($words1, function($word) { return !empty(trim($word)); });
        $words2 = array_filter($words2, function($word) { return !empty(trim($word)); });

        if (empty($words1) && empty($words2)) {
            return 100.0;
        }

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        // 计算交集和并集
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        // Jaccard 相似度 = 交集大小 / 并集大小
        $similarity = (count($intersection) / count($union)) * 100;

        return round($similarity, 2);
    }

    /**
     * 更新图片字段
     *
     * @param string $table 表名
     * @param object $existingRecord 已存在的记录
     * @param array $newData 新数据
     */
    private function updateImageFields(string $table, object $existingRecord, array $newData): void
    {
        $primaryKey = $this->getPrimaryKey($table);
        $recordId = $existingRecord->$primaryKey;

        // 需要更新的排除字段
        $excludeFields = ['sort_order', 'date_modified', 'image', 'status', 'description', 'price', 'viewed'];
        $updateData = [];

        foreach ($excludeFields as $field) {
            if (isset($newData[$field])) {
                $updateData[$field] = $newData[$field];
            }
        }

        if (!empty($updateData)) {
            DB::table($table)->where($primaryKey, $recordId)->update($updateData);
            
            // 记录更新日志
            $this->log('info', "[{$this->currentDbName}][{$table}] 更新排除字段 - ID={$recordId}, fields=" . json_encode(array_keys($updateData)));
        }
    }

    /**
     * 更新复合主键表的图片字段
     *
     * @param string $table 表名
     * @param array $compositeKey 复合主键字段
     * @param array $keyValues 主键值
     * @param array $newData 新数据
     */
    private function updateCompositeKeyImageFields(string $table, array $compositeKey, array $keyValues, array $newData): void
    {
        // 需要更新的排除字段
        $excludeFields = ['sort_order', 'date_modified', 'image', 'status', 'description', 'price', 'viewed'];
        $updateData = [];

        foreach ($excludeFields as $field) {
            if (isset($newData[$field])) {
                $updateData[$field] = $newData[$field];
            }
        }

        if (empty($updateData)) {
            return;
        }

        // 构建查询条件
        $query = DB::table($table);
        foreach ($compositeKey as $index => $key) {
            $query->where($key, $keyValues[$index]);
        }

        $query->update($updateData);
    }

    private function isDuplicateRecord(string $table, array $rowData): bool
    {
        $check = $this->checkDuplicateRecord($table, $rowData);
        return $check['is_duplicate'] && !$check['image_diff_only'];
    }

    /**
     * 提取图片路径（去除域名部分）
     *
     * @param string|null $value 图片值
     * @return string 提纯后的路径
     */
    private function extractImagePath(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // 如果是HTML内容，提取所有图片路径
        if (strpos($value, '<img') !== false) {
            return $this->extractImagePathsFromHtml($value);
        }

        // 如果是完整URL，提取路径部分
        if (preg_match('#https?://[^/]+/image/(.*)#i', $value, $matches)) {
            return $matches[1];
        }

        // 如果是相对路径，直接返回
        if (strpos($value, 'catalog/') === 0 || strpos($value, 'data/') === 0) {
            return $value;
        }

        // 其他情况返回原值
        return $value;
    }

    /**
     * 从HTML中提取图片路径（用于对比）
     *
     * @param string $html HTML内容
     * @return string 提纯后的路径（多个路径用|分隔）
     */
    private function extractImagePathsFromHtml(string $html): string
    {
        $pattern = '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i';
        preg_match_all($pattern, $html, $matches);

        if (empty($matches[1])) {
            return $html;
        }

        $paths = [];
        foreach ($matches[1] as $url) {
            $paths[] = $this->extractImagePath($url);
        }

        // 返回排序后的路径组合（用于对比）
        sort($paths);
        return implode('|', $paths);
    }

    /**
     * 记录重复数据到日志文件
     */
    private function logDuplicateRecord(string $dbName, string $table, $remoteId, array $rowData): void
    {
        $duplicateLogFile = $this->config['logging']['log_dir'] . "/duplicates_" . date('Ymd') . ".log";

        $dataStr = json_encode($rowData, JSON_UNESCAPED_UNICODE);

        $ts = date('Y-m-d H:i:s');
        $line = "[{$ts}] [{$dbName}][{$table}] 远程ID: {$remoteId} | 数据: {$dataStr}\n";
        file_put_contents($duplicateLogFile, $line, FILE_APPEND);

        $this->log('info', "[{$dbName}][{$table}] 远程ID {$remoteId} 数据重复，已跳过");
    }

    /**
     * 修复无效的日期时间值
     */
    private function fixInvalidDatetimeValues(array $rowData): array
    {
        foreach ($rowData as $field => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (preg_match('/^0000-00-00(\s+00:00:00)?$/', $value) ||
                strpos($value, '0000-00-00') !== false) {
                if (strpos($value, ' ') !== false) {
                    $rowData[$field] = '1970-01-01 00:00:00';
                } else {
                    $rowData[$field] = '1970-01-01';
                }
            }
        }

        return $rowData;
    }

    /**
     * 处理图片URL转换
     *
     * @param string $table 表名
     * @param array $rowData 行数据
     * @return array 处理后的数据
     */
    private function processImageUrls(string $table, array $rowData): array
    {
        // 检查该表是否有需要处理的图片字段
        if (!isset($this->imageFields[$table])) {
            return $rowData;
        }

        $fields = $this->imageFields[$table];

        foreach ($fields as $field) {
            if (!isset($rowData[$field]) || !is_string($rowData[$field])) {
                continue;
            }

            $value = $rowData[$field];

            // 处理 description 字段（HTML内容）
            if ($field === 'description') {
                $rowData[$field] = $this->convertImageUrlsInHtml($value);
            } else {
                // 处理普通图片路径字段
                $rowData[$field] = $this->convertImageUrl($value);
            }
        }

        return $rowData;
    }

    /**
     * 转换单个图片URL
     *
     * @param string $imagePath 图片路径
     * @return string 转换后的URL
     */
    private function convertImageUrl(string $imagePath): string
    {
        // 空字符串或空白字符串直接返回，不添加CDN域名
        if (empty($imagePath) || trim($imagePath) === '') {
            return $imagePath;
        }

        // 如果已经是完整的URL，提取路径部分
        if (preg_match('#https?://[^/]+/image/(.*)#i', $imagePath, $matches)) {
            // 提取 catalog/ 或 data/ 后面的路径
            $path = $matches[1];
            return $this->imageCdnUrl . '/' . $path;
        }

        // 如果是相对路径（以 catalog/ 或 data/ 开头）
        if (strpos($imagePath, 'catalog/') === 0 || strpos($imagePath, 'data/') === 0) {
            return $this->imageCdnUrl . '/' . $imagePath;
        }

        // 其他情况保持原样（包括空字符串）
        return $imagePath;
    }

    /**
     * 转换HTML内容中的图片URL
     *
     * @param string $html HTML内容
     * @return string 转换后的HTML
     */
    private function convertImageUrlsInHtml(string $html): string
    {
        if (empty($html)) {
            return $html;
        }

        // 匹配所有 img 标签的 src 属性
        $pattern = '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i';

        $html = preg_replace_callback($pattern, function ($matches) {
            $fullMatch = $matches[0];
            $imageUrl = $matches[1];

            // 转换URL
            $newUrl = $this->convertImageUrl($imageUrl);

            // 替换原URL
            return str_replace($imageUrl, $newUrl, $fullMatch);
        }, $html);

        return $html;
    }

    /**
     * 记录同步操作日志
     */
    private function logSyncOperation(
        string $dbName,
        string $table,
        int $sourceId,
        string $action,
        string $message = '',
        int $duration = 0
    ): void {
        if (!$this->useDatabaseTables) {
            return;
        }

        try {
            DB::table('oc_sync_log')->insert([
                'source_site' => $dbName,
                'source_table' => $table,
                'source_id' => $sourceId,
                'action' => $action,
                'message' => $message,
                'duration' => $duration,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->log('error', "记录同步日志失败: " . $e->getMessage());
        }
    }

    /**
     * 处理同步错误
     */
    private function handleSyncError(
        string $dbName,
        string $table,
        int $remoteId,
        string $errorMessage
    ): void {
        if (!$this->useDatabaseTables) {
            return;
        }

        try {
            $record = DB::table('oc_sync_record')
                ->where('source_site', $dbName)
                ->where('source_table', $table)
                ->where('source_id', $remoteId)
                ->first();

            if ($record) {
                $retryCount = $record->retry_count + 1;

                DB::table('oc_sync_record')
                    ->where('sync_id', $record->sync_id)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage,
                        'retry_count' => $retryCount,
                        'updated_at' => now(),
                    ]);

                // 超过最大重试次数，移入死信队列
                if ($retryCount >= $this->maxRetryCount) {
                    DB::table('oc_sync_dead')->insert([
                        'sync_id' => $record->sync_id,
                        'source_site' => $dbName,
                        'source_table' => $table,
                        'source_id' => $remoteId,
                        'error_message' => $errorMessage,
                        'retry_count' => $retryCount,
                        'last_attempt_at' => now(),
                        'created_at' => now(),
                    ]);

                    $this->log('warning', "记录已移入死信队列: {$dbName}/{$table}/{$remoteId}");
                }
            } else {
                // 创建新的失败记录
                DB::table('oc_sync_record')->insert([
                    'source_site' => $dbName,
                    'source_table' => $table,
                    'source_id' => $remoteId,
                    'sync_type' => 'insert',
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'retry_count' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $this->log('error', "处理同步错误失败: " . $e->getMessage());
        }
    }

    /**
     * 检测数据冲突
     */
    private function detectConflict(
        string $dbName,
        string $table,
        int $remoteId,
        array $localData,
        array $remoteData
    ): bool {
        if (!$this->useDatabaseTables) {
            return false;
        }

        // 定义冲突检测字段
        $conflictFields = [
            'oc_product' => ['price', 'quantity', 'status'],
            'oc_category' => ['status', 'sort_order'],
        ];

        if (!isset($conflictFields[$table])) {
            return false;
        }

        $hasConflict = false;
        $fields = $conflictFields[$table];

        foreach ($fields as $field) {
            if (isset($localData[$field]) && isset($remoteData[$field])) {
                if ($localData[$field] != $remoteData[$field]) {
                    $hasConflict = true;

                    try {
                        // 记录冲突
                        DB::table('oc_sync_conflict')->insert([
                            'source_site' => $dbName,
                            'source_table' => $table,
                            'conflict_type' => "{$table}_conflict",
                            'local_data' => json_encode($localData),
                            'remote_data' => json_encode($remoteData),
                            'created_at' => now(),
                        ]);

                        $this->log('warning', "检测到数据冲突: {$dbName}/{$table}/{$remoteId} 字段: {$field}");
                    } catch (\Exception $e) {
                        $this->log('error', "记录冲突失败: " . $e->getMessage());
                    }

                    break;
                }
            }
        }

        return $hasConflict;
    }

    /**
     * 判断表是否为子表
     *
     * @param string $table 表名
     * @return bool 是否为子表
     */
    private function isChildTable(string $table): bool
    {
        // 定义主表列表
        $masterTables = [
            'oc_product',
            'oc_category',
            'oc_option',
            'oc_option_value',
        ];

        // 如果表本身是主表,返回false
        if (in_array($table, $masterTables)) {
            return false;
        }

        // 检查是否有外键指向主表
        if (isset($this->foreignKeys[$table])) {
            foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
                if (in_array($referencedTable, $masterTables)) {
                    return true;
                }
            }
        }

        // 检查复合主键表(复合主键表通常也是子表)
        $primaryKey = $this->getPrimaryKey($table);
        if (is_array($primaryKey)) {
            // 检查复合主键中是否包含主表的ID字段
            $masterIdFields = ['product_id', 'category_id', 'option_id', 'option_value_id'];
            foreach ($primaryKey as $keyField) {
                if (in_array($keyField, $masterIdFields)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取子表的主表
     *
     * @param string $table 子表名
     * @return string|null 主表名
     */
    private function getParentTable(string $table): ?string
    {
        // 定义主表优先级(优先返回product相关的主表)
        $masterTablePriority = [
            'oc_product',
            'oc_category',
            'oc_option',
            'oc_option_value',
        ];

        if (isset($this->foreignKeys[$table])) {
            foreach ($masterTablePriority as $masterTable) {
                foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
                    if ($referencedTable === $masterTable) {
                        return $masterTable;
                    }
                }
            }
        }

        return null;
    }

    /**
     * 获取子表指向主表的外键字段
     *
     * @param string $table 子表名
     * @param string $parentTable 主表名
     * @return string|null 外键字段名
     */
    private function getParentForeignKey(string $table, string $parentTable): ?string
    {
        if (isset($this->foreignKeys[$table])) {
            foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
                if ($referencedTable === $parentTable) {
                    return $foreignKey;
                }
            }
        }

        return null;
    }

    /**
     * 同步子表数据(基于主表变更)
     *
     * @param string $remoteConnection 远程连接
     * @param string $dbName 数据库名
     * @param string $table 子表名
     * @param string $parentTable 主表名
     * @param string $parentForeignKey 外键字段
     * @param array $changedParentIds 变更的主表ID映射 [远程ID => 本地ID]
     * @return array 同步结果
     */
    private function syncChildTable(
        string $remoteConnection,
        string $dbName,
        string $table,
        string $parentTable,
        string $parentForeignKey,
        array $changedParentIds
    ): array {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0, 'new_mappings' => 0, 'duplicates' => 0, 'updated_duplicates' => 0, 'changed_ids' => []];
        $startTime = time();

        $primaryKey = $this->getPrimaryKey($table);
        $isCompositeKey = is_array($primaryKey);

        // ========== 优先处理新增ID的子表数据 ==========
        $newParentIds = [];
        if (isset($this->masterTableNewIds[$dbName][$parentTable]) && !empty($this->masterTableNewIds[$dbName][$parentTable])) {
            $newParentIds = $this->masterTableNewIds[$dbName][$parentTable];
            $newParentIdCount = count($newParentIds);

            $this->log('info', "[{$dbName}][{$table}] 从主表 {$parentTable} 获取新增ID: {$newParentIdCount} 个");
            //$this->line('info', "[{$dbName}][{$table}] 从主表 {$parentTable} 获取新增ID: {$newParentIdCount} 个". json_encode($newParentIds));

            if ($newParentIdCount > 0) {
                $this->line("     🚀 优先处理新增ID的子表数据: {$newParentIdCount} 个");
                
                
                // ========== 数据完整性检查和自动修复（在新增ID处理后立即执行） ==========
                $this->checkAndFixDataIntegrity($dbName, $table, $parentTable, $parentForeignKey, $result);

                // 提取远程ID用于查询远程数据
                $remoteNewParentIds = array_keys($newParentIds);

                // 分批处理
                $batchSize = 1000;
                $batches = array_chunk($remoteNewParentIds, $batchSize);

                foreach ($batches as $batchIndex => $batchRemoteIds) {
                    // 查询远程子表数据(使用远程ID)
                    $remoteRows = DB::connection($remoteConnection)
                        ->table($table)
                        ->whereIn($parentForeignKey, $batchRemoteIds)
                        ->get();

                    if ($remoteRows->isEmpty()) {
                        continue;
                    }

                    $this->line("     📦 新增ID批次 " . ($batchIndex + 1) . "/" . count($batches) . ": {$remoteRows->count()} 条记录");
                    $this->log('info',"     📦 新增ID批次明细 ::" . ($batchIndex + 1) . "/" . count($batches) . ": {$remoteRows->count()} 条记录   ::  ".  json_encode(["parentForeignKey"=>$parentForeignKey, "batchRemoteIds"=>$batchRemoteIds,"remoteRows->count()"=>$remoteRows->count() ]));

                    // 处理每条记录
                    foreach ($remoteRows as $row) {
                        try {
                            $this->syncChildRecord($remoteConnection, $dbName, $table, $parentTable, $parentForeignKey, $row, $result, $newParentIds);
                        } catch (\Exception $e) {
                            $result['errors']++;
                            $this->log('error', "[{$dbName}][{$table}] 子表同步失败(新增ID): " . $e->getMessage());
                        }
                    }
                }

                $this->line("     ✅ 新增ID子表数据处理完成: 插入 {$result['inserted']} 条");
            }
        }


        // ========== 处理修改ID的子表数据 ==========
        $modifiedParentIds = array_diff_key($changedParentIds, $newParentIds);
        $modifiedParentIdCount = count($modifiedParentIds);

        if ($modifiedParentIdCount > 0) {
            $this->line("     🔄 处理修改ID的子表数据: {$modifiedParentIdCount} 个");

            // 提取远程ID用于查询远程数据
            $remoteModifiedParentIds = array_keys($modifiedParentIds);

            // 分批处理
            $batchSize = 1000;
            $batches = array_chunk($remoteModifiedParentIds, $batchSize);

            foreach ($batches as $batchIndex => $batchRemoteIds) {
                // 查询远程子表数据(使用远程ID)
                $remoteRows = DB::connection($remoteConnection)
                    ->table($table)
                    ->whereIn($parentForeignKey, $batchRemoteIds)
                    ->get();

                if ($remoteRows->isEmpty()) {
                    continue;
                }

                $this->line("     📦 修改ID批次 " . ($batchIndex + 1) . "/" . count($batches) . ": {$remoteRows->count()} 条记录");

                // 处理每条记录
                foreach ($remoteRows as $row) {
                    try {
                        $this->syncChildRecord($remoteConnection, $dbName, $table, $parentTable, $parentForeignKey, $row, $result, $modifiedParentIds);
                    } catch (\Exception $e) {
                        $result['errors']++;
                        $this->log('error', "[{$dbName}][{$table}] 子表同步失败(修改ID): " . $e->getMessage());
                    }
                }
            }
        }

        // ========== 只对比修改ID的子表数据（新增ID不需要对比） ==========
        if ($modifiedParentIdCount > 0) {
            $this->line("     🔍 开始对比修改ID的子表数据...");
            $this->compareChildTableData($remoteConnection, $dbName, $table, $parentTable, $parentForeignKey, $modifiedParentIds, $result);
        }

        return $result;
    }

    /**
     * 同步子表单条记录
     *
     * @param string $remoteConnection 远程连接
     * @param string $dbName 数据库名
     * @param string $table 子表名
     * @param string $parentTable 主表名
     * @param string $parentForeignKey 外键字段
     * @param object $row 行数据
     * @param array $result 结果统计
     * @param array $changedParentIds 主表ID映射 [远程ID => 本地ID]
     */
    private function syncChildRecord(
        string $remoteConnection,
        string $dbName,
        string $table,
        string $parentTable,
        string $parentForeignKey,
        object $row,
        array &$result,
        array $changedParentIds
    ): void {
        $primaryKey = $this->getPrimaryKey($table);
        $isCompositeKey = is_array($primaryKey);

        $rowData = (array) $row;

        // ========== 关键修复: 确保主表外键使用本地映射ID ==========
        // 获取远程主表ID
        $remoteParentId = $rowData[$parentForeignKey] ?? null;

        if ($remoteParentId !== null && isset($changedParentIds[$remoteParentId])) {
            // 使用本地映射后的ID
            $localParentId = $changedParentIds[$remoteParentId];
            $rowData[$parentForeignKey] = $localParentId;

            $this->log('debug', "[{$dbName}][{$table}] 映射主表ID: {$parentForeignKey} {$remoteParentId} → {$localParentId}");
        } else {
            // 如果没有映射关系,记录警告
            $this->log('warning', "[{$dbName}][{$table}] 未找到主表ID映射: {$parentForeignKey}={$remoteParentId}");
        }

        // ========== 处理其他外键映射 ==========
        $rowData = $this->processForeignKeys($dbName, $table, $rowData, $remoteConnection);

        // 检查是否需要跳过
        if (isset($rowData['__skip_record__']) && $rowData['__skip_record__'] === true) {
            $result['skipped']++;
            return;
        }

        // 处理图片URL
        $rowData = $this->processImageUrls($table, $rowData);

        // 修复日期
        $rowData = $this->fixInvalidDatetimeValues($rowData);

        // 检查重复
        $duplicateCheck = $this->checkDuplicateRecord($table, $rowData);

        if ($duplicateCheck['is_duplicate']) {
            // 如果有差异,更新数据
            if ($duplicateCheck['image_diff_only'] && !empty($duplicateCheck['existing_record'])) {
                if (!$this->isDryRun) {
                    $this->updateImageFields($table, $duplicateCheck['existing_record'], $rowData);
                    $this->stats['total_updated_duplicates']++;
                    $result['updated_duplicates']++;
                    $result['duplicates']++;
                }
            } else {
                $result['duplicates']++;
            }
            return;
        }

        if ($this->isDryRun) {
            $result['inserted']++;
            return;
        }

        // 插入新记录
        try {
            $primaryKey = $this->getPrimaryKey($table);
            $isCompositeKey = is_array($primaryKey);
            
            if ($isCompositeKey) {
                // 复合主键表,直接插入(所有ID字段都已经是本地映射后的ID)
                DB::table($table)->insert($rowData);
            } else {
                // 单主键表,删除自增主键后插入
                if (!in_array($table, $this->nonAutoIncrementTables)) {
                    unset($rowData[$primaryKey]);
                }
                DB::table($table)->insert($rowData);
            }

            $result['inserted']++;
        } catch (QueryException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $result['skipped']++;
            } else {
                throw $e;
            }
        }
    }

    /**
     * 对比子表数据（只对比修改的ID，新增ID不需要对比）
     *
     * @param string $remoteConnection 远程连接
     * @param string $dbName 数据库名
     * @param string $table 子表名
     * @param string $parentTable 主表名
     * @param string $parentForeignKey 外键字段
     * @param array $changedParentIds 变更的主表ID映射 [远程ID => 本地ID]（只包含修改的ID）
     * @param array $result 结果统计
     */
    private function compareChildTableData(
        string $remoteConnection,
        string $dbName,
        string $table,
        string $parentTable,
        string $parentForeignKey,
        array $changedParentIds,
        array &$result
    ): void {
        if (empty($changedParentIds)) {
            $this->line("     ℹ️ 无修改ID，跳过数据对比");
            return;
        }

        // 提取本地ID用于查询本地数据
        $localParentIds = array_values($changedParentIds);
        // 提取远程ID用于查询远程数据
        $remoteParentIds = array_keys($changedParentIds);

        // 分批处理
        $batchSize = 1000;
        $localBatches = array_chunk($localParentIds, $batchSize);
        $remoteBatches = array_chunk($remoteParentIds, $batchSize);

        foreach ($localBatches as $index => $batchLocalIds) {
            $batchRemoteIds = $remoteBatches[$index] ?? [];

            // 查询本地子表数据
            $localCount = DB::table($table)
                ->whereIn($parentForeignKey, $batchLocalIds)
                ->count();

            // 查询远程子表数据（使用远程ID）
            $remoteCount = DB::connection($remoteConnection)
                ->table($table)
                ->whereIn($parentForeignKey, $batchRemoteIds)
                ->count();

            $this->log('info', "[{$dbName}][{$table}] 数据对比 - 远程: {$remoteCount} 条, 本地: {$localCount} 条");

            // 如果数量不一致，记录警告
            if ($remoteCount != $localCount) {
                $this->warn("     ⚠️ 数据不一致: 远程 {$remoteCount} 条, 本地 {$localCount} 条");
                $this->log('warning', "[{$dbName}][{$table}] 数据不一致 - 远程: {$remoteCount} 条, 本地: {$localCount} 条");

                // 获取详细信息用于调试
                $localDetails = DB::table($table)
                    ->select($parentForeignKey, DB::raw('COUNT(*) as cnt'))
                    ->whereIn($parentForeignKey, $batchLocalIds)
                    ->groupBy($parentForeignKey)
                    ->get()
                    ->keyBy($parentForeignKey);

                $remoteDetails = DB::connection($remoteConnection)
                    ->table($table)
                    ->select($parentForeignKey, DB::raw('COUNT(*) as cnt'))
                    ->whereIn($parentForeignKey, $batchRemoteIds)
                    ->groupBy($parentForeignKey)
                    ->get()
                    ->keyBy($parentForeignKey);

                // 找出差异的ID
                foreach ($batchRemoteIds as $i => $remoteId) {
                    $localId = $batchLocalIds[$i] ?? null;
                    if (!$localId) continue;

                    $remoteCnt = $remoteDetails[$remoteId]->cnt ?? 0;
                    $localCnt = $localDetails[$localId]->cnt ?? 0;

                    if ($remoteCnt != $localCnt) {
                        $this->log('warning', "[{$dbName}][{$table}] ID {$remoteId}→{$localId}: 远程 {$remoteCnt} 条, 本地 {$localCnt} 条");
                    }
                }
            }
        }

        $this->line("     ✅ 子表数据对比完成");
    }

    /**
     * 检查并修复数据完整性
     *
     * @param string $dbName 数据库名
     * @param string $table 子表名
     * @param string $parentTable 主表名
     * @param string $parentForeignKey 外键字段
     * @param array $result 结果统计
     */
    private function checkAndFixDataIntegrity(
        string $dbName,
        string $table,
        string $parentTable,
        string $parentForeignKey,
        array &$result
    ): void {
        // 检查是否启用自动修复
        $dataIntegrityConfig = $this->config['data_integrity'] ?? [];
        $autoFix = $dataIntegrityConfig['auto_fix_missing_child_data'] ?? false;

        if (!$autoFix) {
            return;
        }

        // 检查是否是需要检查完整性的子表
        $childTablesToCheck = $dataIntegrityConfig['child_tables_to_check'] ?? [];
        if (!isset($childTablesToCheck[$parentTable][$table])) {
            return;
        }

        $tableConfig = $childTablesToCheck[$parentTable][$table];
        $defaultValues = $tableConfig['default_values'] ?? [];

        if (empty($defaultValues)) {
            return;
        }

        // 查找缺失子表数据的主表记录
        // 对于复合主键表，需要特殊处理
        $primaryKey = $this->getPrimaryKey($table);
        $isCompositeKey = is_array($primaryKey);

        if ($isCompositeKey) {
            // 复合主键表：检查是否存在任何子表记录
            $missingRecords = DB::table("{$parentTable} as p")
                ->leftJoin("{$table} as c", "p.{$parentForeignKey}", '=', "c.{$parentForeignKey}")
                ->whereNull("c.{$parentForeignKey}")
                ->select("p.*")
                ->get();
        } else {
            // 单主键表：直接检查外键
            $missingRecords = DB::table("{$parentTable} as p")
                ->leftJoin("{$table} as c", "p.{$parentForeignKey}", '=', "c.{$parentForeignKey}")
                ->whereNull("c.{$parentForeignKey}")
                ->select("p.*")
                ->get();
        }

        if ($missingRecords->isEmpty()) {
            return;
        }

        $missingCount = $missingRecords->count();
        $this->line("     ⚠️  发现 {$missingCount} 个主表记录缺少子表数据");
        $this->log('warning', "[{$dbName}][{$table}] 发现 {$missingCount} 个主表记录缺少子表数据");

        if ($this->isDryRun) {
            $this->line("     ℹ️  模拟运行模式，跳过自动修复");
            return;
        }

        // 自动修复缺失的子表数据
        $this->line("     🔧 开始自动修复缺失的子表数据...");
        $fixed = 0;
        $batchSize = 100;
        $batch = [];

        foreach ($missingRecords as $parentRecord) {
            $childData = [];

            // 构建子表数据
            foreach ($defaultValues as $field => $value) {
                // 替换占位符
                if (is_string($value) && preg_match('/\{(\w+)\}/', $value, $matches)) {
                    $fieldName = $matches[1];
                    $childData[$field] = $parentRecord->$fieldName ?? $value;
                } else {
                    $childData[$field] = $value;
                }
            }

            // 设置外键
            $childData[$parentForeignKey] = $parentRecord->$parentForeignKey;

            $batch[] = $childData;

            if (count($batch) >= $batchSize) {
                try {
                    DB::table($table)->insert($batch);
                    $fixed += count($batch);
                    $this->line("     ✅ 已修复 {$fixed}/{$missingCount} 条记录");
                    $batch = [];
                } catch (\Exception $e) {
                    $this->log('error', "[{$dbName}][{$table}] 批量插入失败: " . $e->getMessage());
                    $result['errors'] += count($batch);
                    $batch = [];
                }
            }
        }

        // 插入剩余数据
        if (!empty($batch)) {
            try {
                DB::table($table)->insert($batch);
                $fixed += count($batch);
            } catch (\Exception $e) {
                $this->log('error', "[{$dbName}][{$table}] 批量插入失败: " . $e->getMessage());
                $result['errors'] += count($batch);
            }
        }

        if ($fixed > 0) {
            $this->line("     ✅ 成功修复 {$fixed} 条缺失的子表数据");
            $this->log('info', "[{$dbName}][{$table}] 成功修复 {$fixed} 条缺失的子表数据");
            $result['inserted'] += $fixed;
        }
    }

    /**
     * 删除本地多余的记录（反向同步）
     * 
     * 当远程数据库删除了某些记录时，本地数据库可能还有这些记录，
     * 需要删除本地多余的记录以保持数据一致性。
     * 
     * 重要：只在 id_mappings.json 文件存在时执行，确保只处理历史同步过的数据。
     *
     * @param string $remoteConnection 远程连接
     * @param string $dbName 数据库名
     * @param string $table 表名
     * @param array $result 结果统计
     */
    private function deleteOrphanedRecords(
        string $remoteConnection,
        string $dbName,
        string $table,
        array &$result
    ): void {
        // 检查是否启用删除功能
        $dataIntegrityConfig = $this->config['data_integrity'] ?? [];
        $deleteOrphaned = $dataIntegrityConfig['delete_local_orphaned_records'] ?? false;

        if (!$deleteOrphaned) {
            return;
        }

        // 检查映射文件是否存在（关键安全检查）
        $mappingFile = $this->config['mappings']['json_file'] ?? '';
        if (empty($mappingFile) || !file_exists($mappingFile)) {
            $this->log('info', "[{$dbName}][{$table}] 映射文件不存在，跳过反向同步");
            return;
        }

        // 检查映射文件是否为空
        $mappingContent = json_decode(file_get_contents($mappingFile), true);
        if (empty($mappingContent) || !isset($mappingContent[$dbName][$table])) {
            $this->log('info', "[{$dbName}][{$table}] 映射文件中无历史数据，跳过反向同步");
            return;
        }

        $primaryKey = $this->getPrimaryKey($table);
        if (is_array($primaryKey)) {
            // 复合主键表暂不处理
            return;
        }

        $this->line("     🔍 开始反向同步检查（基于历史映射数据）...");

        // 获取远程所有ID
        $remoteIds = DB::connection($remoteConnection)
            ->table($table)
            ->pluck($primaryKey)
            ->toArray();

        // 获取本地所有ID（只检查有映射关系的记录）
        $localIdsWithMapping = [];
        if (isset($this->idMappings[$dbName][$table])) {
            $localIdsWithMapping = array_values($this->idMappings[$dbName][$table]);
        }

        if (empty($localIdsWithMapping)) {
            $this->line("     ℹ️  无历史映射数据，跳过反向同步");
            return;
        }

        // 找出本地有但远程没有的记录（孤立记录）
        $orphanedIds = array_diff($localIdsWithMapping, $remoteIds);

        if (empty($orphanedIds)) {
            return;
        }

        $orphanedCount = count($orphanedIds);
        $this->line("     ⚠️  发现 {$orphanedCount} 个孤立记录（远程已删除）");
        $this->log('warning', "[{$dbName}][{$table}] 发现 {$orphanedCount} 个孤立记录需要删除");

        if ($this->isDryRun) {
            $this->line("     ℹ️  模拟运行模式，跳过删除");
            return;
        }

        // 删除孤立记录
        $this->line("     🗑️  开始删除孤立记录...");
        $deleted = 0;
        $batchSize = 100;
        $batches = array_chunk($orphanedIds, $batchSize);

        foreach ($batches as $batch) {
            try {
                // 删除主表记录
                DB::table($table)->whereIn($primaryKey, $batch)->delete();
                $deleted += count($batch);

                // 删除关联的子表记录
                $this->deleteChildRecords($dbName, $table, $batch);

                $this->line("     ✅ 已删除 {$deleted}/{$orphanedCount} 条记录");
            } catch (\Exception $e) {
                $this->log('error', "[{$dbName}][{$table}] 删除孤立记录失败: " . $e->getMessage());
                $result['errors'] += count($batch);
            }
        }

        if ($deleted > 0) {
            $this->line("     ✅ 成功删除 {$deleted} 条孤立记录");
            $this->log('info', "[{$dbName}][{$table}] 成功删除 {$deleted} 条孤立记录");

            // 从映射中移除已删除的记录
            foreach ($orphanedIds as $localId) {
                $remoteId = array_search($localId, $this->idMappings[$dbName][$table] ?? []);
                if ($remoteId !== false) {
                    unset($this->idMappings[$dbName][$table][$remoteId]);
                    unset($this->tempMappings[$dbName][$table][$remoteId]);
                }
            }

            $result['deleted'] = ($result['deleted'] ?? 0) + $deleted;
        }
    }

    /**
     * 删除子表关联记录
     *
     * @param string $dbName 数据库名
     * @param string $parentTable 主表名
     * @param array $parentIds 主表ID数组
     */
    private function deleteChildRecords(string $dbName, string $parentTable, array $parentIds): void
    {
        // 定义主表和子表的关联关系
        $childTableRelations = [
            'oc_product' => [
                'oc_product_description' => 'product_id',
                'oc_product_to_store' => 'product_id',
                'oc_product_to_layout' => 'product_id',
                'oc_product_to_category' => 'product_id',
                'oc_product_image' => 'product_id',
                'oc_product_option' => 'product_id',
                'oc_product_option_value' => 'product_id',
                'oc_product_attribute' => 'product_id',
                'oc_product_discount' => 'product_id',
                'oc_product_filter' => 'product_id',
                'oc_product_recurring' => 'product_id',
                'oc_product_related' => 'product_id',
                'oc_product_reward' => 'product_id',
                'oc_product_special' => 'product_id',
                'oc_product_to_download' => 'product_id',
            ],
            'oc_category' => [
                'oc_category_description' => 'category_id',
                'oc_category_to_store' => 'category_id',
                'oc_category_to_layout' => 'category_id',
                'oc_category_path' => 'category_id',
                'oc_category_filter' => 'category_id',
            ],
            'oc_option' => [
                'oc_option_description' => 'option_id',
            ],
            'oc_option_value' => [
                'oc_option_value_description' => 'option_value_id',
            ],
        ];

        if (!isset($childTableRelations[$parentTable])) {
            return;
        }

        // 删除所有子表关联记录
        foreach ($childTableRelations[$parentTable] as $childTable => $foreignKey) {
            try {
                $deletedCount = DB::table($childTable)
                    ->whereIn($foreignKey, $parentIds)
                    ->delete();

                if ($deletedCount > 0) {
                    $this->log('info', "[{$dbName}][{$childTable}] 删除 {$deletedCount} 条关联记录");
                }
            } catch (\Exception $e) {
                $this->log('error', "[{$dbName}][{$childTable}] 删除关联记录失败: " . $e->getMessage());
            }
        }
    }
}
