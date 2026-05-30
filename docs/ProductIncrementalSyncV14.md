# ProductIncrementalSyncV14 产品增量同步命令

## 版本信息
- 版本号：14.0
- 日期：2026-05-05
- 前身版本：V3、V6、V12、V13

---

## 版本演进

### V3（2026-04-20）
1. 智能匹配 - 4级渐进式精确匹配策略
2. 性能优化 - 批量处理，性能提升12倍
3. 智能批量 - 根据数据量自动选择最优策略
4. 自动回退 - 批量失败自动回退到逐条处理
5. 图片处理 - 自动转换图片URL，支持HTML内容中的图片
6. 相似度匹配 - 名称相似度≥95%判定为更新
7. 本地保护 - 保护本地独立添加的产品
8. 数据去重 - 检测并处理重复数据

### V6（2026-04-21）
1. 适配「本地产品ID不定时新增」场景
2. 仅同步「远程有、本地无」的产品数据
3. 绝对不删除、不修改、不覆盖本地已存在的数据
4. 远程新增数据映射为本地全新ID
5. 所有子表以本地新ID关联存储
6. 固定远程数据单次提取3000条

### V12（2026-04-27）
1. 继承V9的产品增量同步功能
2. 完善 oc_category 系列表的同步支持
3. 新增 oc_option 系列表同步，采用全量ID匹配模式
4. 支持产品和分类的同步映射
5. 支持更新模式：新增不查date_modified，只有更新才根据date_modified判断

### V13（2026-04-27）
- 继承V12的所有特性

### V14（2026-05-05）
1. 完善 oc_product_option 和 oc_product_option_value 使用各自的主键映射
2. 完善 oc_category.parent_id 正确映射为本地 category_id
3. 统一所有子表的ID映射关系
4. 新增指定分类ID同步功能（--category-ids），不受时间限制

---

## 核心特性

1. **智能增量同步** - 首次同步全量，后续仅同步新增和更新的数据
2. **ID映射机制** - 远程ID与本地ID建立映射关系，支持跨表关联
3. **子表关联同步** - 产品、分类、选项的子表使用本地新ID关联存储
4. **分类层级映射** - 支持 oc_category.parent_id 的正确映射
5. **选项值映射** - oc_product_option 和 oc_product_option_value 使用各自的主键映射
6. **批量处理** - 固定每次提取3000条数据，采用批量插入模式
7. **同步类型选择** - 支持仅产品、仅分类、仅选项或全部同步
8. **指定产品ID同步** - 支持传入指定产品ID列表，不受时间限制
9. **指定分类ID同步** - 支持传入指定分类ID列表，不受时间限制
10. **日期范围同步** - 支持指定日期或最近N天数据同步
11. **模拟运行** - 支持 dry-run 模式预览同步结果
12. **智能匹配** - 4级渐进式精确匹配策略
13. **相似度匹配** - 名称相似度≥95%判定为更新
14. **图片URL处理** - 自动转换图片URL，支持HTML内容中的图片
15. **本地保护** - 保护本地产品不被删除或修改
16. **自动回退机制** - 批量插入失败自动回退到逐条处理

---

## 命令参数

```bash
php artisan product:sync-v14 [选项]
```

| 选项 | 说明 |
|------|------|
| `--dry-run` | 模拟运行，不实际执行同步 |
| `--force` | 强制全量同步 |
| `--site=` | 指定站点名称 |
| `--date=` | 指定日期（格式：Y-m-d） |
| `--days=` | 最近N天 |
| `--ids=` | 指定产品ID列表（逗号分隔），不受时间限制 |
| `--category-ids=` | 指定分类ID列表（逗号分隔），不受时间限制 |
| `--timeout=` | 超时时间，默认300秒 |
| `--sync-type=` | 同步类型：product/category/option/all（默认all） |

---

## ID映射关系

### 产品子表映射

| 表 | 字段 | 映射来源 |
|----|------|---------|
| `oc_product_attribute` | `product_id` | `oc_product.product_id` |
| `oc_product_delete` | `product_id` | `oc_product.product_id` |
| `oc_product_description` | `product_id` | `oc_product.product_id` |
| `oc_product_discount` | `product_id` | `oc_product.product_id` |
| `oc_product_filter` | `product_id` | `oc_product.product_id` |
| `oc_product_image` | `product_id` | `oc_product.product_id` |
| `oc_product_option` | `product_id` | `oc_product.product_id` |
| `oc_product_option` | `product_option_id` | **自身主键映射** |
| `oc_product_option` | `option_id` | `oc_option.option_id` |
| `oc_product_option_value` | `product_id` | `oc_product.product_id` |
| `oc_product_option_value` | `product_option_id` | `oc_product_option.product_option_id` |
| `oc_product_option_value` | `product_option_value_id` | **自身主键映射** |
| `oc_product_option_value` | `option_id` | `oc_option.option_id` |
| `oc_product_option_value` | `option_value_id` | `oc_option_value.option_value_id` |
| `oc_product_recurring` | `product_id` | `oc_product.product_id` |
| `oc_product_related` | `product_id` | `oc_product.product_id` |
| `oc_product_reward` | `product_id` | `oc_product.product_id` |
| `oc_product_special` | `product_id` | `oc_product.product_id` |
| `oc_product_to_category` | `product_id` | `oc_product.product_id` |
| `oc_product_to_category` | `category_id` | `oc_category.category_id` |
| `oc_product_to_download` | `product_id` | `oc_product.product_id` |
| `oc_product_to_layout` | `product_id` | `oc_product.product_id` |
| `oc_product_to_store` | `product_id` | `oc_product.product_id` |

### 分类子表映射

| 表 | 字段 | 映射来源 |
|----|------|---------|
| `oc_category` | `parent_id` | `oc_category.category_id` |
| `oc_category_delete` | `category_id` | `oc_category.category_id` |
| `oc_category_description` | `category_id` | `oc_category.category_id` |
| `oc_category_filter` | `category_id` | `oc_category.category_id` |
| `oc_category_path` | `category_id` | `oc_category.category_id` |
| `oc_category_path` | `path_id` | `oc_category.category_id` |
| `oc_category_to_layout` | `category_id` | `oc_category.category_id` |
| `oc_category_to_store` | `category_id` | `oc_category.category_id` |

### 选项子表映射

| 表 | 字段 | 映射来源 |
|----|------|---------|
| `oc_option_description` | `option_id` | `oc_option.option_id` |
| `oc_option_value` | `option_value_id` | **自身主键映射** |
| `oc_option_value_description` | `option_id` | `oc_option.option_id` |
| `oc_option_value_description` | `option_value_id` | `oc_option_value.option_value_id` |

---

## 表结构参考

### oc_product_option
```sql
CREATE TABLE `oc_product_option` (
  `product_option_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `value` text NOT NULL,
  `required` tinyint(1) NOT NULL,
  PRIMARY KEY (`product_option_id`) USING BTREE,
  KEY `product_id` (`product_id`) USING BTREE,
  KEY `option_id` (`option_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=179840 DEFAULT CHARSET=utf8;
```

### oc_product_option_value
```sql
CREATE TABLE `oc_product_option_value` (
  `product_option_value_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_option_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `option_value_id` int(11) NOT NULL,
  `quantity` int(3) NOT NULL,
  `subtract` tinyint(1) NOT NULL,
  `price` decimal(15,4) NOT NULL,
  `price_prefix` varchar(1) NOT NULL,
  `points` int(8) NOT NULL,
  `points_prefix` varchar(1) NOT NULL,
  `weight` decimal(15,8) NOT NULL,
  `weight_prefix` varchar(1) NOT NULL,
  PRIMARY KEY (`product_option_value_id`) USING BTREE,
  KEY `product_option_id` (`product_option_id`) USING BTREE,
  KEY `product_id` (`product_id`) USING BTREE,
  KEY `option_id` (`option_id`) USING BTREE,
  KEY `option_value_id` (`option_value_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=854865 DEFAULT CHARSET=utf8;
```

---

## 映射文件

**路径：** `storage/app/remote_product_sync/id_mappings.json`

```json
{
  "站点名称": {
    "oc_product": {
      "远程product_id": "本地product_id"
    },
    "oc_category": {
      "远程category_id": "本地category_id"
    },
    "oc_option": {
      "远程option_id": "本地option_id"
    },
    "oc_product_option": {
      "远程product_option_id": "本地product_option_id"
    },
    "oc_product_option_value": {
      "远程product_option_value_id": "本地product_option_value_id"
    },
    "oc_option_value": {
      "远程option_value_id": "本地option_value_id"
    }
  }
}
```

---

## 使用示例

```bash
# 基本同步
php artisan product:sync-v14

# 模拟运行
php artisan product:sync-v14 --dry-run

# 指定站点
php artisan product:sync-v14 --site=savebull_cp2026

# 指定日期
php artisan product:sync-v14 --date=2026-04-23

# 最近N天
php artisan product:sync-v14 --days=7

# 指定产品ID（不受时间限制）
php artisan product:sync-v14 --ids=59128,50580

# 指定分类ID（不受时间限制）
php artisan product:sync-v14 --category-ids=190,191,192

# 仅同步产品/分类/选项
php artisan product:sync-v14 --sync-type=product
php artisan product:sync-v14 --sync-type=category
php artisan product:sync-v14 --sync-type=option
```

---

## 同步流程

### 产品同步流程
1. 获取远程产品列表（根据日期或指定ID）
2. 判断是否为首次同步
3. 首次同步：直接插入新记录并建立映射
4. 非首次同步：检查映射文件，比较 date_modified 判断更新
5. 同步产品子表（使用本地新ID关联）
6. 更新映射文件

### 分类同步流程
1. 获取远程分类列表（根据日期或指定ID）
2. 判断是否为首次同步
3. 首次同步：直接插入新记录并建立映射
4. 非首次同步：检查映射文件，比较 date_modified 判断更新
5. 指定分类ID模式：优先查找映射，找不到则尝试智能匹配，或作为新增处理
6. 同步分类子表（包括 parent_id 的映射）
7. 更新映射文件

### 选项同步流程
1. 获取远程选项列表（全量ID匹配）
2. 检查本地是否存在相同 option_id 的选项
3. 存在则更新，不存在则插入
4. 同步选项子表
5. 更新映射文件

---

## 映射关系详解

### oc_product_option 映射示例

**远程数据：**
- `oc_product.product_id = 59128`
- `oc_product_option.product_id = 59128`
- `oc_product_option.option_id = [48, 43, 49, 50, 51]`
- `oc_product_option.product_option_id = [180191, 180192, 180193, 180194, 180195]`

**本地数据：**
- `oc_product.product_id = 60224`（新插入）
- `oc_product_option.product_id = 60224`（映射后的本地product_id）
- `oc_product_option.product_option_id = [249242, 249243, 249244, 249245, 249246]`（新插入，自动递增）

**映射关系：**
- 远程 180191 → 本地 249242
- 远程 180192 → 本地 249243
- 远程 180193 → 本地 249244
- 远程 180194 → 本地 249245
- 远程 180195 → 本地 249246
- `oc_product_option_value.product_option_id` 使用映射后的本地 product_option_id

### oc_category.parent_id 映射示例

**远程数据：**
- `oc_category.category_id = 100`
- `oc_category.parent_id = 50`

**本地数据：**
- `oc_category.category_id = 200`（新插入）
- 远程 parent_id = 50 映射为本地 parent_id = 150

**映射关系：**
- 远程 category_id 50 → 本地 category_id 150
- 新增分类时，远程 parent_id = 50 自动替换为本地 parent_id = 150

---

## 同步原则

1. **不删除本地数据** - 仅同步远程新增和更新的数据
2. **不修改已存在数据** - 仅新增记录，已存在记录不受影响
3. **子表依赖主表** - 只有主表同步后，才会同步对应的子表
4. **使用本地新ID** - 所有子表关联字段使用映射后的本地ID
5. **批量处理** - 采用批量提取和插入，提高同步效率

---

## 匹配策略

### 渐进式精确匹配

| 级别 | 匹配条件 | 说明 |
|------|----------|------|
| 01 | date_added + model | 基础匹配 |
| 02 | date_added + model + image | 图片提纯后对比 |
| 03 | date_added + model + image + name | 名称完全匹配 |
| 04 | date_added + model + image + name相似度 | 相似度≥95%判定为更新 |

### 图片提纯逻辑

| 原始路径 | 提纯后 |
|---------|--------|
| `https://img.saveb.link/image/catalog/2024/08/28/abc.jpg` | `catalog/2024/08/28/abc.jpg` |
| `https://img.saveb.net/image/catalog/2024/08/28/abc.jpg` | `catalog/2024/08/28/abc.jpg` |
| `catalog/2024/08/28/abc.jpg` | `catalog/2024/08/28/abc.jpg` |

---

## 智能批量处理

### 策略选择

| 数据量范围 | 处理模式 | 批量大小 |
|-----------|---------|---------|
| ≤ 10,000条 | 逐条插入 | 每批100条 |
| 10,001 - 50,000条 | 批量插入 | 每批1000条 |
| > 50,000条 | 批量插入 | 每批5000条 |

### 自动回退机制
```
批量插入失败 → 自动回滚事务 → 回退到逐条插入 → 确保数据完整性
```

---

## 图片URL处理

### 图片CDN
默认使用：`https://img.saveb.link/image`

### 处理的字段

| 表名 | 字段 |
|------|------|
| oc_product | image |
| oc_product_description | description |
| oc_product_image | image |
| oc_category | image |

---

## 性能对比

| 指标 | 优化前 | 优化后 | 提升 |
|------|--------|--------|------|
| 数据库查询次数 | 6000次 | 10次 | 600倍 |
| 处理时间 | 60秒 | 5秒 | 12倍 |

---

## 同步记录追踪

### oc_sync_record 字段说明

| 字段 | 说明 |
|------|------|
| source_site | 来源站点名称 |
| source_table | 来源表名 |
| source_id | 远程ID |
| target_id | 本地ID |
| sync_type | insert/update/delete/skip |
| status | success/failed/pending |
| error_message | 错误信息JSON |

### sync_type 说明

| 类型 | 说明 | 使用场景 |
|------|------|----------|
| insert | 插入新数据 | 新增产品 |
| update | 更新数据 | 产品名称更新 |
| delete | 删除数据 | 反向同步删除孤立记录 |
| skip | 跳过数据 | 产品已存在、本地独立添加 |
