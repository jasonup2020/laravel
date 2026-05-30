# 同步系统数据库表融合方案

## 概述

将现有的CSV进度文件和JSON映射文件与新的数据库表结构融合，实现更强大的同步管理能力。

## 现有机制分析

### 当前实现方式

1. **CSV进度文件** (`progress.csv`)
   - 记录每个表的同步进度（最大ID）
   - 用于增量同步和断点续传
   - 格式：`db_name, table_name, max_id, sync_time`

2. **JSON映射文件** (`id_mappings.json`)
   - 存储远程ID与本地ID的映射关系
   - 三层嵌套结构：`{库名 -> 表名 -> 远程ID -> 本地ID}`
   - 用于外键关联转换

3. **CSV历史备份** (`history_*.csv`)
   - 记录每次同步新增的映射关系
   - 用于数据恢复和审计

## 新数据库表结构

### 核心表

1. **oc_sync_record** - 同步记录映射表
   - 替代JSON映射文件
   - 提供更强大的查询和管理能力
   - 支持状态跟踪和重试机制

2. **oc_sync_log** - 同步日志表
   - 记录详细的同步操作日志
   - 用于问题排查和审计

3. **oc_sync_dead** - 死信队列表
   - 存储同步失败的数据
   - 等待人工处理

4. **oc_sync_conflict** - 冲突日志表
   - 记录数据冲突
   - 支持人工介入处理

5. **oc_sync_rate_limit** - 速率限制表
   - API请求频率控制
   - 防止滥用

6. **oc_store_extend** - 站点扩展信息表
   - 替代配置文件中的远程数据库配置
   - 支持动态管理

## 融合方案

### 方案一：双轨制（推荐）

**保留现有文件机制，同时写入数据库表**

#### 优势
- 向后兼容，不影响现有功能
- 数据库表提供额外的管理和查询能力
- 文件作为备份，数据库作为主记录

#### 实现步骤

1. **同步记录创建**
   ```php
   // 原有：保存到JSON
   $this->idMappings[$dbName][$table][$remoteId] = $localId;
   
   // 新增：同时写入数据库
   DB::table('oc_sync_record')->insert([
       'source_site' => $dbName,
       'source_table' => $table,
       'source_id' => $remoteId,
       'target_id' => $localId,
       'sync_type' => 'insert',
       'status' => 'success',
   ]);
   ```

2. **同步进度记录**
   ```php
   // 原有：保存到CSV
   $this->progressData[$dbName][$table] = ['max_id' => $maxId, 'sync_time' => $time];
   
   // 新增：同时写入数据库
   DB::table('oc_sync_log')->insert([
       'source_site' => $dbName,
       'source_table' => $table,
       'source_id' => $maxId,
       'action' => 'sync_success',
       'message' => "同步完成，最大ID: {$maxId}",
   ]);
   ```

3. **错误处理**
   ```php
   // 原有：记录到日志文件
   $this->log('error', $errorMessage);
   
   // 新增：写入数据库并检查重试次数
   $record = DB::table('oc_sync_record')
       ->where('source_site', $dbName)
       ->where('source_table', $table)
       ->where('source_id', $remoteId)
       ->first();
   
   if ($record) {
       $retryCount = $record->retry_count + 1;
       
       if ($retryCount >= 3) {
           // 移入死信队列
           DB::table('oc_sync_dead')->insert([
               'sync_id' => $record->sync_id,
               'source_site' => $dbName,
               'source_table' => $table,
               'source_id' => $remoteId,
               'error_message' => $errorMessage,
               'retry_count' => $retryCount,
           ]);
       } else {
           // 更新重试次数
           DB::table('oc_sync_record')
               ->where('sync_id', $record->sync_id)
               ->update([
                   'status' => 'failed',
                   'error_message' => $errorMessage,
                   'retry_count' => $retryCount,
               ]);
       }
   }
   ```

### 方案二：完全迁移

**完全使用数据库表，废弃文件机制**

#### 优势
- 统一数据存储
- 更强大的查询和管理能力
- 支持事务和并发控制

#### 劣势
- 需要大量重构
- 数据库故障会影响同步
- 失去文件备份的便利性

#### 实现要点

1. **映射查询优化**
   ```php
   // 原有：从JSON读取
   $localId = $this->idMappings[$dbName][$table][$remoteId] ?? null;
   
   // 新增：从数据库查询（带缓存）
   $localId = $this->getIdMappingFromDB($dbName, $table, $remoteId);
   
   private function getIdMappingFromDB($dbName, $table, $remoteId) {
       $cacheKey = "{$dbName}_{$table}_{$remoteId}";
       
       if (isset($this->mappingCache[$cacheKey])) {
           return $this->mappingCache[$cacheKey];
       }
       
       $record = DB::table('oc_sync_record')
           ->where('source_site', $dbName)
           ->where('source_table', $table)
           ->where('source_id', $remoteId)
           ->where('status', 'success')
           ->first();
       
       $localId = $record ? $record->target_id : null;
       $this->mappingCache[$cacheKey] = $localId;
       
       return $localId;
   }
   ```

2. **批量插入优化**
   ```php
   // 批量插入同步记录
   $records = [];
   foreach ($syncData as $item) {
       $records[] = [
           'source_site' => $dbName,
           'source_table' => $table,
           'source_id' => $item['remote_id'],
           'target_id' => $item['local_id'],
           'sync_type' => 'insert',
           'status' => 'success',
           'created_at' => now(),
           'updated_at' => now(),
       ];
   }
   
   DB::table('oc_sync_record')->insert($records);
   ```

## 推荐实现：双轨制

### 修改 RemoteDatabaseOrderSync.php

#### 1. 添加数据库表支持属性

```php
// ========== 数据库表支持 ==========
/** @var bool 是否启用数据库表记录 */
private bool $useDatabaseTables = true;

/** @var array 映射缓存（减少数据库查询） */
private array $mappingCache = [];
```

#### 2. 修改同步记录保存方法

```php
private function saveIdMappings(): void
{
    // 原有逻辑：保存到JSON文件
    $jsonFile = $this->config['mappings']['json_file'];
    // ... 原有代码 ...
    
    // 新增：同步写入数据库表
    if ($this->useDatabaseTables) {
        $this->saveMappingsToDatabase();
    }
}

private function saveMappingsToDatabase(): void
{
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
            
            if (!empty($records)) {
                // 批量插入
                DB::table('oc_sync_record')->insert($records);
            }
        }
    }
}
```

#### 3. 添加日志记录方法

```php
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
    
    DB::table('oc_sync_log')->insert([
        'source_site' => $dbName,
        'source_table' => $table,
        'source_id' => $sourceId,
        'action' => $action,
        'message' => $message,
        'duration' => $duration,
        'created_at' => now(),
    ]);
}
```

#### 4. 添加错误处理和重试机制

```php
private function handleSyncError(
    string $dbName,
    string $table,
    int $remoteId,
    string $errorMessage
): void {
    if (!$this->useDatabaseTables) {
        return;
    }
    
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
        if ($retryCount >= 3) {
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
}
```

#### 5. 添加冲突检测方法

```php
private function detectConflict(
    string $dbName,
    string $table,
    int $remoteId,
    array $localData,
    array $remoteData
): bool {
    // 检查关键字段是否冲突
    $conflictFields = $this->getConflictFields($table);
    $hasConflict = false;
    
    foreach ($conflictFields as $field) {
        if (isset($localData[$field]) && isset($remoteData[$field])) {
            if ($localData[$field] != $remoteData[$field]) {
                $hasConflict = true;
                
                // 记录冲突
                DB::table('oc_sync_conflict')->insert([
                    'source_site' => $dbName,
                    'source_table' => $table,
                    'conflict_type' => "{$table}_conflict",
                    'local_data' => json_encode($localData),
                    'remote_data' => json_encode($remoteData),
                    'created_at' => now(),
                ]);
                
                break;
            }
        }
    }
    
    return $hasConflict;
}
```

## 配置文件更新

### config/remote_databases_orders.php

```php
return [
    // ... 原有配置 ...
    
    // ========== 数据库表支持 ==========
    'use_database_tables' => true,  // 是否启用数据库表记录
    
    'database_tables' => [
        'sync_record' => 'oc_sync_record',
        'sync_log' => 'oc_sync_log',
        'sync_dead' => 'oc_sync_dead',
        'sync_conflict' => 'oc_sync_conflict',
        'sync_rate_limit' => 'oc_sync_rate_limit',
        'store_extend' => 'oc_store_extend',
    ],
    
    // ========== 重试配置 ==========
    'max_retry_count' => 3,  // 最大重试次数
    
    // ========== 冲突检测字段 ==========
    'conflict_fields' => [
        'oc_order' => ['order_status_id', 'total'],
        'oc_customer' => ['email', 'telephone'],
    ],
];
```

## 数据迁移脚本

### 从JSON迁移到数据库

```php
php artisan sync:migrate-to-database
```

```php
// 迁移命令示例
public function handle()
{
    $jsonFile = storage_path('app/remote_order_sync/id_mappings.json');
    $mappings = json_decode(file_get_contents($jsonFile), true);
    
    $records = [];
    foreach ($mappings as $dbName => $tables) {
        foreach ($tables as $tableName => $ids) {
            foreach ($ids as $remoteId => $localId) {
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
    }
    
    // 批量插入
    $chunks = array_chunk($records, 1000);
    foreach ($chunks as $chunk) {
        DB::table('oc_sync_record')->insert($chunk);
    }
    
    $this->info("迁移完成，共 " . count($records) . " 条记录");
}
```

## 使用示例

### 查询同步状态

```sql
-- 查看某个站点的同步状态
SELECT * FROM oc_sync_record 
WHERE source_site = 'shop1' 
AND status = 'success' 
ORDER BY created_at DESC;

-- 查看失败的同步记录
SELECT * FROM oc_sync_record 
WHERE status = 'failed' 
AND retry_count < 3;

-- 查看死信队列
SELECT * FROM oc_sync_dead 
WHERE processed = 0;
```

### 重试失败的同步

```php
php artisan sync:retry-failed
```

### 处理死信队列

```php
php artisan sync:process-dead-queue
```

## 总结

推荐使用**双轨制**方案：
1. 保留现有的CSV和JSON文件机制
2. 同时写入数据库表
3. 数据库表提供额外的管理和查询能力
4. 文件作为备份，数据库作为主记录

这样可以：
- 保持向后兼容
- 提供更强大的管理能力
- 支持错误重试和死信队列
- 支持冲突检测和人工介入
- 不影响现有功能
