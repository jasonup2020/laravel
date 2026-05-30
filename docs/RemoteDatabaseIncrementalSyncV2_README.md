# 远程数据库增量同步工具 v3.0 使用说明

## 概述

本工具严格按照 `docs/RemoteDatabaseIncrementalSync.md` 文档开发，实现了13个远程数据库的每日增量同步功能，采用 **JSON+CSV** 存储方式，替代原有的Excel方案。

## 核心特性

### 1. 并发同步
- 支持13个远程数据库并发同步
- 性能较传统单线程模式提升6倍以上
- 可配置并发数据库数量（默认13）

### 2. 高可用连接
- 并发测试3个IP地址（db_host、server_ip、server_inner_ip）
- 自动选择响应最快的IP建立连接
- 3秒超时检测，每个IP最多重试3次
- 自动切换备用IP，保障连接稳定性

### 3. ID映射机制（JSON+CSV）
- **JSON运行时映射**：`storage/app/mappings/id_mapping.json`
  - 三层嵌套结构：`{库名 -> 表名 -> 远程ID -> 本地ID}`
  - 查询效率O(1)，用于运行时快速查询
  - 仅处理当前次13个库的新数据映射

- **CSV历史备份**：`storage/app/mappings/history/history_YYYYMMDD_HHMMSS.csv`
  - 每次同步完成后自动备份本次新映射
  - 格式：`db_name,table_name,remote_id,local_id,timestamp`
  - 保留7天，自动清理过期备份

### 4. 增量同步（CSV进度记录）
- **CSV进度文件**：`storage/app/mappings/sync_last_max_ids.csv`
  - 记录每个远程库每张表的最大同步ID
  - 格式：`db_name,table_name,max_id,sync_time`
  - 支持断点续传，同步中断后可快速恢复

### 5. 外键关联处理
- 严格按照表依赖顺序同步
- 自动将子表外键转换为本地ID
- 支持常规外键（如customer_id）和特殊关联（如email关联）
- 确保关联关系正确、无断裂

### 6. 自动建表
- 同步启动前自动检测本地表是否存在
- 若不存在，从远程库复制表结构创建
- 严格沿用远程库表结构，不修改任何字段

## 文件结构

```
app/Console/Commands/
└── RemoteDatabaseIncrementalSyncV2.php    # 主同步命令类

config/
└── remote_databases_v2.php                 # 配置文件

routes/
└── console.php                             # 定时任务配置

storage/app/mappings/
├── id_mapping.json                         # JSON运行时映射文件
├── sync_last_max_ids.csv                   # CSV进度记录文件
└── history/                                # CSV历史备份目录
    └── history_YYYYMMDD_HHMMSS.csv

storage/logs/remote_sync/
├── sync_YYYYMMDD_HHMMSS.log                # 同步日志
└── sync_error_YYYYMMDD_HHMMSS.log          # 错误日志
```

## 配置说明

### 配置文件：`config/remote_databases_v2.php`

```php
return [
    // 13个远程数据库配置
    'remote_databases' => [
        [
            'name' => 'savebullets_com',
            'db_host' => '10.59.214.241',
            'server_ip' => '38.54.25.52',
            'server_inner_ip' => '10.59.214.241',
            'db_port' => '3306',
            'db_name' => 'savebullets_com',
            'db_username' => 'savebullets_com',
            'db_pwd' => 'LGCzAeKLjCbFGRZz',
            'db_prefix' => '',
            'email' => 'elena@savebs.com',
            'website' => 'https://www.saveb.us',
        ],
        // ... 其他12个数据库
    ],

    // 映射文件与进度记录配置
    'mappings' => [
        'json_file' => storage_path('app/mappings/id_mapping.json'),
        'history_dir' => storage_path('app/mappings/history'),
        'progress_file' => storage_path('app/mappings/sync_last_max_ids.csv'),
        'history_retention_days' => 7,
    ],

    // 超时配置
    'connection_timeout' => 3,   // IP测试超时时间（秒）
    'query_timeout' => 300,      // 单个数据库查询超时时间（秒）

    // IP连接重试配置
    'ip_retry' => [
        'max_retries' => 3,       // 每个IP最多重试次数
    ],

    // 本地数据库配置
    'local_database' => [
        'host' => 'localhost',
        'port' => '3306',
        'database' => 'saveb_cp2026',
        'username' => 'saveb_cp2026',
        'password' => 'Z35kctFFdFMYGPzs',
        'prefix' => '',
    ],

    // 要同步的14张表
    'tables_to_sync' => [
        'oc_customer',
        'oc_address',
        'oc_customer_activity',
        'oc_customer_affiliate',
        'oc_customer_approval',
        'oc_customer_group',
        'oc_customer_group_description',
        'oc_customer_history',
        'oc_customer_ip',
        'oc_customer_login',
        'oc_customer_online',
        'oc_customer_reward',
        'oc_customer_search',
        'oc_customer_transaction',
    ],

    // 表依赖顺序（必须按此顺序同步）
    'sync_order' => [
        'oc_customer_group',
        'oc_customer_group_description',
        'oc_customer',
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
    ],

    // 外键关联配置
    'foreign_keys' => [
        'oc_address' => ['customer_id' => 'oc_customer'],
        'oc_customer_activity' => ['customer_id' => 'oc_customer'],
        // ... 其他外键配置
    ],

    // 并发配置
    'concurrency' => [
        'max_concurrent_dbs' => 13,   // 最大并发数据库数
        'max_concurrent_tables' => 3,  // 每个数据库最大并发表数
    ],
];
```

## 使用方法

### 1. 初始化同步进度

首次使用前，需要初始化同步进度文件：

```bash
php artisan remote-database:incremental-sync-v2 --init
```

此命令会：
- 连接所有远程数据库
- 读取每张表的最大ID
- 生成 `sync_last_max_ids.csv` 进度文件

### 2. 执行增量同步

```bash
php artisan remote-database:incremental-sync-v2
```

此命令会：
- 读取CSV进度文件
- 加载JSON映射文件
- 并发同步13个远程数据库
- 仅同步新增数据（ID > CSV记录的最大ID）
- 更新映射和进度文件

### 3. 强制全量同步

```bash
php artisan remote-database:incremental-sync-v2 --force
```

此命令会：
- 忽略CSV中的最大ID记录
- 全量同步所有数据
- 重新生成所有映射关系

### 4. 模拟运行（测试）

```bash
php artisan remote-database:incremental-sync-v2 --dry-run
```

此命令会：
- 模拟同步过程
- 不实际写入数据库
- 用于测试连接和同步逻辑

### 5. 同步指定数据库

```bash
php artisan remote-database:incremental-sync-v2 --db=savebullets_com
```

### 6. 同步指定表

```bash
php artisan remote-database:incremental-sync-v2 --table=oc_customer
```

### 7. 详细输出模式

```bash
php artisan remote-database:incremental-sync-v2 --verbose
```

### 8. 自定义超时时间

```bash
php artisan remote-database:incremental-sync-v2 --timeout=600 --connection-timeout=5
```

### 9. 自定义并发数

```bash
php artisan remote-database:incremental-sync-v2 --concurrency=5
```

## 定时任务

定时任务已在 `routes/console.php` 中配置，每日凌晨1点自动执行：

```php
Schedule::command('remote-database:incremental-sync-v2')
    ->dailyAt('01:00')
    ->description('13个远程数据库每日增量同步')
    ->withoutOverlapping();
```

### 启用定时任务

#### Windows系统

1. 打开"任务计划程序"
2. 创建基本任务
3. 触发器：每天 01:00
4. 操作：启动程序
   - 程序：`php.exe` 的完整路径
   - 参数：`artisan schedule:run`
   - 起始于：项目根目录

或者使用批处理文件：

```batch
@echo off
cd /d D:\wwwroot\laravel13.test.com
php artisan schedule:run >> storage\logs\scheduler.log
```

#### Linux系统

```bash
crontab -e
```

添加以下内容：

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 同步流程

### 整体同步流程

1. **启动同步任务**
   - 加载配置
   - 初始化日志文件
   - 创建必要的目录

2. **自动建表检测**
   - 遍历14张表
   - 检测本地表是否存在
   - 不存在则从远程库复制结构

3. **加载进度和映射**
   - 读取CSV进度文件
   - 读取JSON映射文件
   - 初始化临时映射数组

4. **并发同步远程库**
   - 同时启动13个远程库的同步任务
   - 每个任务独立执行

5. **单库同步闭环**
   - 高可用IP测试与选择
   - 按表依赖顺序同步
   - 处理外键关联
   - 更新进度

6. **映射持久化与历史备份**
   - 合并临时映射到主映射
   - 保存JSON文件
   - 备份CSV历史文件

7. **生成同步报告**
   - 统计同步数据
   - 记录日志
   - 输出报告

### 单库同步流程

1. **IP测试与连接**
   - 并发测试3个IP
   - 选择响应最快的IP
   - 建立数据库连接

2. **同步基础表**
   - 按依赖顺序同步
   - 单条记录逐一处理
   - 生成ID映射
   - 处理关联数据

3. **同步用户主表**
   - 检查用户是否已存在
   - 插入或更新数据
   - 保存映射关系

4. **同步关联表**
   - 查询关联数据
   - 转换外键为本地ID
   - 插入本地数据库

5. **更新进度**
   - 更新CSV进度文件
   - 记录同步时间

## 异常处理

### 远程库连接异常
- 所有IP连接失败，记录错误日志
- 标记该库同步失败
- 不影响其他库的同步

### 数据同步异常
- 单条记录同步失败，事务回滚
- 跳过该记录，继续处理下一条
- 记录错误日志

### CSV操作异常
- 文件损坏，自动重建
- 基于JSON文件恢复进度
- 不影响同步任务推进

### IP切换异常
- 连接中断，自动切换备用IP
- 记录IP异常日志
- 暂停该库同步

### JSON/CSV操作异常
- JSON文件损坏，基于CSV备份恢复
- CSV备份失败，仅记录日志
- 不删除已有文件

## 数据一致性与验证

### ID映射验证
- 随机选取记录验证映射关系
- 对比JSON文件与CSV备份
- 确保映射准确、持久化有效

### 外键关联验证
- 随机选取用户记录
- 查询关联的子表数据
- 确认外键均为本地ID

### 数据完整性验证
- 对比远程库与本地库记录数
- 确保无数据丢失、无重复

### CSV进度验证
- 随机选取表验证最大ID
- 确保进度记录准确

## 性能指标

- **并发性能**：13个远程库并发同步，性能提升6倍以上
- **同步耗时**：单个远程库（10万行数据）≤3分钟，13个库总耗时≤40分钟
- **资源占用**：CPU≤50%，内存≤1GB
- **IP响应**：3秒超时检测，自动切换

## 日志管理

### 日志文件位置
- 同步日志：`storage/logs/remote_sync/sync_YYYYMMDD_HHMMSS.log`
- 错误日志：`storage/logs/remote_sync/sync_error_YYYYMMDD_HHMMSS.log`

### 日志保留
- 日志文件保留7天
- 自动清理过期日志

### 日志内容
- 同步库名、表名、同步行数
- 耗时、IP连接情况
- 错误信息、映射更新情况
- 进度更新情况

## 维护指南

### 查看同步进度

```bash
cat storage/app/mappings/sync_last_max_ids.csv
```

### 查看ID映射

```bash
cat storage/app/mappings/id_mapping.json
```

### 查看历史备份

```bash
ls -lh storage/app/mappings/history/
```

### 手动调整进度

编辑 `sync_last_max_ids.csv` 文件，修改对应表的最大ID：

```csv
db_name,table_name,max_id,sync_time
savebullets_com,oc_customer,100,2026-04-10 12:00:00
```

### 恢复映射数据

从CSV历史备份恢复：

```bash
# 找到需要恢复的备份文件
cat storage/app/mappings/history/history_20260410_120000.csv

# 手动合并到 id_mapping.json
```

### 清理过期数据

```bash
# 清理过期历史备份（7天）
find storage/app/mappings/history/ -name "history_*.csv" -mtime +7 -delete

# 清理过期日志（7天）
find storage/logs/remote_sync/ -name "sync_*.log" -mtime +7 -delete
```

## 常见问题

### Q1: 同步失败，提示连接超时

**A**: 检查以下项：
- 网络连通性：确保能访问远程数据库IP
- 防火墙：检查防火墙是否阻止连接
- IP配置：确认db_host、server_ip、server_inner_ip配置正确
- 增加超时时间：`--connection-timeout=10`

### Q2: 映射数据丢失

**A**:
- 从CSV历史备份恢复映射数据
- 检查JSON文件是否损坏
- 重新初始化进度：`--init --force`

### Q3: 进度文件损坏

**A**:
- 基于JSON文件恢复进度
- 或重新初始化：`--init`

### Q4: 外键关联错误

**A**:
- 检查表依赖顺序是否正确
- 确认JSON映射文件完整
- 查看错误日志获取详细信息

### Q5: 数据重复

**A**:
- 检查CSV进度文件是否正确更新
- 确认主键配置正确
- 查看日志定位重复数据

## 与旧版本的区别

| 特性 | 旧版本 (Excel) | 新版本 (JSON+CSV) |
|------|---------------|-------------------|
| 进度记录 | Excel文件 | CSV文件 |
| 映射存储 | JSON文件 | JSON + CSV备份 |
| 历史归档 | 无 | CSV历史备份 |
| 文件格式 | .xlsx | .json, .csv |
| 依赖库 | PhpSpreadsheet | 无（PHP内置） |
| 读写性能 | 较慢 | 快速 |
| 文件大小 | 较大 | 小 |
| 可维护性 | 需要Excel软件 | 文本编辑器 |

## 技术支持

如有问题，请查看：
1. 日志文件：`storage/logs/remote_sync/`
2. 错误日志：`storage/logs/remote_sync/sync_error_*.log`
3. 配置文件：`config/remote_databases_v2.php`
4. 技术文档：`docs/RemoteDatabaseIncrementalSync.md`

## 版本历史

### v3.0 (2026-04-10)
- ✅ 采用JSON+CSV存储方式
- ✅ 实现并发IP测试与连接选择
- ✅ 实现ID映射持久化与历史备份
- ✅ 实现CSV进度记录与断点续传
- ✅ 实现外键关联自动转换
- ✅ 实现自动建表功能
- ✅ 完善异常处理机制
- ✅ 优化性能与稳定性

---

**注意**：本工具严格按照 `docs/RemoteDatabaseIncrementalSync.md` 文档开发，确保所有功能符合需求规范。
