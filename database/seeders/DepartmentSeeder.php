<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * 部门数据填充
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['id' => 1, 'tenant_id' => 1, 'name' => '总公司', 'code' => 'HQ', 'parent_id' => 0, 'sort' => 1],
            ['id' => 2, 'tenant_id' => 1, 'name' => '技术部', 'code' => 'TECH', 'parent_id' => 1, 'sort' => 1],
            ['id' => 3, 'tenant_id' => 1, 'name' => '产品部', 'code' => 'PRODUCT', 'parent_id' => 1, 'sort' => 2],
            ['id' => 4, 'tenant_id' => 1, 'name' => '运营部', 'code' => 'OPERATION', 'parent_id' => 1, 'sort' => 3],
            ['id' => 5, 'tenant_id' => 1, 'name' => '市场部', 'code' => 'MARKET', 'parent_id' => 1, 'sort' => 4],
            ['id' => 6, 'tenant_id' => 1, 'name' => '人事部', 'code' => 'HR', 'parent_id' => 1, 'sort' => 5],
            ['id' => 7, 'tenant_id' => 1, 'name' => '财务部', 'code' => 'FINANCE', 'parent_id' => 1, 'sort' => 6],
        ];

        foreach ($departments as $dept) {
            Department::create(array_merge($dept, ['status' => Department::STATUS_ENABLED]));
        }
    }
}
