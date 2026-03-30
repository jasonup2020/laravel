<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * SaaS安装命令
 * 
 * 一键安装SaaS多租户权限系统
 */
class SaasInstall extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'saas:install {--force : 强制覆盖已存在的文件}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '安装SaaS多租户权限系统';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $this->info('开始安装 SaaS 多租户权限系统...');

        // 1. 检查环境
        $this->checkEnvironment();

        // 2. 生成配置文件
        $this->generateConfigFiles();

        // 3. 运行数据库迁移
        $this->runMigrations();

        // 4. 运行数据填充
        $this->runSeeders();

        // 5. 生成JWT密钥
        $this->generateJwtSecret();

        // 6. 创建存储链接
        $this->createStorageLink();

        // 7. 清除缓存
        $this->clearCache();

        $this->newLine();
        $this->info('✅ SaaS 多租户权限系统安装完成！');
        $this->newLine();
        $this->displayPostInstallInfo();

        return Command::SUCCESS;
    }

    /**
     * 检查环境
     */
    protected function checkEnvironment(): void
    {
        $this->info('检查环境...');

        // 检查PHP版本
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $this->error('PHP版本必须 >= 8.2.0');
            exit(1);
        }

        // 检查必需的扩展
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'xml', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->error("缺少必需的PHP扩展: {$ext}");
                exit(1);
            }
        }

        $this->line('  ✓ 环境检查通过');
    }

    /**
     * 生成配置文件
     */
    protected function generateConfigFiles(): void
    {
        $this->info('生成配置文件...');

        // 复制.env文件
        if (!File::exists(base_path('.env')) || $this->option('force')) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->line('  ✓ .env 文件已创建');
        }

        // 生成应用密钥
        if (empty(config('app.key'))) {
            Artisan::call('key:generate');
            $this->line('  ✓ 应用密钥已生成');
        }

        $this->line('  ✓ 配置文件已生成');
    }

    /**
     * 运行数据库迁移
     */
    protected function runMigrations(): void
    {
        $this->info('运行数据库迁移...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->line('  ✓ 数据库迁移完成');
        } catch (\Exception $e) {
            $this->error('数据库迁移失败: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * 运行数据填充
     */
    protected function runSeeders(): void
    {
        $this->info('运行数据填充...');

        try {
            Artisan::call('db:seed', ['--force' => true]);
            $this->line('  ✓ 数据填充完成');
        } catch (\Exception $e) {
            $this->warn('数据填充失败: ' . $e->getMessage());
        }
    }

    /**
     * 生成JWT密钥
     */
    protected function generateJwtSecret(): void
    {
        $this->info('生成JWT密钥...');

        if (empty(config('jwt.secret'))) {
            Artisan::call('jwt:secret', ['--force' => true]);
            $this->line('  ✓ JWT密钥已生成');
        }
    }

    /**
     * 创建存储链接
     */
    protected function createStorageLink(): void
    {
        $this->info('创建存储链接...');

        if (!File::exists(public_path('storage'))) {
            Artisan::call('storage:link');
            $this->line('  ✓ 存储链接已创建');
        }
    }

    /**
     * 清除缓存
     */
    protected function clearCache(): void
    {
        $this->info('清除缓存...');

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');

        $this->line('  ✓ 缓存已清除');
    }

    /**
     * 显示安装后信息
     */
    protected function displayPostInstallInfo(): void
    {
        $this->info('====================================');
        $this->info('安装信息：');
        $this->info('====================================');
        $this->line('');
        $this->line('默认管理员账号：');
        $this->line('  邮箱: admin@example.com');
        $this->line('  密码: admin123');
        $this->line('');
        $this->line('API文档地址：');
        $this->line('  http://your-domain/api/documentation');
        $this->line('');
        $this->line('下一步操作：');
        $this->line('  1. 配置 .env 文件中的数据库连接');
        $this->line('  2. 配置 .env 文件中的Redis连接');
        $this->line('  3. 运行 php artisan serve 启动服务');
        $this->line('  4. 访问 API 文档测试接口');
        $this->info('====================================');
    }
}
