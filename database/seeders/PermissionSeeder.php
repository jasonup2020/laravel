<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * 权限数据填充
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // 用户管理
            ['id' => 1, 'name' => '用户列表', 'slug' => 'user-index', 'module' => 'user', 'controller' => 'UserController', 'action' => 'index'],
            ['id' => 2, 'name' => '用户创建', 'slug' => 'user-store', 'module' => 'user', 'controller' => 'UserController', 'action' => 'store'],
            ['id' => 3, 'name' => '用户编辑', 'slug' => 'user-update', 'module' => 'user', 'controller' => 'UserController', 'action' => 'update'],
            ['id' => 4, 'name' => '用户删除', 'slug' => 'user-destroy', 'module' => 'user', 'controller' => 'UserController', 'action' => 'destroy'],
            
            // 角色管理
            ['id' => 10, 'name' => '角色列表', 'slug' => 'role-index', 'module' => 'role', 'controller' => 'RoleController', 'action' => 'index'],
            ['id' => 11, 'name' => '角色创建', 'slug' => 'role-store', 'module' => 'role', 'controller' => 'RoleController', 'action' => 'store'],
            ['id' => 12, 'name' => '角色编辑', 'slug' => 'role-update', 'module' => 'role', 'controller' => 'RoleController', 'action' => 'update'],
            ['id' => 13, 'name' => '角色删除', 'slug' => 'role-destroy', 'module' => 'role', 'controller' => 'RoleController', 'action' => 'destroy'],
            
            // 权限管理
            ['id' => 20, 'name' => '权限列表', 'slug' => 'permission-index', 'module' => 'permission', 'controller' => 'PermissionController', 'action' => 'index'],
            ['id' => 21, 'name' => '权限创建', 'slug' => 'permission-store', 'module' => 'permission', 'controller' => 'PermissionController', 'action' => 'store'],
            ['id' => 22, 'name' => '权限编辑', 'slug' => 'permission-update', 'module' => 'permission', 'controller' => 'PermissionController', 'action' => 'update'],
            ['id' => 23, 'name' => '权限删除', 'slug' => 'permission-destroy', 'module' => 'permission', 'controller' => 'PermissionController', 'action' => 'destroy'],
            
            // 部门管理
            ['id' => 30, 'name' => '部门列表', 'slug' => 'department-index', 'module' => 'department', 'controller' => 'DepartmentController', 'action' => 'index'],
            ['id' => 31, 'name' => '部门创建', 'slug' => 'department-store', 'module' => 'department', 'controller' => 'DepartmentController', 'action' => 'store'],
            ['id' => 32, 'name' => '部门编辑', 'slug' => 'department-update', 'module' => 'department', 'controller' => 'DepartmentController', 'action' => 'update'],
            ['id' => 33, 'name' => '部门删除', 'slug' => 'department-destroy', 'module' => 'department', 'controller' => 'DepartmentController', 'action' => 'destroy'],
            
            // 岗位管理
            ['id' => 40, 'name' => '岗位列表', 'slug' => 'position-index', 'module' => 'position', 'controller' => 'PositionController', 'action' => 'index'],
            ['id' => 41, 'name' => '岗位创建', 'slug' => 'position-store', 'module' => 'position', 'controller' => 'PositionController', 'action' => 'store'],
            ['id' => 42, 'name' => '岗位编辑', 'slug' => 'position-update', 'module' => 'position', 'controller' => 'PositionController', 'action' => 'update'],
            ['id' => 43, 'name' => '岗位删除', 'slug' => 'position-destroy', 'module' => 'position', 'controller' => 'PositionController', 'action' => 'destroy'],
            
            // 租户管理
            ['id' => 50, 'name' => '租户列表', 'slug' => 'tenant-index', 'module' => 'tenant', 'controller' => 'TenantController', 'action' => 'index'],
            ['id' => 51, 'name' => '租户创建', 'slug' => 'tenant-store', 'module' => 'tenant', 'controller' => 'TenantController', 'action' => 'store'],
            ['id' => 52, 'name' => '租户编辑', 'slug' => 'tenant-update', 'module' => 'tenant', 'controller' => 'TenantController', 'action' => 'update'],
            ['id' => 53, 'name' => '租户删除', 'slug' => 'tenant-destroy', 'module' => 'tenant', 'controller' => 'TenantController', 'action' => 'destroy'],
        ];

        foreach ($permissions as $permission) {
            Permission::create(array_merge($permission, ['status' => Permission::STATUS_ENABLED]));
        }

        // 为超管角色分配所有权限
        $role = \App\Models\Role::find(1);
        if ($role) {
            $role->permissions()->sync(Permission::pluck('id')->toArray());
        }
    }
}
