<?php

namespace App\Services;

use App\Models\Department;
use App\Exceptions\BusinessException;

/**
 * 部门服务
 */
class DepartmentService extends BaseService
{
    protected string $modelClass = Department::class;

    /**
     * 初始化默认部门
     *
     * @param int $tenantId
     * @return void
     */
    public function initDefaultDepartment(int $tenantId): void
    {
        Department::create([
            'tenant_id' => $tenantId,
            'name' => '总公司',
            'code' => 'HQ',
            'parent_id' => 0,
            'sort' => 1,
            'status' => Department::STATUS_ENABLED,
        ]);
    }

    /**
     * 获取部门树
     *
     * @param int|null $tenantId
     * @return array
     */
    public function getDepartmentTree(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? config('saas.current_tenant_id');
        
        $departments = Department::where('tenant_id', $tenantId)
            ->enabled()
            ->orderBy('sort')
            ->get();

        return $this->buildTree($departments);
    }

    /**
     * 构建部门树
     *
     * @param $departments
     * @param int $parentId
     * @return array
     */
    protected function buildTree($departments, int $parentId = 0): array
    {
        $tree = [];
        
        foreach ($departments as $dept) {
            if ($dept->parent_id === $parentId) {
                $children = $this->buildTree($departments, $dept->id);
                
                $item = $dept->toArray();
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                
                $tree[] = $item;
            }
        }
        
        return $tree;
    }

    /**
     * 创建部门
     *
     * @param array $data
     * @return Department
     */
    public function createDepartment(array $data): Department
    {
        $tenantId = config('saas.current_tenant_id');
        
        return Department::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'code' => $data['code'],
            'parent_id' => $data['parent_id'] ?? 0,
            'leader' => $data['leader'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'status' => $data['status'] ?? Department::STATUS_ENABLED,
            'created_by' => auth()->id() ?? 0,
            'updated_by' => auth()->id() ?? 0,
        ]);
    }

    /**
     * 更新部门
     *
     * @param int $id
     * @param array $data
     * @return Department
     */
    public function updateDepartment(int $id, array $data): Department
    {
        $department = Department::findOrFail($id);
        
        $data['updated_by'] = auth()->id() ?? 0;
        $department->update($data);
        
        return $department;
    }

    /**
     * 删除部门
     *
     * @param int $id
     * @return bool
     * @throws BusinessException
     */
    public function deleteDepartment(int $id): bool
    {
        $department = Department::findOrFail($id);
        
        // 检查是否有子部门
        if ($department->children()->exists()) {
            throw new BusinessException('存在子部门，无法删除');
        }
        
        // 检查是否有用户
        if ($department->users()->exists()) {
            throw new BusinessException('部门下存在用户，无法删除');
        }
        
        return $department->delete();
    }
}
