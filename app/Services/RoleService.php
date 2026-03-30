<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * 角色服务
 */
class RoleService extends BaseService
{
    protected string $modelClass = Role::class;

    /**
     * 初始化默认角色
     *
     * @param int $tenantId
     * @return void
     */
    public function initDefaultRoles(int $tenantId): void
    {
        $roles = [
            [
                'tenant_id' => $tenantId,
                'name' => '超级管理员',
                'code' => 'super_admin',
                'description' => '拥有所有权限',
                'is_system' => 1,
                'sort' => 1,
                'status' => Role::STATUS_ENABLED,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => '普通用户',
                'code' => 'user',
                'description' => '普通用户权限',
                'is_system' => 1,
                'sort' => 2,
                'status' => Role::STATUS_ENABLED,
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }

    /**
     * 创建角色
     *
     * @param array $data
     * @return Role
     * @throws BusinessException
     */
    public function createRole(array $data): Role
    {
        $tenantId = config('saas.current_tenant_id');

        // 验证角色编码唯一性
        if (Role::where('tenant_id', $tenantId)->where('code', $data['code'])->exists()) {
            throw new BusinessException('角色编码已存在');
        }

        DB::beginTransaction();
        try {
            $role = Role::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'is_system' => 0,
                'sort' => $data['sort'] ?? 0,
                'status' => $data['status'] ?? Role::STATUS_ENABLED,
                'created_by' => auth()->id() ?? 0,
                'updated_by' => auth()->id() ?? 0,
            ]);

            // 分配权限
            if (!empty($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            DB::commit();
            return $role;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException('创建角色失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新角色
     *
     * @param int $id
     * @param array $data
     * @return Role
     * @throws BusinessException
     */
    public function updateRole(int $id, array $data): Role
    {
        $role = Role::findOrFail($id);

        // 系统角色不能修改
        if ($role->is_system) {
            throw new BusinessException('系统角色不能修改');
        }

        $tenantId = config('saas.current_tenant_id');

        // 验证角色编码唯一性
        if (isset($data['code']) && $data['code'] !== $role->code) {
            if (Role::where('tenant_id', $tenantId)->where('code', $data['code'])->exists()) {
                throw new BusinessException('角色编码已存在');
            }
        }

        DB::beginTransaction();
        try {
            $data['updated_by'] = auth()->id() ?? 0;
            $role->update($data);

            // 更新权限
            if (isset($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            DB::commit();
            return $role;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException('更新角色失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除角色
     *
     * @param int $id
     * @return bool
     * @throws BusinessException
     */
    public function deleteRole(int $id): bool
    {
        $role = Role::findOrFail($id);

        // 系统角色不能删除
        if ($role->is_system) {
            throw new BusinessException('系统角色不能删除');
        }

        // 检查是否有用户使用该角色
        if ($role->users()->exists()) {
            throw new BusinessException('角色下存在用户，无法删除');
        }

        return $role->delete();
    }

    /**
     * 获取角色列表
     *
     * @param array $filters
     * @param int $page
     * @param int $pageSize
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getRoleList(array $filters = [], int $page = 1, int $pageSize = 20)
    {
        $tenantId = config('saas.current_tenant_id');
        
        $query = Role::where('tenant_id', $tenantId);

        // 状态筛选
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 关键词搜索
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        return $query->with('permissions')
            ->orderBy('sort')
            ->orderBy('created_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * 分配权限
     *
     * @param int $roleId
     * @param array $permissionIds
     * @return Role
     * @throws BusinessException
     */
    public function assignPermissions(int $roleId, array $permissionIds): Role
    {
        $role = Role::findOrFail($roleId);

        // 系统角色不能修改权限
        if ($role->is_system) {
            throw new BusinessException('系统角色不能修改权限');
        }

        $role->permissions()->sync($permissionIds);
        
        return $role->load('permissions');
    }

    /**
     * 批量删除
     *
     * @param array $ids
     * @return int
     * @throws BusinessException
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;
        
        foreach ($ids as $id) {
            try {
                if ($this->deleteRole($id)) {
                    $count++;
                }
            } catch (BusinessException $e) {
                // 忽略错误，继续删除其他角色
            }
        }
        
        return $count;
    }
}
