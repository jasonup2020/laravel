<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * 租户限流配置服务
 * 
 * 管理每个租户的限流配置
 */
class TenantRateLimitService extends BaseService
{
    protected string $modelClass = Tenant::class;
    
    /**
     * 缓存时间（秒）
     */
    protected int $cacheTime = 3600;
    
    /**
     * 获取租户限流配置
     *
     * @param int $tenantId
     * @return array
     */
    public function getRateLimitConfig(int $tenantId): array
    {
        $cacheKey = "tenant_rate_limit:{$tenantId}";
        
        return Cache::remember($cacheKey, $this->cacheTime, function () use ($tenantId) {
            $tenant = Tenant::find($tenantId);
            
            if (!$tenant) {
                return $this->getDefaultConfig();
            }
            
            // 从租户配置中获取限流设置
            $config = $tenant->config ?? [];
            
            return array_merge($this->getDefaultConfig(), $config['rate_limit'] ?? []);
        });
    }
    
    /**
     * 设置租户限流配置
     *
     * @param int $tenantId
     * @param array $config
     * @return bool
     */
    public function setRateLimitConfig(int $tenantId, array $config): bool
    {
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            return false;
        }
        
        // 获取现有配置
        $tenantConfig = $tenant->config ?? [];
        
        // 更新限流配置
        $tenantConfig['rate_limit'] = $config;
        
        // 保存
        $tenant->config = $tenantConfig;
        $tenant->save();
        
        // 清除缓存
        Cache::forget("tenant_rate_limit:{$tenantId}");
        
        return true;
    }
    
    /**
     * 获取默认限流配置
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'global' => 1000,           // 全局默认：每分钟1000次
            'auth' => [
                'login' => 10,          // 登录：每分钟10次
                'refresh' => 10,        // Token刷新：每分钟10次
                'password' => 5,        // 修改密码：每分钟5次
            ],
            'users' => [
                'index' => 100,         // 用户列表：每分钟100次
                'store' => 20,          // 创建用户：每分钟20次
                'update' => 30,         // 更新用户：每分钟30次
                'destroy' => 10,        // 删除用户：每分钟10次
            ],
            'roles' => [
                'index' => 100,
                'store' => 20,
                'update' => 30,
                'destroy' => 10,
            ],
            'departments' => [
                'index' => 100,
                'store' => 20,
                'tree' => 200,
            ],
            'menus' => [
                'index' => 100,
                'tree' => 200,
                'user-menus' => 300,
            ],
            'tenants' => [
                'index' => 50,
                'show' => 100,
                'config' => 20,
            ],
        ];
    }
    
    /**
     * 获取租户当前限流状态
     *
     * @param int $tenantId
     * @return array
     */
    public function getRateLimitStatus(int $tenantId): array
    {
        $config = $this->getRateLimitConfig($tenantId);
        
        // 获取当前使用情况
        $status = [];
        
        foreach ($config as $resource => $limits) {
            if (is_array($limits)) {
                foreach ($limits as $action => $limit) {
                    $key = "rate_limit:tenant:{$tenantId}:endpoint:{$resource}.{$action}";
                    $attempts = \Illuminate\Support\Facades\RateLimiter::attempts($key);
                    $status["{$resource}.{$action}"] = [
                        'limit' => $limit,
                        'current' => $attempts,
                        'remaining' => max(0, $limit - $attempts),
                    ];
                }
            } else {
                $key = "rate_limit:tenant:{$tenantId}:endpoint:{$resource}";
                $attempts = \Illuminate\Support\Facades\RateLimiter::attempts($key);
                $status[$resource] = [
                    'limit' => $limits,
                    'current' => $attempts,
                    'remaining' => max(0, $limits - $attempts),
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * 重置租户限流计数
     *
     * @param int $tenantId
     * @param string|null $endpoint
     * @return bool
     */
    public function resetRateLimit(int $tenantId, ?string $endpoint = null): bool
    {
        if ($endpoint) {
            // 重置特定接口
            $key = "rate_limit:tenant:{$tenantId}:endpoint:{$endpoint}";
            \Illuminate\Support\Facades\RateLimiter::clear($key);
        } else {
            // 重置所有接口
            $config = $this->getRateLimitConfig($tenantId);
            
            foreach ($config as $resource => $limits) {
                if (is_array($limits)) {
                    foreach ($limits as $action => $limit) {
                        $key = "rate_limit:tenant:{$tenantId}:endpoint:{$resource}.{$action}";
                        \Illuminate\Support\Facades\RateLimiter::clear($key);
                    }
                } else {
                    $key = "rate_limit:tenant:{$tenantId}:endpoint:{$resource}";
                    \Illuminate\Support\Facades\RateLimiter::clear($key);
                }
            }
        }
        
        return true;
    }
}
