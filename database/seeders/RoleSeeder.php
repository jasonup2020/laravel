<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * 角色数据填充
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'tenant_id' => 1,
                'name' => '超级管理员',
                'slug' => 'super_admin',
                'description' => '拥有所有权限',
                'status' => Role::STATUS_ENABLED,
            ],
            [
                'id' => 2,
                'tenant_id' => 1,
                'name' => '普通用户',
                'slug' => 'user',
                'description' => '普通用户权限',
                'status' => Role::STATUS_ENABLED,
            ],
            [
                'id' => 3,
                'tenant_id' => 1,
                'name' => '部门管理员',
                'slug' => 'dept_admin',
                'description' => '部门管理权限',
                'status' => Role::STATUS_ENABLED,
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
