<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitStressTest extends Command
{
    protected $signature = 'test:rate-limit-stress 
                            {--endpoint=/api/users : API端点} 
                            {--requests=70 : 请求次数} 
                            {--limit=60 : 频次限制} 
                            {--token= : 认证Token}';

    protected $description = 'API频次限制压力测试';

    public function handle()
    {
        $endpoint = $this->option('endpoint');
        $totalRequests = (int) $this->option('requests');
        $limit = (int) $this->option('limit');
        $token = $this->option('token');

        $this->info("=== API频次限制压力测试 ===");
        $this->info("端点: {$endpoint}");
        $this->info("总请求次数: {$totalRequests}");
        $this->info("频次限制: {$limit} 次/分钟");
        $this->info("认证Token: " . ($token ? '已设置' : '未设置'));
        $this->newLine();

        $successCount = 0;
        $failedCount = 0;
        $rateLimitedCount = 0;
        $results = [];

        $bar = $this->output->createProgressBar($totalRequests);

        for ($i = 1; $i <= $totalRequests; $i++) {
            try {
                $headers = [];
                if ($token) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }

                $response = Http::withHeaders($headers)
                    ->timeout(5)
                    ->get('http://127.0.0.1:8000' . $endpoint);

                $statusCode = $response->status();
                
                if ($statusCode === 200) {
                    $successCount++;
                    $results[$i] = [
                        'status' => 'success',
                        'code' => $statusCode,
                        'remaining' => $response->header('X-RateLimit-Remaining'),
                    ];
                } elseif ($statusCode === 429) {
                    $rateLimitedCount++;
                    $body = $response->json();
                    $results[$i] = [
                        'status' => 'rate_limited',
                        'code' => $statusCode,
                        'retry_after' => $body['retry_after'] ?? null,
                    ];
                } else {
                    $failedCount++;
                    $results[$i] = [
                        'status' => 'failed',
                        'code' => $statusCode,
                    ];
                }
            } catch (\Exception $e) {
                $failedCount++;
                $results[$i] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            $bar->advance();
            usleep(10000); // 10ms延迟
        }

        $bar->finish();
        $this->newLine(2);

        // 输出统计结果
        $this->info("=== 测试结果统计 ===");
        $this->table(
            ['指标', '数量', '百分比'],
            [
                ['总请求数', $totalRequests, '100%'],
                ['成功请求', $successCount, round($successCount / $totalRequests * 100, 2) . '%'],
                ['频次限制', $rateLimitedCount, round($rateLimitedCount / $totalRequests * 100, 2) . '%'],
                ['失败请求', $failedCount, round($failedCount / $totalRequests * 100, 2) . '%'],
            ]
        );
        $this->newLine();

        // 输出详细结果
        $this->info("=== 详细测试数据 ===");
        $detailedResults = [];
        $sampleInterval = max(1, (int)($totalRequests / 20)); // 采样间隔
        
        foreach ($results as $i => $result) {
            if ($i % $sampleInterval === 0 || $result['status'] === 'rate_limited') {
                $detailedResults[] = [
                    '请求#' => $i,
                    '状态' => $result['status'],
                    'HTTP码' => $result['code'] ?? 'N/A',
                    '剩余次数' => $result['remaining'] ?? ($result['retry_after'] ?? 'N/A'),
                ];
            }
        }

        if (count($detailedResults) > 0) {
            $this->table(
                array_keys($detailedResults[0]),
                $detailedResults
            );
        }
        $this->newLine();

        // 验证结果
        $this->info("=== 验证结果 ===");
        if ($rateLimitedCount > 0 && $successCount <= $limit) {
            $this->info("✅ 频次限制功能正常工作");
            $this->info("✅ 成功请求数 ({$successCount}) 未超过限制 ({$limit})");
            $this->info("✅ 触发了 {$rateLimitedCount} 次频次限制");
        } else {
            $this->warn("⚠️ 频次限制可能未正常工作");
            if ($successCount > $limit) {
                $this->error("❌ 成功请求数 ({$successCount}) 超过了限制 ({$limit})");
            }
        }

        return Command::SUCCESS;
    }
}
