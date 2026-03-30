<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 菜单数据填充
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            // 系统管理
            ['id' => 1, 'tenant_id' => 1, 'name' => '系统管理', 'slug' => 'system', 'parent_id' => null, 'path' => '/system', 'icon' => 'setting', 'sort' => 1],
            ['id' => 2, 'tenant_id' => 1, 'name' => '用户管理', 'slug' => 'system-user', 'parent_id' => 1, 'path' => '/system/user', 'component' => 'system/user/index', 'icon' => 'user', 'sort' => 1],
            ['id' => 3, 'tenant_id' => 1, 'name' => '角色管理', 'slug' => 'system-role', 'parent_id' => 1, 'path' => '/system/role', 'component' => 'system/role/index', 'icon' => 'peoples', 'sort' => 2],
            ['id' => 4, 'tenant_id' => 1, 'name' => '权限管理', 'slug' => 'system-permission', 'parent_id' => 1, 'path' => '/system/permission', 'component' => 'system/permission/index', 'icon' => 'lock', 'sort' => 3],
            ['id' => 5, 'tenant_id' => 1, 'name' => '菜单管理', 'slug' => 'system-menu', 'parent_id' => 1, 'path' => '/system/menu', 'component' => 'system/menu/index', 'icon' => 'tree-table', 'sort' => 4],
            
            // 组织架构
            ['id' => 10, 'tenant_id' => 1, 'name' => '组织架构', 'slug' => 'organization', 'parent_id' => null, 'path' => '/organization', 'icon' => 'tree', 'sort' => 2],
            ['id' => 11, 'tenant_id' => 1, 'name' => '部门管理', 'slug' => 'organization-department', 'parent_id' => 10, 'path' => '/organization/department', 'component' => 'organization/department/index', 'icon' => 'tree', 'sort' => 1],
            ['id' => 12, 'tenant_id' => 1, 'name' => '岗位管理', 'slug' => 'organization-position', 'parent_id' => 10, 'path' => '/organization/position', 'component' => 'organization/position/index', 'icon' => 'post', 'sort' => 2],
            ['id' => 13, 'tenant_id' => 1, 'name' => '职级管理', 'slug' => 'organization-level', 'parent_id' => 10, 'path' => '/organization/level', 'component' => 'organization/level/index', 'icon' => 'level', 'sort' => 3],
            
            // 租户管理
            ['id' => 20, 'tenant_id' => 1, 'name' => '租户管理', 'slug' => 'tenant', 'parent_id' => null, 'path' => '/tenant', 'icon' => 'international', 'sort' => 3],
            ['id' => 21, 'tenant_id' => 1, 'name' => '租户列表', 'slug' => 'tenant-index', 'parent_id' => 20, 'path' => '/tenant/index', 'component' => 'tenant/index', 'icon' => 'list', 'sort' => 1],
            
            // 个人中心
            ['id' => 30, 'tenant_id' => 1, 'name' => '个人中心', 'slug' => 'profile', 'parent_id' => null, 'path' => '/profile', 'component' => 'profile/index', 'icon' => 'user', 'sort' => 99, 'is_hidden' => true],
        ];

        foreach ($menus as $menu) {
            Menu::create(array_merge($menu, [
                'status' => Menu::STATUS_ENABLED,
                'is_hidden' => $menu['is_hidden'] ?? false,
                'is_cached' => true,
                'is_external' => false,
            ]));
        }
    }
}
