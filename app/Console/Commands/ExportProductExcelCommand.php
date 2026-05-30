<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

#[Signature('app:export-product-excel
    {--category=* : 分类ID,可指定多个,如 --category=776 --category=777}
    {--limit=0 : 限制导出数量,0表示不限制}
    {--skip-exported : 跳过已经导出的产品ID}
    {--output= : 输出文件路径,默认为 storage/exports/products_时间戳.xlsx}')]
#[Description('导出产品数据到Excel,包含图片、标题、Model')]
class ExportProductExcelCommand extends Command {

    /**
     * 图片基础URL配置(远程图片域名)
     * 333,329,330,331,332,334,349,347,416,346,414,338,341,337,342,343,417,344,348,345
     *
     *
     * 使用示例

        // ### 1. 导出所有产品
        // ```bash
        // php artisan export:product-excel
        // ```

        // ### 2. 导出指定分类的产品
        // ```bash
        // php artisan export:product-excel --category=776
        // ```

        // ### 3. 导出多个分类的产品
        // ```bash
        // php artisan export:product-excel --category=776 --category=777 --category=778
        // ```

        // ### 4. 限制导出数量
        // ```bash
        // php artisan export:product-excel --limit=50
        // ```

        // ### 5. 指定输出文件路径
        // ```bash
        // php artisan export:product-excel --output=/custom/path/products.xlsx
        // ```

        // ### 6. 综合使用
        // ```bash
        // php artisan export:product-excel --category=776 --category=777 --limit=100 --output=/exports/limited_products.xlsx
     */
    // private string $imageBaseUrl = ' https://www.saveb.net/image/';
    private string $imageBaseUrl = 'https://img.sammylux.net/image/';
    // private string $imageBaseUrl = 'https://img.saveb-transfer.co/image/';    ### https://www.saveb.net/image/cache/catalog/2026/3/27/WHCP/06/画板%206-800x800.png

    /**
     * 默认导出的分类ID列表
     */
    private array $defaultCategoryIds = [333, 329, 330, 331, 332, 334, 349, 347, 416, 346, 414, 338, 341, 337, 342, 343, 417, 344, 348, 345];

    /**
     * 已导出的产品ID记录文件路径
     */
    private string $exportedIdsFile;

    /**
     * 命令执行入口
     *
     * @return int 执行状态码
     */
    public function handle(): int {
        // ini_set('memory_limit', '512M');
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);

        $categoryIds = $this->option('category');
        $limit = (int) $this->option('limit');
        $skipExported = $this->option('skip-exported');
        $outputPath = $this->option('output');

        // 初始化已导出ID文件路径
        $this->exportedIdsFile = storage_path('exports/exported_product_ids.json');

        // 记录开始日志
        Log::info('========== 开始导出产品数据 ==========');
        Log::info('参数: ' . json_encode([
            'category' => $categoryIds,
            'limit' => $limit,
            'skip_exported' => $skipExported,
            'output' => $outputPath
        ]));

        // 验证表是否存在
        if (!$this->tableExists('oc_product')) {
            $this->error('oc_product 表不存在!');
            Log::error('oc_product 表不存在');
            return 1;
        }

        // 验证目标表是否存在
        if (!$this->tableExists('product_exports')) {
            $this->error('product_exports 表不存在!');
            Log::error('product_exports 表不存在');
            return 1;
        }

        // 如果没有指定分类,使用默认分类列表
        if (empty($categoryIds)) {
            $categoryIds = $this->defaultCategoryIds;
            $this->info('使用默认分类: ' . implode(', ', $categoryIds));
            Log::info('使用默认分类: ' . implode(', ', $categoryIds));
        }

        // 获取已导出的产品ID列表
        $exportedProductIds = $skipExported ? $this->getExportedProductIds() : [];

        // 构建查询
        $query = DB::table('oc_product as p')
                ->join('oc_product_description as pd', 'p.product_id', '=', 'pd.product_id')
                ->join('oc_product_to_category as ptc', 'p.product_id', '=', 'ptc.product_id')
                ->join('oc_category_description as cd', 'ptc.category_id', '=', 'cd.category_id')
                ->select([
                    'p.product_id',
                    'p.model',
                    'p.image',
                    'pd.name as title',
                    'p.price',
                    'p.status',
                    'ptc.category_id',
                    'cd.name as category_name'
                ])
                ->where('pd.language_id', 1)
                ->where('cd.language_id', 1)
                ->where(function($query) {
                    $query->where('p.product_id', '<', 51224)
                          ->orWhere('p.product_id', '>', 56122);
                })
                ->whereIn('ptc.category_id', $categoryIds)
                ->distinct();
                // ->groupBy('p.product_id');
                // ->groupBy('p.product_id', 'p.model', 'p.image', 'pd.name', 'p.price', 'p.status', 'ptc.category_id', 'cd.name');


        // 排除已导出的产品ID
        if (!empty($exportedProductIds)) {
            $query->whereNotIn('p.product_id', $exportedProductIds);
            $this->info('跳过已导出的产品数量: ' . count($exportedProductIds));
            Log::info('跳过已导出的产品数量: ' . count($exportedProductIds));
        }

        // 限制数量
        if ($limit > 0) {
            $query->limit($limit);
            $this->info("限制导出数量: {$limit}");
            Log::info("限制导出数量: {$limit}");
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('没有找到符合条件的产品数据');
            Log::warning('没有找到符合条件的产品数据');
            return 0;
        }

        $totalProducts = $products->count();
        $this->info("找到 {$totalProducts} 个产品,开始导出...");
        Log::info("找到 {$totalProducts} 个产品,开始导出...");

        // 创建 Excel 文件
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('产品列表');

        // 设置表头
        $sheet->setCellValue('A1', '产品ID');
        $sheet->setCellValue('B1', '图片');
        $sheet->setCellValue('C1', '标题');
        $sheet->setCellValue('D1', 'Model');
        $sheet->setCellValue('E1', '价格');
        $sheet->setCellValue('F1', '图片路径');
        $sheet->setCellValue('G1', '分类ID');
        $sheet->setCellValue('H1', '分类名');
        $sheet->setCellValue('I1', '状态');

        // 设置表头样式
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

        // 设置列宽
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(50);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(30);
        $sheet->getColumnDimension('I')->setWidth(10);

        // 创建进度条
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        $row = 2;
        $newExportedIds = [];
        $writtenProductIds = [];
        $insertSuccessCount = 0;
        $insertFailCount = 0;





        foreach ($products as $product) {

            // 检查产品ID是否已经写入过,如果已写入则跳过
            if ($skipExported && in_array($product->product_id, $writtenProductIds)) {
                $bar->advance();
                continue;
            }

            // 记录已写入的产品ID
            $writtenProductIds[] = $product->product_id;

            // 写入产品ID
            $sheet->setCellValue('A' . $row, $product->product_id ?? '');

            // 处理图片URL
            $imageUrl = '';
            if ($product->image) {

                $imageUrl = $this->cleanImageUrl($product->image);
            }

            // print_r([$product->image,$imageUrl]);

            $sheet->setCellValue('B' . $row, "");

            // 写入标题
            $sheet->setCellValue('C' . $row, $product->title ?? '');

            // 写入 Model
            $sheet->setCellValue('D' . $row, $product->model ?? '');

            // 写入价格
            $sheet->setCellValue('E' . $row,  0);

            // 写入图片路径
            $sheet->setCellValue('F' . $row, $this->imageBaseUrl.$imageUrl);

            // 写入分类ID
            $sheet->setCellValue('G' . $row, $product->category_id ?? '');

            // 写入分类名
            $sheet->setCellValue('H' . $row, $product->category_name ?? '');

            // 写入状态
            $sheet->setCellValue('I' . $row, $product->status == 1 ? '启用' : '禁用');

            // 设置行高(用于图片显示)
            $sheet->getRowDimension($row)->setRowHeight(200);

            // 如果有图片,尝试嵌入图片
            if ($product->image) {
                $this->insertImage($sheet, 'B' . $row, $imageUrl);
            }

            // 逐条插入到数据库并记录日志
            $insertResult = $this->insertProductRecord($product, $imageUrl);
            if ($insertResult) {
                $insertSuccessCount++;
            } else {
                $insertFailCount++;
            }

            // 记录新导出的产品ID(不在已导出列表中的产品)
            if ($skipExported && !in_array($product->product_id, $exportedProductIds)) {
                if (!in_array($product->product_id, $newExportedIds)) {
                    $newExportedIds[] = $product->product_id;
                }
            }

            sleep(1);
            $row++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // 确定输出路径
        if (!$outputPath) {
            $exportDir = storage_path('exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            $outputPath = $exportDir . '/products_' . date('Ymd_His') . '.xlsx';
        }

        // 保存文件
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $this->info("导出完成!");
        $this->info("文件路径: {$outputPath}");
        $this->info("导出数量: {$totalProducts}");
        $this->info("数据库插入成功: {$insertSuccessCount}");
        $this->info("数据库插入失败: {$insertFailCount}");

        Log::info("导出完成!");
        Log::info("文件路径: {$outputPath}");
        Log::info("导出数量: {$totalProducts}");
        Log::info("数据库插入成功: {$insertSuccessCount}");
        Log::info("数据库插入失败: {$insertFailCount}");

        // 保存新导出的产品ID到文件
        if ($skipExported && !empty($newExportedIds)) {
            $this->saveExportedProductIds($newExportedIds);
            $this->info("已保存 " . count($newExportedIds) . " 个新导出的产品ID");
            Log::info("已保存 " . count($newExportedIds) . " 个新导出的产品ID");
        }

        Log::info('========== 导出结束 ==========');

        return 0;
    }


    // 统一处理图片路径(无论原来是 https 还是已经是 catalog 都能正确转换)
    private function cleanImageUrl($image) {
    // 1. 先把 URL 编码过的字符还原(比如 %20 → 空格)
    $image = urldecode($image);

    // 2. 正则匹配:删除从开头到 /image/ 的所有内容
    $image = preg_replace('/^https?:\/\/.*?\/image\//i', '', $image);

    return $image;
    }



    /**
     * 检查数据表是否存在
     *
     * @param string $table 表名
     * @return bool 表是否存在
     */
    private function tableExists(string $table): bool {
        try {
            DB::table($table)->first();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 插入图片到 Excel 单元格(支持远程图片)
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param string $cell 单元格坐标
     * @param string $imagePath 图片相对路径
     */
    private function insertImage($sheet, string $cell, string $imagePath): void {
        $tempFile = null;

        try {
            // 构建远程图片URL
            $imageUrl = $this->imageBaseUrl . $imagePath;

            // 从远程下载图片到临时文件
            $tempFile = $this->downloadImage($imageUrl);

            if (!$tempFile || !file_exists($tempFile)) {
                return;
            }

            $drawing = new Drawing();
            $drawing->setName('Product Image');
            $drawing->setDescription('Product Image');
            $drawing->setPath($tempFile);
            $drawing->setHeight(200);
            $drawing->setCoordinates($cell);
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        } catch (\Exception $e) {
            // 图片插入失败时忽略
        }
    }

    /**
     * 从远程URL下载图片到临时文件
     *
     * @param string $url 远程图片URL
     * @return string|null 临时文件路径,失败返回null
     */
    private function downloadImage(string $url): ?string {
        try {
            // 对URL进行编码,处理中文和特殊字符
            $encodedUrl = $this->encodeUrl($url);

            // 获取图片扩展名
            $extension = pathinfo(parse_url($encodedUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

            // 创建临时文件
            $tempFile = tempnam(sys_get_temp_dir(), 'product_img_') . '.' . $extension;

            // 使用 cURL 下载图片
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $encodedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_REFERER, 'https://img.sammylux.net/image/');

            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || empty($imageData)) {
                $this->warn("图片下载失败: URL={$encodedUrl}, HTTP={$httpCode}, Error={$curlError}");
                return null;
            }

            // 保存到临时文件
            file_put_contents($tempFile, $imageData);

            return $tempFile;
        } catch (\Exception $e) {
            $this->error("图片下载异常: URL={$url}, Error=" . $e->getMessage());
            return null;
        }
    }

    /**
     * 编码URL,处理中文和特殊字符
     *
     * @param string $url 原始URL
     * @return string 编码后的URL
     */
    private function encodeUrl(string $url): string {
        // 分解URL
        $parsed = parse_url($url);

        if (!$parsed) {
            return $url;
        }

        // 编码路径部分
        if (isset($parsed['path'])) {
            $pathParts = explode('/', $parsed['path']);
            $encodedParts = [];
            foreach ($pathParts as $part) {
                // 对每个路径段进行编码,但不编码斜杠
                $encodedParts[] = rawurlencode($part);
            }
            $parsed['path'] = implode('/', $encodedParts);
        }

        // 编码查询参数
        if (isset($parsed['query'])) {
            $parsed['query'] = http_build_query($this->parseQueryString($parsed['query']));
        }

        // 重新构建URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? ':' . $parsed['pass'] : '';
        $auth = ($user || $pass) ? $user . $pass . '@' : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    /**
     * 解析查询字符串
     *
     * @param string $query 查询字符串
     * @return array 参数数组
     */
    private function parseQueryString(string $query): array {
        $params = [];
        $pairs = explode('&', $query);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            $value = isset($parts[1]) ? urldecode($parts[1]) : '';
            $params[$key] = $value;
        }
        return $params;
    }

    /**
     * 获取已导出的产品ID列表
     *
     * @return array 已导出的产品ID数组
     */
    private function getExportedProductIds(): array {
        if (!file_exists($this->exportedIdsFile)) {
            return [];
        }

        $content = file_get_contents($this->exportedIdsFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * 保存已导出的产品ID到文件
     *
     * @param array $newIds 新导出的产品ID数组
     * @return bool 是否保存成功
     */
    private function saveExportedProductIds(array $newIds): bool {
        // 获取已存在的ID列表
        $existingIds = $this->getExportedProductIds();

        // 合并新旧ID并去重
        $allIds = array_unique(array_merge($existingIds, $newIds));

        // 确保目录存在
        $exportDir = dirname($this->exportedIdsFile);
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // 保存到文件
        $result = file_put_contents($this->exportedIdsFile, json_encode($allIds, JSON_PRETTY_PRINT));

        return $result !== false;
    }

    /**
     * 逐条插入产品记录到数据库并记录日志
     *
     * @param object $product 产品对象
     * @param string $imageUrl 图片URL
     * @return bool 插入是否成功
     */
    private function insertProductRecord($product, string $imageUrl): bool {
        try {
            // 构建插入数据
            $data = [
                'product_id' => $product->product_id,
                'model' => $product->model,
                'title' => $product->title,
                'price' => $product->price,
                'image' => $imageUrl,
                'full_image_url' => $this->imageBaseUrl . $imageUrl,
                'category_id' => $product->category_id,
                'category_name' => $product->category_name,
                'status' => $product->status,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // 生成插入SQL语句
            $sql = "INSERT INTO `product_exports` (`product_id`, `model`, `title`, `price`, `image`, `full_image_url`, `category_id`, `category_name`, `status`, `created_at`, `updated_at`) VALUES (" .
                   "'" . $data['product_id'] . "', " .
                   "'" . addslashes($data['model']) . "', " .
                   "'" . addslashes($data['title']) . "', " .
                   "'" . $data['price'] . "', " .
                   "'" . addslashes($data['image']) . "', " .
                   "'" . addslashes($data['full_image_url']) . "', " .
                   "'" . $data['category_id'] . "', " .
                   "'" . addslashes($data['category_name']) . "', " .
                   "'" . $data['status'] . "', " .
                   "'" . $data['created_at'] . "', " .
                   "'" . $data['updated_at'] . "'" .
                   ")";

            // 记录插入语句到日志
            Log::info("准备插入产品记录 - 产品ID: {$product->product_id}");
            Log::info("插入SQL: " . $sql);

            // 执行插入
            $result = DB::table('product_exports')->insert($data);

            // 记录插入结果
            if ($result) {
                Log::info("插入成功 - 产品ID: {$product->product_id}");
                return true;
            } else {
                Log::error("插入失败 - 产品ID: {$product->product_id}");
                return false;
            }
        } catch (\Exception $e) {
            // 记录错误信息
            Log::error("插入异常 - 产品ID: {$product->product_id}, 错误: " . $e->getMessage());
            Log::error("错误堆栈: " . $e->getTraceAsString());
            return false;
        }
    }
}
