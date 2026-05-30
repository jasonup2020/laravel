# 产品Excel导出命令文档

## 命令说明

`export:product-excel` 命令用于从数据库导出产品数据到Excel文件，包含产品ID、图片、标题、Model、价格、分类ID、分类名、状态等信息。

## 命令用法

```bash
php artisan export:product-excel [选项]
```

## 可用选项

| 选项 | 说明 | 示例 |
|------|------|------|
| `--category=*` | 分类ID，可指定多个分类。不指定时使用默认的20个分类 | `--category=776 --category=777` |
| `--limit=0` | 限制导出数量，0表示不限制 | `--limit=100` |
| `--skip-exported` | 跳过已经导出的产品ID（基于 stored/exports/exported_product_ids.json） | `--skip-exported` |
| `--output=` | 输出文件路径，默认为 `storage/exports/products_时间戳.xlsx` | `--output=/path/to/output.xlsx` |

## 默认分类列表

当不指定 `--category` 参数时，命令默认导出以下20个分类的产品：

```
333, 329, 330, 331, 332, 334, 349, 347, 416, 346, 414, 338, 341, 337, 342, 343, 417, 344, 348, 345
```

## 使用示例

### 1. 导出默认20个分类的产品（推荐）
```bash
php artisan export:product-excel
```

### 2. 导出并跳过已导出的产品ID
```bash
php artisan export:product-excel --skip-exported
```
> 此选项会读取 `storage/exports/exported_product_ids.json` 文件，跳过已记录的产品ID

### 3. 限制数量并跳过已导出的产品
```bash
php artisan export:product-excel --limit=100 --skip-exported
```

### 4. 导出指定分类的产品
```bash
php artisan export:product-excel --category=776
```

### 5. 导出多个分类的产品
```bash
php artisan export:product-excel --category=776 --category=777 --category=778
```

### 6. 限制导出数量
```bash
php artisan export:product-excel --limit=50
```

### 7. 指定输出文件路径
```bash
php artisan export:product-excel --output=/custom/path/products.xlsx
```

### 8. 综合使用
```bash
php artisan export:product-excel --category=776 --category=777 --limit=100 --output=/exports/limited_products.xlsx
```
## Excel文件结构

导出的Excel文件包含以下列：

| 列名 | 列标识 | 说明 | 示例 |
|------|--------|------|------|
| 产品ID | A | 产品的唯一标识 | 12345 |
| 图片 | B | 图片的远程URL，同时嵌入图片预览 | https://img.saveb-transfer.co/catalog/xxx.jpg |
| 标题 | C | 产品名称 | GUCCI 手提包 |
| Model | D | 产品型号 | Model123 |
| 价格 | E | 产品价格 | 999.99 |
| 图片路径 | F | 数据库中的原始图片路径 | catalog/2025/4/15/GUCCI/23/主图/画板 24.jpg |
| 分类ID | G | 产品所属分类的ID | 333 |
| 分类名 | H | 产品所属分类的名称 | LV Women |
| 状态 | I | 产品状态（启用/禁用） | 启用 |

## 图片处理逻辑

命令会根据图片路径的不同格式进行智能处理：

### 1. 完整URL格式
如果图片路径包含 `https://www.saveb.net/image/`：
- 删除 `https://www.saveb.net/image/` 前缀
- 与 `https://img.saveb-transfer.co/catalog/` 组合

**示例：**
```
原始路径: https://www.saveb.net/image/catalog/1.112/7/N4781900.jpg
处理后: https://img.saveb-transfer.co/catalog/catalog/1.112/7/N4781900.jpg
```

### 2. 普通路径格式
如果图片路径是相对路径：
- 直接与 `https://img.saveb-transfer.co/` 组合

**示例：**
```
原始路径: catalog/2025/4/15/GUCCI/23/主图/画板 24.jpg
处理后: https://img.saveb-transfer.co/catalog/2025/4/15/GUCCI/23/主图/画板 24.jpg
```

### 3. 图片下载与嵌入
- 命令会尝试从远程URL下载图片
- 下载的图片会嵌入到Excel的对应单元格中
- 支持中文路径和特殊字符的URL编码处理
- 如果下载失败，会在控制台显示详细错误信息

## 数据来源

命令从以下数据表获取产品信息：

- `oc_product` - 产品基本信息（包含 product_id, model, image, price, status）
- `oc_product_description` - 产品描述信息（包含 name）
- `oc_product_to_category` - 产品分类关联信息
- `oc_category_description` - 分类描述信息（包含分类名称）

## 性能优化

- 命令执行时自动设置内存限制为 512M
- 设置最大执行时间为无限制
- 使用进度条显示导出进度
- 支持大量数据导出

## 注意事项

1. **表存在性检查**：命令会检查 `oc_product` 表是否存在，如果不存在则退出
2. **编码处理**：自动处理URL中的中文字符和特殊字符
3. **错误处理**：图片下载失败时会显示详细错误日志，但不会中断导出过程
4. **输出目录**：默认输出目录为 `storage/exports`，如果不存在会自动创建
5. **文件命名**：默认文件名格式为 `products_年月日_时分秒.xlsx`
6. **产品ID去重**：
   - 使用 `--skip-exported` 选项时，命令会读取 `storage/exports/exported_product_ids.json` 文件
   - 已导出的产品ID会被自动跳过，避免重复导出
   - 每次导出完成后，新的产品ID会自动追加到该JSON文件中
   - 如需重新导出所有产品，删除或清空 `exported_product_ids.json` 文件即可
7. **默认分类**：不指定 `--category` 参数时，默认导出20个指定分类的产品
8. **状态说明**：状态字段中，`1` 表示"启用"，`0` 表示"禁用"

## 错误处理

### 常见错误及解决方案

1. **图片下载失败**
   - 错误信息：`图片下载失败: URL=xxx, HTTP=xxx, Error=xxx`
   - 可能原因：网络问题、URL格式错误、服务器拒绝访问
   - 解决方案：检查网络连接，验证URL格式是否正确

2. **表不存在**
   - 错误信息：`oc_product 表不存在！`
   - 解决方案：检查数据库连接和表结构

3. **内存不足**
   - 解决方案：增加 `memory_limit` 设置或使用 `--limit` 限制导出数量

4. **URL编码问题**
   - 错误信息：`URL rejected: Malformed input to a URL function`
   - 解决方案：命令已自动处理URL编码，如仍有问题请检查原始路径格式

## 配置项

可以在命令类中修改以下配置：

```php
// 远程图片域名
private string $imageBaseUrl = 'https://img.saveb-transfer.co/';

// 图片高度（像素）
$drawing->setHeight(60);

// 行高（用于图片显示）
$sheet->getRowDimension($row)->setRowHeight(60);
```

## 版本历史

- v1.0 - 初始版本，支持基本的产品导出功能
- v1.1 - 添加产品ID列
- v1.2 - 优化图片URL处理逻辑，支持多种URL格式
- v1.3 - 添加URL编码处理，支持中文路径
- v1.4 - 改进错误日志，增强调试能力
- v2.0 - **重大更新**：
  - 添加分类ID和分类名列
  - 添加产品状态列（启用/禁用）
  - 添加默认分类列表（20个分类）
  - 添加产品ID去重功能（--skip-exported 选项）
  - 新增 exported_product_ids.json 文件记录已导出的产品ID
  - 关联 oc_category_description 表获取分类名称

## 相关文件

- 命令文件：`app/Console/Commands/ExportProductExcelCommand.php`
- 输出目录：`storage/exports/`
- 已导出ID记录文件：`storage/exports/exported_product_ids.json`
- 文档目录：`docs/ExportProductExcelCommand.md`

## 快速开始

### 首次导出（导出所有默认分类的产品）
```bash
php artisan export:product-excel
```

### 后续导出（跳过已导出的产品）
```bash
php artisan export:product-excel --skip-exported
```

### 重置导出记录（如需重新导出所有产品）
```bash
rm storage/exports/exported_product_ids.json
```
