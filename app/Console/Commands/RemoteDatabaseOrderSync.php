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
 * 远程数据库订单增量同步命令类 
 *
 * 核心功能：
 * 1. 并发同步多个远程数据库的订单数据
 * 2. 高可用IP连接：并发测试多个IP，自动选择最快响应
 * 3. ID映射机制：JSON运行时映射 + CSV历史备份
 * 4. 增量同步：CSV进度记录，支持断点续传
 * 5. 外键关联：自动转换子表外键为本地ID
 * 6. 自动建表：从远程库复制表结构
 * 7. 数据去重：检测远程表数据与本地表数据是否相同
 * 8. 异常处理：完善的容错机制
 *
 * 同步的订单相关表：
 * - oc_order (主订单表)
 * - oc_order_history (订单历史)
 * - oc_order_option (订单选项)
 * - oc_order_product (订单产品)
 * - oc_order_recurring (订单周期)
 * - oc_order_recurring_transaction (订单周期交易)
 * - oc_order_shipment (订单发货)
 * - oc_order_status (订单状态)
 * - oc_order_total (订单总计)
 * - oc_order_voucher (订单代金券)
 *
 * @author CodeArts Agent
 * @version 1.0
 * @date 2026-04-14
 */
#[Signature('remote-database:order-sync
    {--dry-run : 模拟运行，不实际写入数据}
    {--db= : 只同步指定数据库（按name字段）}
    {--table= : 只同步指定表}
    {--init : 初始化同步进度，读取各远程库当前最大ID}
    {--timeout=300 : 每个数据库查询超时时间（秒）}
    {--connection-timeout=3 : 数据库连接超时时间（秒）}
    {--force : 强制全量同步，忽略CSV中的最大ID记录}
    {--concurrency=13 : 并发数据库数量（默认13）}')]
#[Description('远程数据库订单增量同步工具 (基于JSON+CSV+数据去重)  弃用')]
class RemoteDatabaseOrderSync extends Command
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

    /** @var array 需要同步的订单表列表 */
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
        $this->info('║          远程数据库订单增量同步工具 (JSON+CSV优化版)        ║');
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

        // 加载CSV进度文件
        $this->loadProgress();

        // 加载JSON映射文件
        $this->loadIdMappings();

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
        $this->config = config('remote_databases_orders');
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
        $logDir = $this->config['logging']['log_dir'] ?? storage_path('logs/remote_order_sync');

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

        foreach ($this->syncOrder as $table) {
            if (!in_array($table, $this->tablesToSync)) continue;
            if ($this->option('table') && $table !== $this->option('table')) continue;

            $this->newLine();
            $this->info("  📋 同步表: {$table}");

            try {
                $result = $this->syncTable($remoteConnection, $dbName, $table);

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

                $duplicates = $result['duplicates'] ?? 0;
                $this->line("     ✓ 结果: +{$result['inserted']} | 跳过: {$result['skipped']} | 错误: {$result['errors']} | 新映射: {$result['new_mappings']} | 重复: {$duplicates}");

            } catch (\Exception $e) {
                $this->error("  ❌ 表 {$table} 同步失败: " . $e->getMessage());
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
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0, 'new_mappings' => 0, 'duplicates' => 0];
        $startTime = time();

        $primaryKey = $this->getPrimaryKey($table);

        if (is_array($primaryKey)) {
            return $this->syncCompositeKeyTable($remoteConnection, $dbName, $table, $primaryKey);
        }

        $lastMaxId = $this->option('force') ? 0 : ($this->progressData[$dbName][$table]['max_id'] ?? 0);

        $newCount = DB::connection($remoteConnection)
            ->table($table)
            ->where($primaryKey, '>', $lastMaxId)
            ->count();

        if ($newCount === 0) {
            $this->line("     ℹ️ 无新数据 (当前最大ID: {$lastMaxId})");
            return $result;
        }

        $this->line("     📊 发现 {$newCount} 条新记录 (ID > {$lastMaxId})");

        $offset = 0;
        $currentMaxId = $lastMaxId;
        $batchSize = 100;

        while (true) {
            if (time() - $startTime > $this->queryTimeout) {
                $this->warn("     ⚠️ 查询超时，已处理 {$offset}/{$newCount}");
                break;
            }

            $rows = DB::connection($remoteConnection)
                ->table($table)
                ->where($primaryKey, '>', $lastMaxId)
                ->orderBy($primaryKey, 'asc')
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($rows->isEmpty()) break;

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

            $offset += $batchSize;
            $progress = min($offset, $newCount);
            $percent = round(($progress / $newCount) * 100, 1);
            $this->line("     ⏳ 进度: {$progress}/{$newCount} ({$percent}%)");
        }

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

        if (!in_array($table, $this->nonAutoIncrementTables)) {
            unset($rowData[$primaryKey]);
        }

        $rowData = $this->fixInvalidDatetimeValues($rowData);

        if ($this->isDuplicateRecord($table, $rowData)) {
            $result['duplicates']++;
            $this->logDuplicateRecord($dbName, $table, $remoteId, $rowData);
            return;
        }

        if ($this->isDryRun) {
            $result['inserted']++;
            $result['new_mappings']++;
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
     * 同步复合主键表
     */
    private function syncCompositeKeyTable(
        string $remoteConnection,
        string $dbName,
        string $table,
        array $compositeKey
    ): array {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0, 'new_mappings' => 0, 'duplicates' => 0];
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

        foreach ($remoteRows as $row) {
            if (time() - $startTime > $this->queryTimeout) {
                $this->warn("     ⚠️ 查询超时");
                break;
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

            $keyValues = array_map(fn($key) => $rowData[$key] ?? $row->$key ?? '', $compositeKey);
            $keyStr = implode('_', $keyValues);

            if (isset($localKeys[$keyStr])) {
                $result['skipped']++;
                continue;
            }

            if ($this->isDryRun) {
                $result['inserted']++;
                continue;
            }

            try {
                DB::table($table)->insert($rowData);
                $result['inserted']++;
            } catch (QueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $result['skipped']++;
                } else {
                    $result['errors']++;
                    $this->log('error', "[{$dbName}][{$table}] key={$keyStr}: " . $e->getMessage());
                }
            }
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
     * 处理外键关联
     *
     * 特殊处理：
     * - oc_order.customer_id: 通过订单的 email 字段查找本地 oc_customer 表
     */
    private function processForeignKeys(string $dbName, string $table, array $rowData, ?string $remoteConnection = null): array
    {
        if (!isset($this->foreignKeys[$table])) {
            return $rowData;
        }

        foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
            if (!isset($rowData[$foreignKey])) {
                continue;
            }

            $remoteRefId = $rowData[$foreignKey];

            // 特殊处理：oc_order.customer_id 通过 email 查找本地客户
            if ($table === 'oc_order' && $foreignKey === 'customer_id' && $referencedTable === 'oc_customer') {
                // customer_id 为 0 表示游客订单，不需要映射（不匹配email）
                if ($remoteRefId == 0) {
                    continue;
                }

                // 优先从映射中获取
                if (isset($this->idMappings[$dbName][$referencedTable][$remoteRefId])) {
                    $rowData[$foreignKey] = $this->idMappings[$dbName][$referencedTable][$remoteRefId];
                    continue;
                }

                // 优先：通过远程 oc_customer.customer_id 查询 email（只读操作）
                $localCustomerId = null;
                if ($remoteConnection) {
                    try {
                        $remoteEmail = DB::connection($remoteConnection)
                            ->table('oc_customer')
                            ->where('customer_id', $remoteRefId)
                            ->value('email');

                        if ($remoteEmail) {
                            $localCustomerId = DB::table('oc_customer')
                                ->where('email', $remoteEmail)
                                ->value('customer_id');

                            if ($localCustomerId) {
                                $rowData[$foreignKey] = $localCustomerId;
                                $this->idMappings[$dbName][$referencedTable][$remoteRefId] = $localCustomerId;
                                $this->tempMappings[$dbName][$referencedTable][$remoteRefId] = $localCustomerId;
                                $this->log('info', "  通过远程oc_customer查询email '{$remoteEmail}' 找到本地客户ID: {$localCustomerId} (远程客户ID: {$remoteRefId})");
                                continue;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->log('warning', "  查询远程oc_customer失败: " . $e->getMessage());
                    }
                }

                // 其次：使用订单自带的 email 字段查找本地客户
                if (isset($rowData['email']) && !empty($rowData['email'])) {
                    $localCustomerId = DB::table('oc_customer')
                        ->where('email', $rowData['email'])
                        ->value('customer_id');

                    if ($localCustomerId) {
                        $rowData[$foreignKey] = $localCustomerId;
                        $this->idMappings[$dbName][$referencedTable][$remoteRefId] = $localCustomerId;
                        $this->tempMappings[$dbName][$referencedTable][$remoteRefId] = $localCustomerId;
                        $this->log('info', "  通过订单email '{$rowData['email']}' 找到本地客户ID: {$localCustomerId} (远程客户ID: {$remoteRefId})");
                        continue;
                    }
                }

                // 都没找到，customer_id 设为 0
                $this->log('warning', "  未找到远程客户ID {$remoteRefId} 对应的本地客户，customer_id 设为 0");
                $rowData[$foreignKey] = 0;
                continue;
            }

            // 常规外键处理
            $refPk = $this->getPrimaryKey($referencedTable);
            
            // 如果引用表是复合主键表，跳过外键映射（复合主键表已全量同步）
            if (is_array($refPk)) {
                $this->log('info', "  引用表 {$referencedTable} 是复合主键，跳过外键 {$foreignKey} 的映射处理");
                continue;
            }
            
            if (isset($this->idMappings[$dbName][$referencedTable][$remoteRefId])) {
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
        $connectionName = 'remote_order_' . $remoteDb['name'];
        $dbName = $remoteDb['name'];

        // 检查IP缓存
        $cacheFile = $this->config['mappings']['ip_cache_file'] ?? storage_path('app/remote_order_sync/ip_cache.json');
        $cacheTTL = $this->config['ip_cache_ttl'] ?? 3600;

        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true) ?? [];
            if (isset($cacheData[$dbName])) {
                $cached = $cacheData[$dbName];
                $cacheAge = time() - ($cached['cached_at'] ?? 0);

                if ($cacheAge < $cacheTTL) {
                    $this->line("  ✅ 使用缓存IP: {$cached['host']} (缓存时间: " . round($cacheAge / 60, 1) . "分钟前)");

                    if ($this->pingHost($cached['host'])) {
                        $this->ipCache[$dbName] = $cached;

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

        $pingResults = $this->pingTestHosts($hosts);

        if (empty($pingResults)) {
            throw new \Exception("所有IP地址Ping测试失败");
        }

        $fastestIP = $pingResults[0]['host'];
        $fastestTime = $pingResults[0]['time'];

        $this->line("  🎯 Ping最快: {$fastestIP} (" . round($fastestTime * 1000, 2) . "ms)");
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
     * Ping测试多个主机
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

        usort($results, fn($a, $b) => $a['time'] <=> $b['time']);

        return $results;
    }

    /**
     * Ping测试单个主机
     */
    private function pingHost(string $host, int $timeout = 3): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "ping -n 1 -w " . ($timeout * 1000) . " " . escapeshellarg($host);
            $result = shell_exec($cmd);
            return strpos($result, 'TTL=') !== false || strpos($result, '时间=') !== false;
        }

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
        $connectionName = 'remote_order_' . $dbName;
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
        $this->info("║ 执行耗时: {$duration} 秒                                                ║");
        $this->info('╚══════════════════════════════════════════════════════════════╝');

        $this->newLine();
        $this->info('📊 各数据库同步详情:');
        foreach ($this->stats['db_stats'] as $dbName => $dbStat) {
            $dbDuration = round(microtime(true) - $dbStat['start_time'], 2);
            $ipInfo = $dbStat['ip_used'] ? " (IP: {$dbStat['ip_used']}, " . round($dbStat['ip_response_time'] * 1000, 2) . "ms)" : '';
            $dupInfo = isset($dbStat['duplicates']) ? " | 重复: {$dbStat['duplicates']}" : '';
            $this->line("  {$dbName}: +{$dbStat['inserted']} | 跳过: {$dbStat['skipped']} | 错误: {$dbStat['errors']} | 表: {$dbStat['tables']}{$dupInfo} | 耗时: {$dbDuration}s{$ipInfo}");
        }

        $this->newLine();
        $this->info("📁 日志文件: {$this->logFile}");
        $this->info("📊 进度文件: {$this->config['mappings']['progress_file']}");
        $this->info("🔗 映射文件: {$this->config['mappings']['json_file']}");
        $this->info("📜 历史备份: {$this->config['mappings']['history_dir']}");

        $this->log('info', "执行完成 - 插入: {$this->stats['total_inserted']}, 跳过: {$this->stats['total_skipped']}, 错误: {$this->stats['total_errors']}, 新映射: {$this->stats['new_mappings']}, 重复: {$this->stats['total_duplicates']}, 耗时: {$duration}秒");
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
    private function isDuplicateRecord(string $table, array $rowData): bool
    {
        try {
            $query = DB::table($table);
            foreach ($rowData as $field => $value) {
                $query->where($field, $value);
            }

            return $query->exists();
        } catch (\Exception $e) {
            $this->log('warning', "去重检测失败 [{$table}]: " . $e->getMessage());
            return false;
        }
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
            'oc_order' => ['order_status_id', 'total'],
            'oc_customer' => ['email', 'telephone'],
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
}
