<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use App\Exceptions\BusinessException;

/**
 * 租户识别中间件
 */
class IdentifyTenant
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws BusinessException
     */
    public function handle(Request $request, Closure $next)
    {
        // 如果已经通过JWT中间件设置了租户ID，直接使用
        if ($request->has('current_tenant_id')) {
            $tenantId = $request->get('current_tenant_id');
            $tenant = Tenant::find($tenantId);
        } else {
            // 否则通过域名或租户编码识别
            $tenant = $this->identifyTenant($request);
        }

        if (!$tenant) {
            throw new BusinessException('租户不存在', 404);
        }

        if (!$tenant->isEnabled()) {
            throw new BusinessException('租户已被禁用或已过期', 403);
        }

        // 设置当前租户到配置
        config(['saas.current_tenant_id' => $tenant->id]);
        config(['saas.current_tenant' => $tenant]);

        // 将租户信息存入请求
        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }

    /**
     * 识别租户
     *
     * @param Request $request
     * @return Tenant|null
     */
    protected function identifyTenant(Request $request): ?Tenant
    {
        // 1. 从请求头获取租户编码
        $tenantCode = $request->header('X-Tenant-Code');
        if ($tenantCode) {
            return $this->tenantService->getTenantByCode($tenantCode);
        }

        // 2. 从域名识别
        $domain = $request->getHost();
        if ($domain && $domain !== config('app.url')) {
            $tenant = $this->tenantService->getTenantByDomain($domain);
            if ($tenant) {
                return $tenant;
            }
        }

        // 3. 从查询参数获取租户编码
        $tenantCode = $request->query('tenant_code');
        if ($tenantCode) {
            return $this->tenantService->getTenantByCode($tenantCode);
        }

        // 4. 返回默认租户（如果配置了）
        $defaultTenantId = config('saas.default_tenant_id');
        if ($defaultTenantId) {
            return Tenant::find($defaultTenantId);
        }

        return null;
    }
}
