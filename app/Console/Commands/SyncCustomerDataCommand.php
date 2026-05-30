<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

/**
 * 从多个远程库同步客户数据到本地库
 * 
 * 功能说明：
 * 1. 从配置的多个远程数据库读取客户相关表数据
 * 2. 使用 Excel 文件记录每个远程库每个表的最大 ID
 * 3. 只同步 ID 大于 Excel 中记录的最大 ID 的新数据
 * 4. 同步完成后更新 Excel 中的最大 ID
 * 5. 本地表不存在时自动创建（仅创建表结构）
 * 6. 数据库连接信息写死在配置文件中
 * 7. 支持 IP 替换重试：先用 db_host，失败后用 server_inner_ip 替换
 * 8. 设置每个数据库单次最大超时时间
 * 
 * 使用方法：
 * php artisan app:sync-customer-data
 * php artisan app:sync-customer-data --dry-run     # 模拟运行，不写入数据
 * php artisan app:sync-customer-data --db=saveb_us  # 只同步指定数据库
 * php artisan app:sync-customer-data --table=oc_customer  # 只同步指定表
 * php artisan app:sync-customer-data --init-excel   # 初始化 Excel 文件（读取当前各库最大ID）
 * 
 * php artisan app:sync-customer-data --init-excel    #初始化 Excel 文件（读取各库当前最大 ID）
 * php artisan app:sync-customer-data                       # 同步所有数据库的所有表
 * php artisan app:sync-customer-data --db=saveb_uk         # 只同步指定数据库
 * php artisan app:sync-customer-data --table=oc_customer   # 只同步指定表
 * php artisan app:sync-customer-data --dry-run             # 模拟运行（不写入数据）
 * php artisan app:sync-customer-data --timeout=600         # 设置超时时间（秒）
 * php artisan app:sync-customer-data --batch-size=1000     # 设置超时时间（秒）


 */
#[Signature('app:sync-customer-data 
    {--dry-run : 仅模拟运行，不实际写入数据库}
    {--db= : 只同步指定数据库（按name字段匹配）}
    {--table= : 只同步指定表}
    {--init-excel : 初始化Excel文件，读取各远程库当前最大ID}
    {--batch-size=500 : 每批处理的记录数}
    {--timeout=300 : 每个数据库单次最大超时时间（秒）}')]
#[Description('从多个远程库同步客户数据到本地库，使用Excel记录最大ID')]
class SyncCustomerDataCommand extends Command
{
    /**
     * 日志文件路径
     */
    private string $logFile;

    /**
     * 错误日志文件路径
     */
    private string $errorLogFile;

    /**
     * 是否为模拟运行模式
     */
    private bool $isDryRun = false;

    /**
     * 每批处理的记录数
     */
    private int $batchSize = 500;

    /**
     * 远程数据库配置
     */
    private array $remoteDatabases = [];

    /**
     * 要同步的表
     */
    private array $tablesToSync = [];

    /**
     * 每个表的主键字段
     */
    private array $primaryKeys = [];

    /**
     * Excel 文件路径
     */
    private string $excelFile;

    /**
     * Excel 备份目录
     */
    private string $excelBackupDir;

    /**
     * 数据库连接超时时间（秒）
     */
    private int $connectionTimeout = 5;

    /**
     * 数据库查询超时时间（秒）
     */
    private int $queryTimeout = 300;

    /**
     * ID 映射表：[table_name => [remote_id => local_id]]
     */
    private array $idMappings = [];

    /**
     * 外键关联配置：[table_name => [foreign_key_column => referenced_table]]
     */
    private array $foreignKeys = [
        'oc_address' => ['customer_id' => 'oc_customer'],
        'oc_customer_activity' => ['customer_id' => 'oc_customer'],
        'oc_customer_affiliate' => ['customer_id' => 'oc_customer'],
        'oc_customer_approval' => ['customer_id' => 'oc_customer'],
        'oc_customer_history' => ['customer_id' => 'oc_customer'],
        'oc_customer_ip' => ['customer_id' => 'oc_customer'],
        'oc_customer_login' => ['email' => 'oc_customer'], // 特殊：通过 email 关联
        'oc_customer_online' => ['customer_id' => 'oc_customer'],
        'oc_customer_reward' => ['customer_id' => 'oc_customer'],
        'oc_customer_transaction' => ['customer_id' => 'oc_customer'],
        'oc_customer_wishlist' => ['customer_id' => 'oc_customer'],
    ];

    /**
     * 表同步顺序（确保被引用的表先同步）
     * 注意：oc_customer 放在最后，因为它会触发关联表的同步
     */
    private array $syncOrder = [
        'oc_customer_group',          // 客户组（基础数据）
        'oc_customer_group_description', // 客户组描述
        'oc_address',                 // 地址（依赖 customer）
        'oc_customer_activity',       // 客户活动
        'oc_customer_affiliate',      // 客户联盟
        'oc_customer_approval',       // 客户审批
        'oc_customer_history',        // 客户历史
        'oc_customer_ip',             // 客户IP
        'oc_customer_login',          // 客户登录
        'oc_customer_online',         // 客户在线
        'oc_customer_reward',         // 客户奖励
        'oc_customer_search',         // 客户搜索
        'oc_customer_transaction',    // 客户交易
        'oc_customer_wishlist',       // 客户愿望清单
        'oc_customer',                // 客户（主表，放在最后触发关联表同步）
    ];

    /**
     * 命令执行入口
     */
    public function handle(): int
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);

        $this->isDryRun = (bool) $this->option('dry-run');
        $this->batchSize = (int) $this->option('batch-size');

        // 加载配置
        $this->loadConfig();

        // 命令行参数覆盖超时配置
        if ($this->option('timeout')) {
            $this->queryTimeout = (int) $this->option('timeout');
        }

        // 初始化日志
        $this->initLogFiles();

        $this->info('开始同步客户数据...');
        $this->log('info', '开始同步客户数据...');
        $this->log('info', "连接超时: {$this->connectionTimeout}秒, 查询超时: {$this->queryTimeout}秒");

        if ($this->isDryRun) {
            $this->warn('【模拟运行模式】不会实际写入数据库');
        }

        // 初始化 Excel 模式
        if ($this->option('init-excel')) {
            return $this->initExcel();
        }

        // 加载或创建 Excel 文件
        $maxIds = $this->loadExcel();

        // 加载现有的 ID 映射关系
        $this->loadIdMappings();

        // 统计
        $totalInserted = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        // 遍历每个远程数据库
        foreach ($this->remoteDatabases as $remoteDb) {
            $dbFilter = $this->option('db');
            if ($dbFilter && $remoteDb['name'] !== $dbFilter) {
                continue;
            }

            $this->newLine(2);
            $this->info("=== 处理远程库: {$remoteDb['name']} ({$remoteDb['website']}) ===");

            try {
                $remoteConnection = $this->createRemoteConnection($remoteDb);
            } catch (\Exception $e) {
                $this->error("无法连接远程库 {$remoteDb['name']}: " . $e->getMessage());
                $this->log('error', "无法连接远程库 {$remoteDb['name']}: " . $e->getMessage());
                $totalErrors++;
                continue;
            }

            // 按照指定顺序同步表
            foreach ($this->syncOrder as $table) {
                // 检查是否在要同步的表列表中
                if (!in_array($table, $this->tablesToSync)) {
                    continue;
                }

                $tableFilter = $this->option('table');
                if ($tableFilter && $table !== $tableFilter) {
                    continue;
                }

                // 跳过关联表，它们会在 syncCustomerRelatedTables 中处理
                $relatedTables = [
                    'oc_address',
                    'oc_customer_activity',
                    'oc_customer_affiliate',
                    'oc_customer_approval',
                    'oc_customer_history',
                    'oc_customer_ip',
                    'oc_customer_login',
                    'oc_customer_online',
                    'oc_customer_reward',
                    'oc_customer_search',
                    'oc_customer_transaction',
                    'oc_customer_wishlist',
                ];
                if (in_array($table, $relatedTables)) {
                    continue;
                }

                $this->newLine();
                $this->info("  处理表: {$table}");

                try {
                    $result = $this->syncTable($remoteConnection, $remoteDb['name'], $table, $maxIds);
                    $totalInserted += $result['inserted'];
                    $totalSkipped += $result['skipped'];
                    $totalErrors += $result['errors'];
                } catch (\Exception $e) {
                    $this->error("  同步表 {$table} 失败: " . $e->getMessage());
                    $this->log('error', "同步表 {$table} 失败 (库: {$remoteDb['name']}): " . $e->getMessage());
                    $totalErrors++;
                }
            }

            // 每个数据库执行完后更新 Excel 和 ID 映射
            if (!$this->isDryRun) {
                $this->saveExcel($maxIds);
                $this->saveIdMappings();
                $this->line("  已更新 Excel 和 ID 映射文件");
            }

            // 断开远程连接
            $this->disconnectRemote($remoteDb['name']);
        }

        $this->newLine(2);
        $this->info("同步完成！插入: {$totalInserted}, 跳过: {$totalSkipped}, 错误: {$totalErrors}");
        $this->info("日志文件: {$this->logFile}");
        $this->info("Excel文件: {$this->excelFile}");
        $this->info("ID映射文件: " . storage_path('app/id_mappings.json'));

        $this->log('info', "同步完成！插入: {$totalInserted}, 跳过: {$totalSkipped}, 错误: {$totalErrors}");

        return 0;
    }

    /**
     * 加载配置
     */
    private function loadConfig(): void
    {
        $config = config('remote_databases');
        $this->remoteDatabases = $config['remote_databases'] ?? [];
        $this->tablesToSync = $config['tables_to_sync'] ?? [];
        $this->primaryKeys = $config['primary_keys'] ?? [];
        $this->excelFile = $config['excel']['max_id_file'] ?? storage_path('app/max_ids.xlsx');
        $this->excelBackupDir = $config['excel']['backup_dir'] ?? storage_path('app/excel_backups');
        $this->connectionTimeout = $config['connection_timeout'] ?? 30;
        $this->queryTimeout = $config['query_timeout'] ?? 300;
    }

    /**
     * 初始化日志文件
     */
    private function initLogFiles(): void
    {
        $timestamp = date('Ymd_His');
        $logDir = storage_path('logs/customer_sync');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $this->logFile = $logDir . "/sync_{$timestamp}.log";
        $this->errorLogFile = $logDir . "/sync_error_{$timestamp}.log";
    }

    /**
     * 创建远程数据库连接（并发测试所有IP，使用最快响应的）
     * 3秒超时，并发测试所有IP，哪个先成功就用哪个
     */
    private function createRemoteConnection(array $remoteDb): string
    {
        $connectionName = 'remote_' . $remoteDb['name'];
        $hosts = [
            $remoteDb['db_host'] ?? $remoteDb['host'],
            $remoteDb['server_ip'] ?? null,
            $remoteDb['server_inner_ip'] ?? null,
        ];

        $hosts = array_filter($hosts, function($host) {
            return $host !== null;
        });

        $hosts = array_values($hosts); // 重新索引数组

        $this->line("  并发测试 " . count($hosts) . " 个IP...");

        // 最多重试 3 次
        $maxRetries = 3;
        for ($retry = 1; $retry <= $maxRetries; $retry++) {
            // 并发测试所有IP
            $result = $this->connectWithParallelHosts($connectionName, $hosts, $remoteDb, 3);

            if ($result['success']) {
                DB::connection($connectionName)->statement("SET SESSION wait_timeout = {$this->queryTimeout}");
                DB::connection($connectionName)->statement("SET SESSION interactive_timeout = {$this->queryTimeout}");
                return $connectionName;
            }

            if ($retry < $maxRetries) {
                $this->warn("  第 {$retry} 次连接失败，重试中...");
            }
        }

        throw new \Exception("所有 IP 连接均失败（已重试 {$maxRetries} 次）: " . ($result['error'] ?? 'Unknown error'));
    }

    /**
     * 并发测试多个主机，使用最快响应的
     */
    private function connectWithParallelHosts(string $connectionName, array $hosts, array $remoteDb, int $timeoutSeconds): array
    {
        $results = [];
        $successHost = null;
        $successTime = PHP_FLOAT_MAX;

        // 为每个主机创建独立的连接配置
        foreach ($hosts as $index => $host) {
            $connKey = $connectionName . '_temp_' . $index;
            
            config(["database.connections.{$connKey}" => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => $remoteDb['db_port'] ?? $remoteDb['port'] ?? '3306',
                'database' => $remoteDb['db_name'] ?? $remoteDb['database'],
                'username' => $remoteDb['db_username'] ?? $remoteDb['username'],
                'password' => $remoteDb['db_pwd'] ?? $remoteDb['password'],
                'charset' => 'utf8',
                'collation' => 'utf8_general_ci',
                'prefix' => $remoteDb['db_prefix'] ?? $remoteDb['prefix'] ?? '',
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

        // 并发测试所有连接
        $startTime = microtime(true);
        $completed = 0;
        $maxWaitTime = $timeoutSeconds;

        while ($completed < count($hosts) && (microtime(true) - $startTime) < $maxWaitTime) {
            foreach ($results as $index => &$result) {
                if ($result['success'] || $result['error']) {
                    continue;
                }

                try {
                    $pdo = DB::connection($result['connKey'])->getPdo();
                    $elapsed = microtime(true) - $startTime;
                    
                    $result['success'] = true;
                    $result['time'] = $elapsed;
                    $result['pdo'] = $pdo;

                    $this->line("  ✓ {$result['host']} 响应成功 (耗时: " . round($elapsed * 1000, 2) . "ms)");

                    // 记录最快响应的
                    if ($elapsed < $successTime) {
                        $successTime = $elapsed;
                        $successHost = $index;
                    }

                    $completed++;

                } catch (\Exception $e) {
                    $elapsed = microtime(true) - $startTime;
                    $errorMsg = $e->getMessage();

                    // 检查是否是超时错误
                    $isTimeout = strpos($errorMsg, '2002') !== false || 
                                 strpos($errorMsg, 'timeout') !== false ||
                                 strpos($errorMsg, '连接方在一段时间后没有正确答复') !== false;

                    if ($isTimeout || $elapsed >= $timeoutSeconds) {
                        $result['error'] = $errorMsg;
                        $this->warn("  ✗ {$result['host']} 超时/失败");
                        $completed++;
                        DB::purge($result['connKey']);
                    }
                    // 否则继续等待
                }
            }

            // 短暂休眠，避免CPU占用过高
            if ($completed < count($hosts)) {
                usleep(10000); // 10ms
            }
        }

        // 清理所有临时连接
        foreach ($results as $result) {
            if ($result['connKey'] !== $connectionName . '_temp_' . $successHost) {
                DB::purge($result['connKey']);
            }
        }

        // 如果有成功的连接，将最快的连接配置到正式的连接名
        if ($successHost !== null) {
            $bestResult = $results[$successHost];
            
            // 将成功的连接配置复制到正式连接名
            $tempConfig = config("database.connections.{$bestResult['connKey']}");
            config(["database.connections.{$connectionName}" => $tempConfig]);

            $this->line("  使用最快响应: {$bestResult['host']}");

            return [
                'success' => true,
                'host' => $bestResult['host'],
                'time' => $bestResult['time'],
            ];
        }

        // 所有连接都失败
        $errors = [];
        foreach ($results as $result) {
            if ($result['error']) {
                $errors[] = "{$result['host']}: " . substr($result['error'], 0, 100);
            }
        }

        return [
            'success' => false,
            'error' => implode('; ', $errors),
        ];
    }

    /**
     * 断开远程连接
     */
    private function disconnectRemote(string $dbName): void
    {
        $connectionName = 'remote_' . $dbName;
        $connection = DB::connection($connectionName);
        $pdo = $connection->getPdo();
        unset($pdo);
        DB::purge($connectionName);
        $this->line("  已断开连接: {$dbName}");
    }

    /**
     * 同步单个表的数据（使用新的 ID）
     */
    private function syncTable(string $remoteConnection, string $dbName, string $table, array &$maxIds): array
    {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
        $startTime = time();

        $primaryKey = $this->getPrimaryKey($table);
        $isCompositeKey = is_array($primaryKey);

        // 对于复合主键的表，使用不同的同步策略
        if ($isCompositeKey) {
            return $this->syncCompositeKeyTable($remoteConnection, $dbName, $table, $maxIds, $primaryKey);
        }

        // 获取 Excel 中记录的最大 ID
        $lastMaxId = $maxIds[$dbName][$table] ?? 0;

        // 获取远程表中大于 lastMaxId 的记录数
        $newCount = DB::connection($remoteConnection)
            ->table($table)
            ->where($primaryKey, '>', $lastMaxId)
            ->count();

        if ($newCount === 0) {
            $this->line("    无新数据 (当前最大ID: {$lastMaxId})");
            return $result;
        }

        $this->line("    发现 {$newCount} 条新数据 (从ID > {$lastMaxId} 开始)");

        // 确保本地表存在
        $this->ensureLocalTableExists($remoteConnection, $table);

        // 分批读取并插入
        $offset = 0;
        $currentMaxId = $lastMaxId;

        while (true) {
            // 检查超时
            if (time() - $startTime > $this->queryTimeout) {
                $this->warn("    查询超时，已处理 {$offset}/{$newCount} 条记录");
                $this->log('warning', "表 {$table} 查询超时");
                break;
            }

            $rows = DB::connection($remoteConnection)
                ->table($table)
                ->where($primaryKey, '>', $lastMaxId)
                ->orderBy($primaryKey, 'asc')
                ->skip($offset)
                ->take($this->batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $remoteId = $row->$primaryKey;

                // 检查是否已经映射过（已同步过）
                if (isset($this->idMappings[$table][$remoteId])) {
                    $result['skipped']++;
                    continue;
                }

                if ($this->isDryRun) {
                    $result['inserted']++;
                } else {
                    try {
                        // 处理外键关联（将远程ID转换为本地ID）
                        $rowData = $this->processForeignKeys($table, (array) $row, $dbName);

                        // 移除主键，让数据库自动生成新的本地ID
                        unset($rowData[$primaryKey]);

                        // 插入数据并获取新生成的本地ID
                        $newId = DB::table($table)->insertGetId($rowData);

                        // 记录 ID 映射关系：远程ID -> 本地新ID
                        $this->idMappings[$table][$remoteId] = $newId;

                        $this->line("    映射: 远程ID {$remoteId} -> 本地ID {$newId}");

                        // 如果是 oc_customer 表，立即同步该客户的所有关联表数据
                        if ($table === 'oc_customer') {
                            $this->syncCustomerRelatedTables($remoteConnection, $dbName, $remoteId, $newId);
                        }

                        $result['inserted']++;
                    } catch (QueryException $e) {
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $result['skipped']++;
                        } else {
                            $result['errors']++;
                            $this->log('error', "插入失败 [{$dbName}][{$table}] 远程ID={$remoteId}: " . $e->getMessage());
                        }
                    }
                }

                // 更新当前最大 ID（记录远程库的最大ID，用于下次增量同步）
                if ($remoteId > $currentMaxId) {
                    $currentMaxId = $remoteId;
                }
            }

            $offset += $this->batchSize;
            $this->line("    已处理: " . min($offset, $newCount) . "/{$newCount}");
        }

        // 更新 maxIds
        if ($currentMaxId > $lastMaxId) {
            $maxIds[$dbName][$table] = $currentMaxId;
            $this->line("    更新最大ID: {$lastMaxId} -> {$currentMaxId}");
        }

        return $result;
    }

    /**
     * 同步客户的所有关联表数据
     */
    private function syncCustomerRelatedTables(string $remoteConnection, string $dbName, int $remoteCustomerId, int $localCustomerId): void
    {
        $relatedTables = [
            'oc_address',
            'oc_customer_activity',
            'oc_customer_affiliate',
            'oc_customer_approval',
            'oc_customer_history',
            'oc_customer_ip',
            'oc_customer_login',
            'oc_customer_online',
            'oc_customer_reward',
            'oc_customer_search',
            'oc_customer_transaction',
            'oc_customer_wishlist',
        ];

        foreach ($relatedTables as $relatedTable) {
            $this->syncCustomerRelatedTable($remoteConnection, $dbName, $relatedTable, $remoteCustomerId, $localCustomerId);
        }
    }

    /**
     * 同步客户的单个关联表数据
     */
    private function syncCustomerRelatedTable(string $remoteConnection, string $dbName, string $table, int $remoteCustomerId, int $localCustomerId): void
    {
        $primaryKey = $this->getPrimaryKey($table);
        $isCompositeKey = is_array($primaryKey);

        // 复合主键表使用全量对比策略
        if ($isCompositeKey) {
            return; // 复合主键表在主流程中处理
        }

        // 特殊处理：oc_customer_login 通过 email 关联
        if ($table === 'oc_customer_login') {
            // 先获取该客户的 email
            $customerEmail = DB::connection($remoteConnection)
                ->table('oc_customer')
                ->where('customer_id', $remoteCustomerId)
                ->value('email');

            if (!$customerEmail) {
                return;
            }

            // 查询远程表中该 email 的登录记录
            $remoteRecords = DB::connection($remoteConnection)
                ->table($table)
                ->where('email', $customerEmail)
                ->get();
        } else {
            // 查询远程表中该客户的所有记录
            $remoteRecords = DB::connection($remoteConnection)
                ->table($table)
                ->where('customer_id', $remoteCustomerId)
                ->get();
        }

        if ($remoteRecords->isEmpty()) {
            return;
        }

        $this->line("      同步 {$table}: " . $remoteRecords->count() . " 条记录");

        // 确保本地表存在
        $this->ensureLocalTableExists($remoteConnection, $table);

        foreach ($remoteRecords as $record) {
            $recordData = (array) $record;

            // 将远程 customer_id 替换为本地 customer_id
            if (isset($recordData['customer_id'])) {
                $recordData['customer_id'] = $localCustomerId;
            }

            try {
                // 移除主键，让数据库自动生成新的本地ID
                unset($recordData[$primaryKey]);

                // 插入数据
                DB::table($table)->insert($recordData);

                $this->line("        已插入: {$table} 记录");
            } catch (QueryException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    // 重复记录，跳过
                } else {
                    $this->log('error', "插入关联表失败 [{$dbName}][{$table}] customer_id={$remoteCustomerId}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 处理外键关联，将远程 ID 转换为本地 ID
     */
    private function processForeignKeys(string $table, array $rowData, string $dbName): array
    {
        if (!isset($this->foreignKeys[$table])) {
            return $rowData;
        }

        foreach ($this->foreignKeys[$table] as $foreignKey => $referencedTable) {
            if (!isset($rowData[$foreignKey])) {
                continue;
            }

            $remoteRefId = $rowData[$foreignKey];

            // 特殊处理：oc_customer_login 通过 email 关联
            if ($table === 'oc_customer_login' && $foreignKey === 'email') {
                // 通过 email 查找本地 customer_id
                $localCustomerId = DB::table('oc_customer')
                    ->where('email', $remoteRefId)
                    ->value('customer_id');

                if ($localCustomerId) {
                    $rowData[$foreignKey] = $remoteRefId; // 保持 email 不变
                    $this->log('info', "  通过 email {$remoteRefId} 找到本地 customer_id: {$localCustomerId}");
                } else {
                    $this->log('warning', "  未找到 email {$remoteRefId} 对应的本地 customer");
                }
                continue;
            }

            // 常规外键处理：查找 ID 映射
            if (isset($this->idMappings[$referencedTable][$remoteRefId])) {
                $localRefId = $this->idMappings[$referencedTable][$remoteRefId];
                $rowData[$foreignKey] = $localRefId;
            } else {
                // 如果没有映射，尝试从数据库查找
                $localRefId = DB::table($referencedTable)
                    ->where($this->getPrimaryKey($referencedTable), $remoteRefId)
                    ->value($this->getPrimaryKey($referencedTable));

                if ($localRefId) {
                    $rowData[$foreignKey] = $localRefId;
                    $this->idMappings[$referencedTable][$remoteRefId] = $localRefId;
                } else {
                    $this->log('warning', "  未找到 {$referencedTable} ID {$remoteRefId} 的本地映射");
                }
            }
        }

        return $rowData;
    }

    /**
     * 同步复合主键表的数据
     */
    private function syncCompositeKeyTable(string $remoteConnection, string $dbName, string $table, array &$maxIds, array $compositeKey): array
    {
        $result = ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
        $startTime = time();

        // 对于复合主键表，使用全量对比策略
        // 获取远程表所有记录
        $remoteRows = DB::connection($remoteConnection)->table($table)->get();

        if ($remoteRows->isEmpty()) {
            $this->line("    远程表无数据");
            return $result;
        }

        $this->line("    远程表有 {$remoteRows->count()} 条记录，进行全量对比");

        // 确保本地表存在
        $this->ensureLocalTableExists($remoteConnection, $table);

        // 获取本地所有记录的复合键
        $localKeys = [];
        $localRows = DB::table($table)->get();
        foreach ($localRows as $localRow) {
            $keyValues = [];
            foreach ($compositeKey as $key) {
                $keyValues[] = $localRow->$key;
            }
            $localKeys[implode('_', $keyValues)] = true;
        }

        // 对比并插入
        $inserted = 0;
        foreach ($remoteRows as $row) {
            // 检查超时
            if (time() - $startTime > $this->queryTimeout) {
                $this->warn("    查询超时");
                $this->log('warning', "表 {$table} 查询超时");
                break;
            }

            $rowData = (array) $row;

            // 处理外键关联
            $rowData = $this->processForeignKeys($table, $rowData, $dbName);

            $keyValues = [];
            foreach ($compositeKey as $key) {
                $keyValues[] = $rowData[$key] ?? $row->$key;
            }
            $keyStr = implode('_', $keyValues);

            if (isset($localKeys[$keyStr])) {
                $result['skipped']++;
                continue;
            }

            if ($this->isDryRun) {
                $result['inserted']++;
            } else {
                try {
                    DB::table($table)->insert($rowData);
                    $result['inserted']++;
                    $inserted++;
                } catch (QueryException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $result['skipped']++;
                    } else {
                        $result['errors']++;
                        $this->log('error', "插入失败 [{$dbName}][{$table}] key={$keyStr}: " . $e->getMessage());
                    }
                }
            }
        }

        // 对于复合主键表，记录同步时间作为标记
        $maxIds[$dbName][$table] = time();

        if ($inserted > 0 || $this->isDryRun) {
            $this->line("    插入: {$result['inserted']}, 跳过: {$result['skipped']}");
        }

        return $result;
    }

    /**
     * 获取表的主键
     */
    private function getPrimaryKey(string $table): string|array
    {
        return $this->primaryKeys[$table] ?? 'id';
    }

    /**
     * 检查本地记录是否存在
     */
    private function recordExistsLocal(string $table, string $primaryKey, $id): bool
    {
        return DB::table($table)->where($primaryKey, $id)->exists();
    }

    /**
     * 确保本地表存在（如果不存在则从远程复制结构）
     */
    private function ensureLocalTableExists(string $remoteConnection, string $table): void
    {
        $localDbName = DB::getDatabaseName();

        // 检查本地表是否存在
        $tableExists = DB::selectOne(
            "SELECT COUNT(*) as count FROM information_schema.tables 
             WHERE table_schema = ? AND table_name = ?",
            [$localDbName, $table]
        );

        if ($tableExists->count > 0) {
            return;
        }

        $this->warn("    本地表 {$table} 不存在，从远程库创建...");

        if ($this->isDryRun) {
            $this->line("    [模拟] 将创建表 {$table}");
            return;
        }

        try {
            // 从远程获取建表语句
            $createTableResult = DB::connection($remoteConnection)
                ->selectOne("SHOW CREATE TABLE `{$table}`");

            $createTableSql = $createTableResult->{'Create Table'} ?? null;

            if ($createTableSql) {
                DB::statement($createTableSql);
                $this->line("    表 {$table} 创建成功");
                $this->log('info', "从远程库创建本地表 {$table} 成功");
            }
        } catch (\Exception $e) {
            $this->error("    创建表 {$table} 失败: " . $e->getMessage());
            $this->log('error', "创建表 {$table} 失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 初始化 Excel 文件
     * 读取各远程库当前最大ID并写入Excel
     */
    private function initExcel(): int
    {
        $this->info('初始化 Excel 文件，读取各远程库当前最大ID...');

        $maxIds = [];

        foreach ($this->remoteDatabases as $remoteDb) {
            $dbFilter = $this->option('db');
            if ($dbFilter && $remoteDb['name'] !== $dbFilter) {
                continue;
            }

            $this->info("连接远程库: {$remoteDb['name']}");

            try {
                $remoteConnection = $this->createRemoteConnection($remoteDb);
            } catch (\Exception $e) {
                $this->error("无法连接: " . $e->getMessage());
                continue;
            }

            $maxIds[$remoteDb['name']] = [];

            foreach ($this->tablesToSync as $table) {
                $tableFilter = $this->option('table');
                if ($tableFilter && $table !== $tableFilter) {
                    continue;
                }

                $primaryKey = $this->getPrimaryKey($table);

                // 复合主键表跳过
                if (is_array($primaryKey)) {
                    $maxIds[$remoteDb['name']][$table] = 0;
                    $this->line("  {$table}: 复合主键，标记为0");
                    continue;
                }

                try {
                    $maxId = DB::connection($remoteConnection)
                        ->table($table)
                        ->max($primaryKey);

                    $maxIds[$remoteDb['name']][$table] = $maxId ?? 0;
                    $this->line("  {$table}: 最大ID = " . ($maxId ?? 0));
                } catch (\Exception $e) {
                    $maxIds[$remoteDb['name']][$table] = 0;
                    $this->warn("  {$table}: 读取失败 - " . $e->getMessage());
                }
            }

            $this->disconnectRemote($remoteDb['name']);
        }

        $this->saveExcel($maxIds);
        $this->info("Excel 文件已保存到: {$this->excelFile}");

        return 0;
    }

    /**
     * 加载 Excel 文件中的最大 ID
     * 返回格式: [db_name => [table_name => max_id]]
     */
    private function loadExcel(): array
    {
        if (!file_exists($this->excelFile)) {
            $this->warn("Excel 文件不存在，将创建新文件");
            $this->info("提示: 可以使用 --init-excel 参数初始化Excel文件");
            return [];
        }

        try {
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($this->excelFile);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            $maxIds = [];

            // 第一行是表头: [A => '数据库', B => '表名', C => '最大ID', D => '更新时间']
            for ($row = 2; $row <= count($data); $row++) {
                $dbName = $data[$row]['A'] ?? null;
                $tableName = $data[$row]['B'] ?? null;
                $maxId = $data[$row]['C'] ?? 0;

                if ($dbName && $tableName) {
                    $maxIds[$dbName][$tableName] = (int) $maxId;
                }
            }

            $this->line("已加载 Excel 文件，共 " . count($data) - 1 . " 条记录");

            // 显式关闭 Excel 对象，释放文件句柄
            $spreadsheet->disconnectWorksheets();
            $spreadsheet->garbageCollect();
            unset($spreadsheet);
            $reader = null;
            
            // 强制垃圾回收
            gc_collect_cycles();

            return $maxIds;

        } catch (\Exception $e) {
            $this->error("加载 Excel 文件失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 保存最大 ID 到 Excel 文件
     */
    private function saveExcel(array $maxIds): void
    {
        // 备份旧 Excel 文件到备份目录（不在后续参与使用）
        if (file_exists($this->excelFile)) {
            if (!is_dir($this->excelBackupDir)) {
                mkdir($this->excelBackupDir, 0755, true);
            }
            $backupFile = $this->excelBackupDir . '/max_ids_' . date('Ymd_His') . '.xlsx';
            if (copy($this->excelFile, $backupFile)) {
                $this->log('info', "Excel 文件已备份到: {$backupFile}");
            } else {
                $this->log('warning', "Excel 文件备份失败");
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('最大ID记录');

        // 表头
        $sheet->setCellValue('A1', '数据库');
        $sheet->setCellValue('B1', '表名');
        $sheet->setCellValue('C1', '最大ID');
        $sheet->setCellValue('D1', '更新时间');

        // 设置表头样式
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(25);

        $row = 2;
        $now = date('Y-m-d H:i:s');

        foreach ($maxIds as $dbName => $tables) {
            foreach ($tables as $tableName => $maxId) {
                $sheet->setCellValue("A{$row}", $dbName);
                $sheet->setCellValue("B{$row}", $tableName);
                $sheet->setCellValue("C{$row}", $maxId);
                $sheet->setCellValue("D{$row}", $now);
                $row++;
            }
        }

        // 确保目录存在
        $dir = dirname($this->excelFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($this->excelFile);

        // 清理资源
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        $writer = null;

        $this->log('info', "Excel 文件已保存: {$this->excelFile}");
    }

    /**
     * 加载 ID 映射关系
     */
    private function loadIdMappings(): void
    {
        // 每次执行清空映射，只保留当前次的数据，历史不保留
        $this->idMappings = [];
        $this->line("ID 映射已清空，仅保留本次同步数据");
    }

    /**
     * 保存 ID 映射关系
     */
    private function saveIdMappings(): void
    {
        $mappingFile = storage_path('app/id_mappings.json');
        $backupFile = storage_path('app/id_mappings_backup_' . date('Ymd_His') . '.json');

        // 备份旧文件
        if (file_exists($mappingFile)) {
            copy($mappingFile, $backupFile);
        }

        try {
            $content = json_encode($this->idMappings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($mappingFile, $content);
            $this->log('info', "ID 映射已保存: {$mappingFile}");
        } catch (\Exception $e) {
            $this->error("保存 ID 映射失败: " . $e->getMessage());
        }
    }

    /**
     * 记录日志
     */
    private function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND);

        if ($level === 'error') {
            file_put_contents($this->errorLogFile, $logLine, FILE_APPEND);
        }
    }
}