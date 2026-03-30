<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Exceptions\BusinessException;

/**
 * 租户服务
 * 
 * 处理租户相关的业务逻辑
 * 包括租户创建、配置管理、状态控制等
 */
class TenantService extends BaseService
{
    protected string $modelClass = Tenant::class;

    /**
     * 创建租户
     *
     * @param array $data 租户数据
     * @return Tenant
     * @throws BusinessException
     */
    public function createTenant(array $data): Tenant
    {
        // 验证租户编码唯一性
        if (Tenant::where('code', $data['code'])->exists()) {
            throw new BusinessException('租户编码已存在');
        }

        // 验证域名唯一性
        if (!empty($data['domain']) && Tenant::where('domain', $data['domain'])->exists()) {
            throw new BusinessException('租户域名已存在');
        }

        DB::beginTransaction();
        try {
            // 创建租户
            $tenant = Tenant::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'domain' => $data['domain'] ?? null,
                'logo' => $data['logo'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'address' => $data['address'] ?? null,
                'config' => $data['config'] ?? [],
                'expire_at' => $data['expire_at'] ?? null,
                'status' => $data['status'] ?? Tenant::STATUS_ENABLED,
                'created_by' => auth()->id() ?? 0,
                'updated_by' => auth()->id() ?? 0,
            ]);

            // 初始化租户默认数据
            $this->initTenantData($tenant);

            DB::commit();
            return $tenant;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException('创建租户失败: ' . $e->getMessage());
        }
    }

    /**
     * 初始化租户默认数据
     *
     * @param Tenant $tenant
     * @return void
     */
    protected function initTenantData(Tenant $tenant): void
    {
        // 初始化默认角色
        $roleService = app(RoleService::class);
        $roleService->initDefaultRoles($tenant->id);

        // 初始化默认部门
        $departmentService = app(DepartmentService::class);
        $departmentService->initDefaultDepartment($tenant->id);

        // 初始化默认菜单
        $menuService = app(MenuService::class);
        $menuService->initDefaultMenus($tenant->id);
    }

    /**
     * 更新租户
     *
     * @param int $id 租户ID
     * @param array $data 更新数据
     * @return Tenant
     * @throws BusinessException
     */
    public function updateTenant(int $id, array $data): Tenant
    {
        $tenant = Tenant::findOrFail($id);

        // 验证租户编码唯一性
        if (isset($data['code']) && $data['code'] !== $tenant->code) {
            if (Tenant::where('code', $data['code'])->exists()) {
                throw new BusinessException('租户编码已存在');
            }
        }

        // 验证域名唯一性
        if (isset($data['domain']) && $data['domain'] !== $tenant->domain) {
            if (!empty($data['domain']) && Tenant::where('domain', $data['domain'])->exists()) {
                throw new BusinessException('租户域名已存在');
            }
        }

        $data['updated_by'] = auth()->id() ?? 0;
        $tenant->update($data);

        return $tenant;
    }

    /**
     * 删除租户
     *
     * @param int $id 租户ID
     * @return bool
     * @throws BusinessException
     */
    public function deleteTenant(int $id): bool
    {
        $tenant = Tenant::findOrFail($id);

        // 检查是否有用户
        if ($tenant->users()->exists()) {
            throw new BusinessException('租户下存在用户，无法删除');
        }

        return $tenant->delete();
    }

    /**
     * 启用租户
     *
     * @param int $id 租户ID
     * @return Tenant
     */
    public function enableTenant(int $id): Tenant
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'status' => Tenant::STATUS_ENABLED,
            'updated_by' => auth()->id() ?? 0,
        ]);

        return $tenant;
    }

    /**
     * 禁用租户
     *
     * @param int $id 租户ID
     * @return Tenant
     */
    public function disableTenant(int $id): Tenant
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'status' => Tenant::STATUS_DISABLED,
            'updated_by' => auth()->id() ?? 0,
        ]);

        return $tenant;
    }

    /**
     * 根据域名获取租户
     *
     * @param string $domain
     * @return Tenant|null
     */
    public function getTenantByDomain(string $domain): ?Tenant
    {
        return Tenant::byDomain($domain)->enabled()->first();
    }

    /**
     * 根据编码获取租户
     *
     * @param string $code
     * @return Tenant|null
     */
    public function getTenantByCode(string $code): ?Tenant
    {
        return Tenant::byCode($code)->enabled()->first();
    }

    /**
     * 获取租户配置
     *
     * @param int $tenantId
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(int $tenantId, ?string $key = null, $default = null)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return $tenant->getConfig($key, $default);
    }

    /**
     * 设置租户配置
     *
     * @param int $tenantId
     * @param string|array $key
     * @param mixed $value
     * @return void
     */
    public function setConfig(int $tenantId, $key, $value = null): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $tenant->setConfig($key, $value);
        $tenant->updated_by = auth()->id() ?? 0;
        $tenant->save();
    }

    /**
     * 获取租户列表
     *
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTenantList(array $filters = [], int $page = 1, int $pageSize = 20)
    {
        $query = Tenant::query();

        // 状态筛选
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 名称搜索
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        // 编码搜索
        if (!empty($filters['code'])) {
            $query->where('code', 'like', "%{$filters['code']}%");
        }

        // 域名搜索
        if (!empty($filters['domain'])) {
            $query->where('domain', 'like', "%{$filters['domain']}%");
        }

        // 过期时间范围
        if (!empty($filters['expire_start'])) {
            $query->where('expire_at', '>=', $filters['expire_start']);
        }
        if (!empty($filters['expire_end'])) {
            $query->where('expire_at', '<=', $filters['expire_end']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * 检查租户权限
     *
     * @param int $tenantId
     * @return bool
     */
    public function checkTenantAccess(int $tenantId): bool
    {
        $currentTenantId = config('saas.current_tenant_id');
        
        // 超级管理员可以访问所有租户
        if (config('saas.is_super_admin', false)) {
            return true;
        }

        return $currentTenantId === $tenantId;
    }
}
