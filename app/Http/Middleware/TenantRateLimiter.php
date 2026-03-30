<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\BusinessException;

/**
 * 租户限流中间件
 * 
 * 为每个租户的每个接口提供独立的限流控制
 * 支持按租户ID、接口路径、用户ID进行精细化限流
 */
class TenantRateLimiter
{
    /**
     * 默认限流配置（每分钟最大请求数）
     */
    protected array $defaultLimits = [
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
            'tree' => 200,          // 树形结构访问更频繁
        ],
        'menus' => [
            'index' => 100,
            'tree' => 200,
            'user-menus' => 300,    // 用户菜单访问最频繁
        ],
        'tenants' => [
            'index' => 50,
            'show' => 100,
            'config' => 20,
        ],
    ];

    /**
     * 租户专属限流配置（可覆盖默认配置）
     */
    protected array $tenantLimits = [
        // 示例：租户ID为1的专属配置
        // 1 => [
        //     'global' => 2000,
        //     'users' => ['index' => 200],
        // ],
    ];

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $limitType 限流类型（可选）
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $limitType = null): Response
    {
        // 获取租户ID
        $tenantId = $this->getTenantId($request);
        
        // 获取接口标识
        $endpoint = $this->getEndpoint($request);
        
        // 获取限流配置
        $maxAttempts = $this->getMaxAttempts($tenantId, $endpoint, $limitType);
        
        // 生成限流key
        $key = $this->getRateLimitKey($tenantId, $endpoint, $request);
        
        // 执行限流检查
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new BusinessException(
                __("messages.rate_limit_exceeded", ['seconds' => $seconds]),
                429
            );
        }
        
        // 增加计数
        RateLimiter::hit($key, 60); // 60秒窗口
        
        // 执行请求
        $response = $next($request);
        
        // 添加限流信息到响应头
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - RateLimiter::attempts($key)));
        $response->headers->set('X-RateLimit-Reset', 60);
        
        return $response;
    }

    /**
     * 获取租户ID
     *
     * @param Request $request
     * @return int|null
     */
    protected function getTenantId(Request $request): ?int
    {
        // 从JWT payload获取
        $payload = $request->attributes->get('jwt_payload');
        if ($payload && isset($payload['tenant_id'])) {
            return $payload['tenant_id'];
        }
        
        // 从请求参数获取
        if ($request->has('tenant_id')) {
            return $request->input('tenant_id');
        }
        
        // 默认租户
        return 1;
    }

    /**
     * 获取接口标识
     *
     * @param Request $request
     * @return string
     */
    protected function getEndpoint(Request $request): string
    {
        $path = $request->path();
        $method = strtolower($request->method());
        
        // 提取资源名称和操作
        // 例如：api/v1/users -> users
        // 例如：api/v1/users/1 -> users.show
        $segments = explode('/', $path);
        
        // 找到v1后的资源名
        $resourceIndex = array_search('v1', $segments);
        if ($resourceIndex !== false && isset($segments[$resourceIndex + 1])) {
            $resource = $segments[$resourceIndex + 1];
            
            // 判断操作类型
            if (isset($segments[$resourceIndex + 2]) && is_numeric($segments[$resourceIndex + 2])) {
                // 有ID参数
                if ($method === 'get') {
                    return "$resource.show";
                } elseif ($method === 'put' || $method === 'patch') {
                    return "$resource.update";
                } elseif ($method === 'delete') {
                    return "$resource.destroy";
                }
            } else {
                // 无ID参数
                if ($method === 'get') {
                    // 检查是否是特殊操作
                    if (isset($segments[$resourceIndex + 2])) {
                        return "$resource.{$segments[$resourceIndex + 2]}";
                    }
                    return "$resource.index";
                } elseif ($method === 'post') {
                    return "$resource.store";
                }
            }
            
            return $resource;
        }
        
        // 认证接口
        if (in_array('auth', $segments)) {
            $authIndex = array_search('auth', $segments);
            if (isset($segments[$authIndex + 1])) {
                return "auth.{$segments[$authIndex + 1]}";
            }
        }
        
        return 'global';
    }

    /**
     * 获取最大请求数
     *
     * @param int|null $tenantId
     * @param string $endpoint
     * @param string|null $limitType
     * @return int
     */
    protected function getMaxAttempts(?int $tenantId, string $endpoint, ?string $limitType = null): int
    {
        // 如果指定了限流类型，直接使用
        if ($limitType) {
            return $this->parseLimitType($limitType);
        }
        
        // 解析endpoint
        $parts = explode('.', $endpoint);
        $resource = $parts[0] ?? 'global';
        $action = $parts[1] ?? null;
        
        // 检查租户专属配置
        if ($tenantId && isset($this->tenantLimits[$tenantId])) {
            $tenantConfig = $this->tenantLimits[$tenantId];
            
            if ($action && isset($tenantConfig[$resource][$action])) {
                return $tenantConfig[$resource][$action];
            }
            
            if (isset($tenantConfig[$resource])) {
                return $tenantConfig[$resource];
            }
            
            if (isset($tenantConfig['global'])) {
                return $tenantConfig['global'];
            }
        }
        
        // 使用默认配置
        if ($action && isset($this->defaultLimits[$resource][$action])) {
            return $this->defaultLimits[$resource][$action];
        }
        
        if (isset($this->defaultLimits[$resource])) {
            if (is_array($this->defaultLimits[$resource])) {
                // 如果是数组但没有找到具体action，使用index作为默认
                return $this->defaultLimits[$resource]['index'] ?? $this->defaultLimits['global'];
            }
            return $this->defaultLimits[$resource];
        }
        
        return $this->defaultLimits['global'];
    }

    /**
     * 解析限流类型
     *
     * @param string $limitType
     * @return int
     */
    protected function parseLimitType(string $limitType): int
    {
        // 格式：10,1 表示每分钟10次
        $parts = explode(',', $limitType);
        return (int) ($parts[0] ?? 100);
    }

    /**
     * 生成限流key
     *
     * @param int|null $tenantId
     * @param string $endpoint
     * @param Request $request
     * @return string
     */
    protected function getRateLimitKey(?int $tenantId, string $endpoint, Request $request): string
    {
        // 基础key：租户ID + 接口
        $key = "rate_limit:tenant:{$tenantId}:endpoint:{$endpoint}";
        
        // 对于敏感操作，加上用户ID
        $sensitiveEndpoints = ['auth.login', 'auth.password', 'users.destroy'];
        if (in_array($endpoint, $sensitiveEndpoints)) {
            $payload = $request->attributes->get('jwt_payload');
            $userId = $payload['user_id'] ?? $request->ip();
            $key .= ":user:{$userId}";
        }
        
        return $key;
    }

    /**
     * 设置租户限流配置
     *
     * @param int $tenantId
     * @param array $config
     * @return void
     */
    public function setTenantLimits(int $tenantId, array $config): void
    {
        $this->tenantLimits[$tenantId] = $config;
    }

    /**
     * 获取租户限流配置
     *
     * @param int $tenantId
     * @return array
     */
    public function getTenantLimitsConfig(int $tenantId): array
    {
        return $this->tenantLimits[$tenantId] ?? [];
    }
}
