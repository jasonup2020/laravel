<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetRequestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:request {url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a GET request to the specified URL every 15 seconds and log CPU/IP info';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');

        // 每15秒记录一次CPU和IP信息
        while (true) {
            $this->logSystemInfo();

            $this->info("Making GET request to: {$url}");

            try {
                $response = Http::get($url);

                if ($response->successful()) {
                    $this->info('Request successful!');
                    $this->info('Status: ' . $response->status());
                    $this->info('Response:');
                    $this->line($response->body());
                } else {
                    $this->error('Request failed!');
                    $this->error('Status: ' . $response->status());
                    $this->error('Response:');
                    $this->line($response->body());
                }
            } catch (\Exception $e) {
                $this->error('Request error: ' . $e->getMessage());
            }

            $this->info('Waiting 15 seconds before next request...');
            $this->newLine();
            sleep(15);
        }

        return Command::SUCCESS;
    }

    /**
     * 记录系统信息（CPU和IP）
     */
    protected function logSystemInfo(): void
    {
        $cpu = $this->getCPUUsage();
        $ip = $this->getServerIP();
        $time = now()->toDateTimeString();

        $this->info('=== System Information ===');
        $this->info("Time: {$time}");
        $this->info("CPU Usage: {$cpu}");
        $this->info("Server IP: {$ip}");
        $this->info('=========================');
        $this->newLine();

        // 同时记录到日志文件
        \Log::info('System Info', [
            'time' => $time,
            'cpu_usage' => $cpu,
            'server_ip' => $ip,
        ]);
    }

    /**
     * 获取CPU使用率
     */
    protected function getCPUUsage(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows 系统
            $output = shell_exec('wmic cpu get loadpercentage');
            if ($output) {
                $lines = explode("\n", trim($output));
                if (isset($lines[1])) {
                    return trim($lines[1]) . '%';
                }
            }
            return 'N/A';
        } elseif (PHP_OS_FAMILY === 'Linux') {
            // Linux 系统
            $load = sys_getloadavg();
            return $load[0] ?? 'N/A';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // macOS 系统
            $output = shell_exec("top -l 1 | grep 'CPU usage'");
            if ($output && preg_match('/(\d+\.?\d*)% user/', $output, $matches)) {
                return $matches[1] . '%';
            }
            return 'N/A';
        }

        return 'N/A';
    }

    /**
     * 获取服务器IP地址
     */
    protected function getServerIP(): string
    {
        // 尝试获取公网IP
        try {
            $response = Http::timeout(5)->get('https://api.ipify.org?format=text');
            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Exception $e) {
            // 忽略错误，继续尝试其他方法
        }

        // 尝试获取本地IP
        $ip = gethostbyname(gethostname());
        if ($ip !== gethostname()) {
            return $ip;
        }

        // 尝试通过 $_SERVER 获取
        if (isset($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }

        if (isset($_SERVER['LOCAL_ADDR'])) {
            return $_SERVER['LOCAL_ADDR'];
        }

        return '127.0.0.1';
    }
}
