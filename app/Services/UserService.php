<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Exceptions\BusinessException;

/**
 * 用户服务
 */
class UserService extends BaseService
{
    protected string $modelClass = User::class;

    /**
     * 创建用户
     *
     * @param array $data
     * @return User
     * @throws BusinessException
     */
    public function createUser(array $data): User
    {
        $tenantId = config('saas.current_tenant_id');

        // 验证用户名唯一性
        if (User::where('tenant_id', $tenantId)->where('username', $data['username'])->exists()) {
            throw new BusinessException('用户名已存在');
        }

        // 验证邮箱唯一性
        if (!empty($data['email']) && User::where('tenant_id', $tenantId)->where('email', $data['email'])->exists()) {
            throw new BusinessException('邮箱已存在');
        }

        // 验证手机号唯一性
        if (!empty($data['phone']) && User::where('tenant_id', $tenantId)->where('phone', $data['phone'])->exists()) {
            throw new BusinessException('手机号已存在');
        }

        DB::beginTransaction();
        try {
            // 创建用户
            $user = new User();
            $user->tenant_id = $tenantId;
            $user->username = $data['username'];
            $user->email = $data['email'] ?? null;
            $user->phone = $data['phone'] ?? null;
            $user->password = $data['password'];
            $user->nickname = $data['nickname'] ?? $data['username'];
            $user->avatar = $data['avatar'] ?? null;
            $user->gender = $data['gender'] ?? 0;
            $user->birthday = $data['birthday'] ?? null;
            $user->signature = $data['signature'] ?? null;
            $user->department_id = $data['department_id'] ?? 0;
            $user->position_id = $data['position_id'] ?? 0;
            $user->level_id = $data['level_id'] ?? 0;
            $user->status = $data['status'] ?? User::STATUS_ENABLED;
            $user->created_by = auth()->id() ?? 0;
            $user->updated_by = auth()->id() ?? 0;
            $user->save();

            // 分配角色
            if (!empty($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException('创建用户失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新用户
     *
     * @param int $id
     * @param array $data
     * @return User
     * @throws BusinessException
     */
    public function updateUser(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $tenantId = config('saas.current_tenant_id');

        // 验证用户名唯一性
        if (isset($data['username']) && $data['username'] !== $user->username) {
            if (User::where('tenant_id', $tenantId)->where('username', $data['username'])->exists()) {
                throw new BusinessException('用户名已存在');
            }
        }

        // 验证邮箱唯一性
        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (!empty($data['email']) && User::where('tenant_id', $tenantId)->where('email', $data['email'])->exists()) {
                throw new BusinessException('邮箱已存在');
            }
        }

        // 验证手机号唯一性
        if (isset($data['phone']) && $data['phone'] !== $user->phone) {
            if (!empty($data['phone']) && User::where('tenant_id', $tenantId)->where('phone', $data['phone'])->exists()) {
                throw new BusinessException('手机号已存在');
            }
        }

        DB::beginTransaction();
        try {
            $data['updated_by'] = auth()->id() ?? 0;
            $user->update($data);

            // 更新角色
            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new BusinessException('更新用户失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除用户
     *
     * @param int $id
     * @return bool
     * @throws BusinessException
     */
    public function deleteUser(int $id): bool
    {
        $user = User::findOrFail($id);

        // 不能删除自己
        if ($user->id === auth()->id()) {
            throw new BusinessException('不能删除自己');
        }

        return $user->delete();
    }

    /**
     * 获取用户列表
     *
     * @param array $filters
     * @param int $page
     * @param int $pageSize
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserList(array $filters = [], int $page = 1, int $pageSize = 20)
    {
        $tenantId = config('saas.current_tenant_id');
        
        $query = User::where('tenant_id', $tenantId);

        // 状态筛选
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 部门筛选
        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // 关键词搜索
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('nickname', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        return $query->with(['roles', 'department', 'position', 'level'])
            ->orderBy('created_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * 启用用户
     *
     * @param int $id
     * @return User
     */
    public function enableUser(int $id): User
    {
        $user = User::findOrFail($id);
        $user->update([
            'status' => User::STATUS_ENABLED,
            'updated_by' => auth()->id() ?? 0,
        ]);

        return $user;
    }

    /**
     * 禁用用户
     *
     * @param int $id
     * @return User
     */
    public function disableUser(int $id): User
    {
        $user = User::findOrFail($id);
        $user->update([
            'status' => User::STATUS_DISABLED,
            'updated_by' => auth()->id() ?? 0,
        ]);

        return $user;
    }

    /**
     * 分配角色
     *
     * @param int $userId
     * @param array $roleIds
     * @return void
     */
    public function assignRoles(int $userId, array $roleIds): void
    {
        $user = User::findOrFail($userId);
        $user->roles()->sync($roleIds);
    }
}
