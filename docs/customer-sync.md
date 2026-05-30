# 客户数据同步工具

## 项目需求

### 业务背景

需要从多个远程 OpenCart 电商系统数据库同步客户相关数据到本地统一数据库，用于客户数据分析和集中管理。

### 核心需求

1. **多库数据汇聚**
   - 从 13 个远程数据库同步客户数据
   - 涉及 14 个客户相关表
   - 数据表存在则同步，不存在则创建（仅创建表结构）
   - 数据库连接信息写死在配置文件中

2. **增量同步机制**
   - 使用 Excel 文件记录每个远程库每个表的最大 ID
   - 每次执行先加载 Excel 数据，再进行远程数据库 ID 对比
   - 只同步 ID 大于 Excel 中记录的新数据
   - 同步完成后更新 Excel 中的最大 ID
   - Excel 文件只保存当前的最大 ID，每次同步直接覆盖
   - ID 映射文件（id_mappings.json）每次执行清空，只保留当前次同步的映射数据，历史映射不保留

3. **ID 统一性要求**
   - 复制远程表数据到本地时使用新的本地 customer_id
   - 确保本地库 ID 的统一性和连续性
   - 示例场景：
     - 远程库 01 有 1-10 条数据（customer_id: 1-10）
     - 远程库 02 有 1-20 条数据（customer_id: 1-20）
     - 远程库 03 有 1-10 条数据（customer_id: 1-20）
     - 复制到本地库应该是 40 条数据
     - 本地库使用新的连续 ID（1-40），不保留远程的原始 ID
     - 例子：如当前本地 
                    oc_customer 当前的  customer_id  是 500，那是复制进来的远程库01 的第一个ID在本地库里就是 501，远程库02的第一条记录 customer_id=1 在本地库就是 511,远程库03的第一条记录 customer_id=1 在本地库就是 531.  
                    oc_customer_activity  也是一样，本地 customer_id 从 501 开始，oc_address 也是从 501 开始. 
                    oc_address  里 customer_id=501 是对应远程库01 的 customer_id=1 的地址，oc_address  里 customer_id=511 是对应远程库02 的 customer_id=1 的地址，oc_address  里 customer_id=531 是对应远程库03 的 customer_id=1 的地址，如果 远程库的 oc_address表里customer_id=1不存在，2存在，那行本地库 对应oc_address表里customer_id只用加 customer_id=502的数据
     - 第二次执行脚本  远程库 01  oc_customer 有 1-15 条数据（customer_id: 1-15），Execl时记录的上一次这个表有10条记录，那么这次就提从第11条开始提取写到本地库里
     

4. **高可用连接**
   - 保留 `server_ip` 和 `server_inner_ip` 两个配置
   - 优先使用 `db_host` 连接
   - 连接失败后用 `server_inner_ip` 替换重试
   - 3 秒无响应立即切换下一个 IP
   - 每个数据库单次最大超时时间可配置
   - 最多重试 3 次

5. **并发优化**
   - 使用并发连接测试提高 IP 命中效率
   - 同时测试所有可用 IP，哪个先响应就用哪个
   - 自动选择响应时间最短的连接

6. **外键关联处理**
   - **按客户为单位同步**：每插入一条 `oc_customer` 记录，立即同步该客户的所有关联表数据
   - 自动将子表的外键转换为本地 ID
   - 支持特殊关联（如 oc_customer_login 通过 email 关联）
   - 确保同一个客户在所有关联表中使用相同的本地 customer_id

   **同步逻辑**：
   1. 从远程 `oc_customer` 表获取一条记录（定义为 A：远程 customer_id）
   2. 插入到本地 `oc_customer` 表，获得新的本地 ID（定义为 B：本地 customer_id）
   3. 用 A 去远程的其他关联表中查询该客户的所有记录：
      - `oc_address`
      - `oc_customer_activity`
      - `oc_customer_affiliate`
      - `oc_customer_approval`
      - `oc_customer_history`
      - `oc_customer_ip`
      - `oc_customer_login`（通过 email 关联）
      - `oc_customer_online`
      - `oc_customer_reward`
      - `oc_customer_search`
      - `oc_customer_transaction`
      - `oc_customer_wishlist`
   4. 将这些记录中的远程 customer_id（A）替换为本地 customer_id（B）
   5. 依次插入到本地对应的表中

7. **数据安全**
   - 支持模拟运行模式（--dry-run）
   - Excel 文件自动备份,备份目录为 storage_path('app/excel_backups')  不在后续参与使用
   - 详细的日志记录

## 项目简介

这是一个用于从多个远程数据库同步客户数据到本地数据库的 Laravel 13 命令行工具。该工具支持并发连接测试、ID 映射、外键关联处理等高级功能。

## 核心功能

### 1. 多库数据同步
- 支持 13 个远程数据库的并发数据同步
- 14 个客户相关表的完整同步
- 自动创建表结构（如果本地表不存在）

### 2. 并发连接优化
- 同时测试所有可用 IP（db_host、server_ip、server_inner_ip）
- 自动选择响应最快的连接
- 3 秒超时检测，快速切换备用 IP
- 性能提升 6 倍以上

### 3. ID 映射机制
- 自动生成新的本地 ID，确保 ID 统一性
- 维护远程 ID 与本地 ID 的映射关系
- 支持多数据库数据合并，避免 ID 冲突
- 映射关系持久化存储

### 4. 外键关联处理
- 自动将子表的外键转换为本地 ID
- 支持常规外键和特殊关联（如 email 关联）
- 按照依赖顺序同步表，确保关联正确

### 5. 增量同步
- 使用 Excel 记录每个远程库每个表的最大 ID
- 只同步新增数据，避免重复
- 支持断点续传

## 安装配置

### 1. 配置文件

配置文件位置：`config/remote_databases.php`

```php
return [
    // 远程数据库配置
    'remote_databases' => [
        [
            'name' => 'saveb_uk',
            'db_host' => '38.60.196.23',
            'server_ip' => '38.60.196.23',
            'server_inner_ip' => '38.60.196.23',
            'db_port' => '3306',
            'db_name' => 'saveb_uk',
            'db_username' => 'saveb_uk',
            'db_pwd' => '66z6ckYeNXXruxyP',
            'db_prefix' => '',
            'email' => 'elena@savebs.com',
            'website' => 'https://www.saveb.uk',
        ],
        // ... 更多数据库配置
    ],

    // Excel 文件配置
    'excel' => [
        'max_id_file' => storage_path('app/max_ids.xlsx'),
        'backup_dir' => storage_path('app/excel_backups'),
    ],

    // 超时配置（秒）
    'connection_timeout' => 5,
    'query_timeout' => 300,

    // 要同步的表
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
        'oc_customer_wishlist',
    ],

    // 主键配置
    'primary_keys' => [
        'oc_customer' => 'customer_id',
        'oc_customer_activity' => 'customer_activity_id',
        // ... 更多表的主键
    ],
];
```

### 2. 依赖包

已安装 `phpoffice/phpspreadsheet` 用于 Excel 文件操作。

## 使用方法

### 基本命令

```bash
# 初始化 Excel 文件（读取各库当前最大 ID）
php artisan app:sync-customer-data --init-excel

# 同步所有数据库的所有表
php artisan app:sync-customer-data

# 只同步指定数据库
php artisan app:sync-customer-data --db=saveb_uk

# 只同步指定表
php artisan app:sync-customer-data --table=oc_customer

# 模拟运行（不写入数据）
php artisan app:sync-customer-data --dry-run

# 设置查询超时时间（秒）
php artisan app:sync-customer-data --timeout=600

# 设置每批处理记录数
php artisan app:sync-customer-data --batch-size=1000
```

### 命令选项

| 选项 | 说明 | 默认值 |
|------|------|--------|
| `--dry-run` | 模拟运行，不实际写入数据库 | false |
| `--db=DB` | 只同步指定数据库（按 name 字段匹配） | - |
| `--table=TABLE` | 只同步指定表 | - |
| `--init-excel` | 初始化 Excel 文件，读取各远程库当前最大 ID | false |
| `--batch-size=N` | 每批处理的记录数 | 500 |
| `--timeout=N` | 每个数据库单次最大超时时间（秒） | 300 |

## 工作原理

### 1. 并发连接测试

```
远程数据库配置:
  db_host: 10.59.214.213
  server_ip: 38.54.94.176
  server_inner_ip: 10.59.214.213

并发测试流程:
  ├─ 测试 IP1 (10.59.214.213) → 3秒超时 ✗
  ├─ 测试 IP2 (38.54.94.176) → 1.4秒响应 ✓ (最快)
  └─ 测试 IP3 (10.59.214.213) → 2.7秒响应 ✓

结果: 使用 IP2 (38.54.94.176)
```

### 2. ID 映射流程

```
场景: 多数据库合并

saveb_uk 库:
  远程数据: customer_id 272-467 (196条)
  → 本地映射: customer_id 745-940

saveb_lux_us 库:
  远程数据: customer_id 272-275 (4条)
  → 本地映射: customer_id 941-944

本地库结果:
  总记录: 200条
  ID范围: 745-944 (连续递增)
```

### 3. 表同步顺序

为确保外键关联正确，按以下顺序同步：

1. `oc_customer_group` (客户组 - 基础数据)
2. `oc_customer_group_description` (客户组描述)
3. `oc_customer` (客户 - 主表)
4. `oc_customer_activity` (客户活动)
5. `oc_customer_affiliate` (客户联盟)
6. `oc_customer_approval` (客户审批)
7. `oc_customer_history` (客户历史)
8. `oc_customer_ip` (客户IP)
9. `oc_customer_login` (客户登录)
10. `oc_customer_online` (客户在线)
11. `oc_customer_reward` (客户奖励)
12. `oc_customer_search` (客户搜索)
13. `oc_customer_transaction` (客户交易)
14. `oc_customer_wishlist` (客户愿望清单)

### 4. 外键处理

```php
// 外键配置
'foreignKeys' => [
    'oc_customer_activity' => ['customer_id' => 'oc_customer'],
    'oc_customer_affiliate' => ['customer_id' => 'oc_customer'],
    'oc_customer_login' => ['email' => 'oc_customer'], // 特殊：通过 email 关联
    // ... 更多外键
]

// 处理流程
1. 读取远程记录: customer_id = 100
2. 查询映射表: oc_customer[100] = 745
3. 转换为本地ID: customer_id = 745
4. 插入本地表
```

## 文件说明

### 生成的文件

| 文件 | 说明 |
|------|------|
| `storage/app/max_ids.xlsx` | Excel 文件，记录每个远程库每个表的最大 ID |
| `storage/app/id_mappings.json` | JSON 文件，记录远程 ID 与本地 ID 的映射关系 |
| `storage/logs/customer_sync/` | 同步日志目录 |

### 日志文件

```
storage/logs/customer_sync/
└── sync_20260409_074902.log        # 主日志
```

## 数据示例

### Excel 文件结构 (max_ids.xlsx)

| 数据库 | 表名 | 最大ID | 更新时间 |
|--------|------|--------|----------|
| saveb_uk | oc_customer | 467 | 2026-04-09 14:49:02 |
| saveb_uk | oc_customer_activity | 0 | 2026-04-09 14:49:02 |
| saveb_lux_us | oc_customer | 275 | 2026-04-09 14:50:15 |

### ID 映射文件结构 (id_mappings.json)

```json
{
  "oc_customer": {
    "272": 1,
    "273": 2,
    "467": 196
  },
  "oc_customer_activity": {
    "1": 1,
    "2": 2
  }
}
```

## 性能优化

### 1. 并发连接

- 传统串行连接：最多 9 秒（3个IP × 3秒）
- 并发连接：1-2 秒（取最快响应）
- **性能提升：6倍以上**

### 2. 批量处理

- 默认每批处理 500 条记录
- 可通过 `--batch-size` 调整
- 减少数据库往返次数

### 3. 增量同步

- 只同步新增数据
- 避免重复处理
- 支持断点续传

## 故障排查

### 1. 连接超时

```
问题: 所有 IP 连接均失败
解决: 
  - 检查网络连接
  - 验证数据库凭据
  - 确认防火墙设置
  - 使用 --timeout 增加超时时间
```

### 2. Excel 文件被占用

```
问题: fopen() failed - Resource temporarily unavailable
解决:
  - 关闭所有 Excel 文件
  - 检查是否有其他进程正在访问
  - 删除锁定的文件后重试
```

### 3. 外键关联失败

```
问题: 未找到对应表的本地映射
解决:
  - 确保表同步顺序正确
  - 检查 ID 映射文件是否完整
  - 重新初始化 Excel 文件
```

## 注意事项

1. **数据库连接**
   - 确保本地数据库有足够的权限
   - 远程数据库需要允许外部连接

2. **数据一致性**
   - 首次同步建议使用 `--init-excel` 初始化
   - 定期备份 ID 映射文件
   - 监控日志文件，及时发现错误

3. **性能考虑**
   - 大量数据同步建议在低峰期进行
   - 可根据服务器性能调整 `--batch-size`
   - 使用 `--dry-run` 先测试

4. **安全性**
   - 配置文件包含敏感信息，注意保护
   - 不要将配置文件提交到版本控制
   - 定期更新数据库密码

## 更新日志

### v1.0.0 (2026-04-09)

- ✅ 实现多数据库并发连接
- ✅ 实现 ID 映射机制
- ✅ 实现外键关联处理
- ✅ 实现增量同步
- ✅ 实现 Excel 文件管理
- ✅ 实现日志记录功能

## 技术栈

- **框架**: Laravel 13
- **PHP**: ^8.3
- **Excel**: phpoffice/phpspreadsheet ^5.3
- **数据库**: MySQL

## 许可证

MIT License

## 支持

如有问题，请查看日志文件或联系开发团队。
