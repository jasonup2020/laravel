# CopyProductByIdCommand 使用文档

## 概述

`CopyProductByIdCommand` 是一个 Laravel Artisan 命令，用于复制指定产品ID的产品数据，并进行分类映射和价格规则处理。

## 功能特性

- ✅ 支持单个或多个产品ID复制
- ✅ 支持从文件读取产品ID列表
- ✅ 模拟运行模式（dry-run）
- ✅ 分类映射转换
- ✅ 价格规则处理
- ✅ 礼品包装选项自动添加
- ✅ 完整的错误日志记录
- ✅ SQL语句日志记录

## 命令语法

```bash
php artisan app:copy-product-by-id [options]
```

## 参数说明

| 参数 | 说明 | 示例 |
|------|------|------|
| `--ids` | 要复制的产品ID，多个ID用逗号分隔 | `--ids=1,2,3` |
| `--file` | 从文件读取产品ID列表，每行一个ID | `--file=/path/to/ids.txt` |
| `--dry-run` | 仅模拟运行，不实际写入数据库 | `--dry-run` |

## 使用示例

### 1. 复制单个产品

```bash
php artisan app:copy-product-by-id --ids=123
```

### 2. 复制多个产品

```bash
php artisan app:copy-product-by-id --ids=123,456,789
```

### 3. 从文件读取产品ID

创建文件 `product_ids.txt`：
```
123
456
789
1001
```

执行命令：
```bash
php artisan app:copy-product-by-id --file=product_ids.txt
```

### 4. 模拟运行（不写入数据库）

```bash
php artisan app:copy-product-by-id --ids=123 --dry-run
```

## 分类映射规则

| 原分类ID | 目标分类ID | 分类组 |
|----------|------------|--------|
| 333 | 778 | LV Women |
| 329 | 779 | LV Women |
| 330 | 780 | LV Women |
| 331 | 781 | LV Women |
| 332 | 782 | LV Women |
| 334 | 783 | LV Women |
| 349 | 797 | LV Women |
| 347 | 784 | LV Women |
| 416 | 785 | LV Women |
| 346 | 786 | LV Women |
| 414 | 787 | LV Women |
| 338 | 788 | LV men |
| 341 | 789 | LV men |
| 337 | 790 | LV men |
| 342 | 791 | LV men |
| 343 | 792 | LV men |
| 417 | 793 | LV men |
| 344 | 794 | LV men |
| 348 | 795 | LV men |
| 345 | 796 | LV men |

## 价格处理规则

### 价格结尾为 19

| 价格区间 | 处理规则 |
|----------|----------|
| < 200 | 改成 59 结尾 |
| 200-500 | 改成 89 结尾 |
| > 500 | +100 改成 19 结尾 |

### 价格结尾为 59

| 价格区间 | 处理规则 |
|----------|----------|
| < 200 | 改成 89 结尾 |
| 200-500 | +100 改成 19 结尾 |
| > 500 | +100 改成 59 结尾 |

### 价格结尾为 89

| 价格区间 | 处理规则 |
|----------|----------|
| < 200 | +100 改成 19 结尾 |
| 200-500 | +100 改成 59 结尾 |
| > 500 | +100 改成 89 结尾 |

## 礼品包装价格规则

| 产品价格区间 | 礼品包装价格 |
|--------------|--------------|
| 200-300 | $10 |
| 300-700 | $20 |
| > 700 | $30 |

## 日志文件

日志文件存储在 `storage/logs/product_copy/` 目录下：

### 1. 主日志文件
- 文件名格式：`copy_YYYYMMDD_HHMMSS.log`
- 内容：处理进度、成功/失败信息

### 2. 错误日志文件
- 文件名格式：`copy_error_YYYYMMDD_HHMMSS.log`
- 内容：外键冲突、数据库错误等详细信息

错误日志格式示例：
```json
{
  "timestamp": "2026-04-04 12:00:00",
  "table": "oc_product_description",
  "old_product_id": 123,
  "new_product_id": 456,
  "error": "SQLSTATE[23000]: Integrity constraint violation...",
  "sql": "INSERT INTO `oc_product_description` ...",
  "data": {
    "product_id": 456,
    "language_id": 1,
    "name": "Product Name MASTER"
  }
}
```

### 3. SQL日志文件
- 文件名格式：`copy_sql_YYYYMMDD_HHMMSS.log`
- 内容：所有执行的SQL语句和数据

## 复制的数据表

脚本会复制以下相关数据：

| 表名 | 说明 |
|------|------|
| `oc_product` | 产品主表 |
| `oc_product_description` | 产品描述 |
| `oc_product_image` | 产品图片 |
| `oc_product_option` | 产品选项 |
| `oc_product_option_value` | 产品选项值 |
| `oc_product_to_category` | 产品分类关联 |
| `oc_product_to_layout` | 产品布局 |
| `oc_product_to_store` | 产品店铺关联 |

## 错误处理

### 外键冲突处理

当发生外键冲突时，脚本会：
1. 记录错误信息到错误日志
2. 记录完整的SQL语句
3. 记录插入的数据内容
4. 继续处理下一个产品

### 常见错误

| 错误类型 | 原因 | 解决方案 |
|----------|------|----------|
| Duplicate entry | 主键或唯一键冲突 | 检查产品ID是否已存在 |
| Foreign key constraint | 外键约束失败 | 检查关联数据是否存在 |
| 产品不存在 | 指定的产品ID不存在 | 确认产品ID是否正确 |

## 注意事项

1. **数据备份**：执行前建议备份数据库
2. **测试运行**：建议先用 `--dry-run` 模式测试
3. **内存限制**：处理大量产品时可能需要调整内存限制
4. **执行时间**：处理大量产品可能需要较长时间

## 完整示例

```bash
# 1. 先模拟运行测试
php artisan app:copy-product-by-id --ids=123,456 --dry-run

# 2. 确认无误后正式执行
php artisan app:copy-product-by-id --ids=123,456

# 3. 查看日志
cat storage/logs/product_copy/copy_*.log
```

## 相关命令

- `app:product-data-processor` - 按分类批量处理产品
- `app:export-product-excel` - 导出产品数据到Excel
