<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use App\Exceptions\BusinessException;

/**
 * 租户中间件
 * 
 * 识别并设置当前租户
 */
class TenantMiddleware
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 获取租户ID（从域名、header或参数）
        $tenantId = $this->getTenantId($request);

        if ($tenantId) {
            // 验证租户是否存在且启用
            $tenant = Tenant::where('id', $tenantId)
                ->where('status', 1)
                ->first();

            if (!$tenant) {
                throw new BusinessException(__('messages.tenant_not_found'), 404);
            }

            // 检查租户是否过期
            if ($tenant->isExpired()) {
                throw new BusinessException(__('messages.tenant_expired'), 403);
            }

            // 设置当前租户
            tenant_id($tenantId);
            tenant($tenant);
        }

        return $next($request);
    }

    /**
     * 获取租户ID
     *
     * @param Request $request
     * @return int|null
     */
    protected function getTenantId(Request $request): ?int
    {
        // 1. 从header获取
        $tenantId = $request->header('X-Tenant-Id');
        
        if ($tenantId) {
            return (int) $tenantId;
        }

        // 2. 从域名获取
        $host = $request->getHost();
        $tenant = Tenant::where('domain', $host)->first();
        
        if ($tenant) {
            return $tenant->id;
        }

        // 3. 从参数获取
        $tenantId = $request->input('tenant_id');
        
        if ($tenantId) {
            return (int) $tenantId;
        }

        return null;
    }
}
