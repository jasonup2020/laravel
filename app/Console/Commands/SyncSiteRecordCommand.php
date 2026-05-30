<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

// Laravel 13 特性：使用属性定义命令签名和描述

/**
 *  场景                    命令                                                            说明
    默认全量同步            php artisan sync:site-record                                    自动同步 wh_site_sycn_record 所有数据
    自定义批次大小 + 全量   php artisan sync:site-record --batch-size=50                    每批 50 条，同步全量数据
    断点续跑 + 全量         php artisan sync:site-record --resume                           从上次中断的 ID 开始，同步到源表最大 ID
    手动指定 ID 范围        php artisan sync:site-record --start-id=126448 --end-id=340466  兼容原有指定 ID 的需求
    混合场景                php artisan sync:site-record --end-id=500000 --resume           从断点 ID 开始，同步到 ID 500000
 */
#[Signature('sync:site-record
    {--batch-size=100 : 每批插入的条数，默认100}
    {--start-id=1 : 起始ID，默认126448}
    {--end-id=341441 : 结束ID，默认340466}
    {--resume : 是否断点续跑（从上次失败的ID继续）}')]
#[Description('分批将 wh_site_sycn_record 的数据同步到 wh_site_sycn_record_20250209')]
class SyncSiteRecordCommand extends Command {

    /**
     * 断点记录的缓存键名
     */
    protected string $resumeKey = 'site_sync_last_id';

    /**
     * 执行控制台命令
     */
    public function handle(): int {
        // 1. 获取命令行参数（保留原有代码）
        $batchSize = (int) $this->option('batch-size');
        $startId = (int) $this->option('start-id');
        $endId = (int) $this->option('end-id');
        $resume = $this->option('resume');

        
        $old_table='wh_site_sycn_record_20260323';
        $cp_new_table='wh_site_sycn_record_20260323_copy';
        // 2. 断点续跑逻辑（保留原有代码）
        if ($resume) {
            $lastId = cache()->get($this->resumeKey, $startId);
            $this->info("🔄 断点续跑模式，从 ID: {$lastId} 开始执行");
            $startId = $lastId;
        }

        // 3. 基础校验（保留原有代码）
        if ($startId > $endId) {
            $this->error("❌ 起始ID({$startId}) 不能大于结束ID({$endId})");
            return Command::FAILURE;
        }

        
        
        // ===== 新增：预校验源表总数据量 =====
        $totalSourceData = DB::table($old_table)
                ->whereBetween('id', [$startId, $endId])
                ->count();
        if ($totalSourceData === 0) {
            $this->error("❌ 源表 {$old_table} 中 ID [{$startId}-{$endId}] 范围内无任何数据，无需执行同步");
            return Command::FAILURE;
        }
        $this->info("\n📌 源表中待同步总数据量：{$totalSourceData} 条");

        // 4. 初始化变量（保留原有代码）
        $currentId = $startId;
        $successCount = 0; // 成功批次（有数据插入）
        $emptyCount = 0;   // 空批次（无数据可插）
        $failCount = 0;    // 失败批次
        $failLogs = [];    // 失败详情
        // 5. 创建进度条（保留原有代码）
        $totalBatch = ceil(($endId - $startId + 1) / $batchSize);
        $progressBar = $this->output->createProgressBar($totalBatch);
        $this->info("\n🚀 开始同步数据，总计 {$totalBatch} 批，每批 {$batchSize} 条");
        $progressBar->start();

        // 6. 核心：分批插入循环（修改此处逻辑）
        while ($currentId <= $endId) {
            $batchEndId = min($currentId + $batchSize - 1, $endId);

            try {
                // 先查询源表数据（提前判断是否为空）
                $insertData = DB::table($old_table)
                        ->select([
                            'id', 'wh_site_id', 'wh_origin_site_id', 'type',
                            'is_appoint', 'status', 'wh_name', 'url',
                            'msg', 'appoint_data', 'add_time',
                            'run_time', 'created_at', 'updated_at'
                        ])
                        ->whereBetween('id', [$currentId, $batchEndId])
                        ->get()
                        ->toArray();

                // 判断是否有数据
                if (empty($insertData)) {
                    $emptyCount++;
                    $emptyMsg = "ℹ️  无数据：ID [{$currentId}-{$batchEndId}]，源表中无该区间数据";
                    $this->line("\n{$emptyMsg}");
                    Log::info($emptyMsg);
                } else {
                    // 有数据才执行插入
                    DB::beginTransaction();
                    // Convert objects to associative arrays with column names
                    $insertDataAssoc = array_map(function($item) {
                        return (array) $item;
                    }, $insertData);
                    DB::table($cp_new_table)->insertGetId($insertDataAssoc);
                    DB::commit();

                    $successCount++;
                    $successMsg = "✅ 成功：ID [{$currentId}-{$batchEndId}]，插入 " . count($insertData) . " 条";
                    $this->line("\n{$successMsg}");
                    Log::info($successMsg);
                }
            } catch (QueryException $e) {
                // 异常回滚
                DB::rollBack();
                $failCount++;

                $errorMsg = "❌ 失败：ID [{$currentId}-{$batchEndId}]，错误：{$e->getMessage()}";
                $this->error("\n{$errorMsg}");
                Log::error($errorMsg);
                $failLogs[] = $errorMsg;
            }

            // 记录当前执行位置
            cache()->put($this->resumeKey, $batchEndId + 1, 86400);

            // 进度条推进
            $progressBar->advance();

            // 推进当前ID
            $currentId = $batchEndId + 1;

            // 可选休眠
            usleep(50000);
        }

        // 7. 完成进度条（保留原有代码）
        $progressBar->finish();

        // 8. 输出最终汇总（修改汇总逻辑）
        $this->info("\n\n📊 同步完成汇总：");
        $this->line("总批次：{$totalBatch}");
        $this->line("成功批次（有数据）：{$successCount}");
        $this->line("空批次（无数据）：{$emptyCount}");
        $this->line("失败批次：{$failCount}");
        
        
        $totalSourceData2=$totalSourceData - ($emptyCount * $batchSize);
        $this->line("实际插入总条数：{$totalSourceData2}"); // 近似值

        if (!empty($failLogs)) {
            $this->error("\n❌ 失败详情：");
            foreach ($failLogs as $log) {
                $this->error("- {$log}");
            }
        }

        // 9. 清除断点缓存（保留原有代码）
        if ($currentId > $endId) {
            cache()->forget($this->resumeKey);
            $this->info("\n✅ 所有批次执行完成，断点缓存已清除");
        }

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
