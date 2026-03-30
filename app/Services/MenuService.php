<?php

namespace App\Services;

use App\Models\Menu;
use App\Exceptions\BusinessException;

/**
 * 菜单服务
 */
class MenuService extends BaseService
{
    protected string $modelClass = Menu::class;

    /**
     * 获取用户菜单
     *
     * @param $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserMenus($user)
    {
        if (!$user) {
            return collect([]);
        }
        
        // 简化：返回所有启用的菜单
        return Menu::where('tenant_id', $user->tenant_id)
            ->where('status', Menu::STATUS_ENABLED)
            ->orderBy('sort')
            ->get();
    }

    /**
     * 初始化默认菜单
     *
     * @param int $tenantId
     * @return void
     */
    public function initDefaultMenus(int $tenantId): void
    {
        $menus = [
            ['tenant_id' => $tenantId, 'name' => '系统管理', 'code' => 'system', 'parent_id' => 0, 'path' => '/system', 'icon' => 'setting', 'type' => 1, 'sort' => 1],
            ['tenant_id' => $tenantId, 'name' => '用户管理', 'code' => 'system.user', 'parent_id' => 0, 'path' => '/system/user', 'component' => 'system/user/index', 'icon' => 'user', 'type' => 2, 'sort' => 1],
        ];

        foreach ($menus as $menu) {
            Menu::create(array_merge($menu, ['status' => Menu::STATUS_ENABLED]));
        }
    }

    /**
     * 获取菜单树
     *
     * @param int|null $tenantId
     * @return array
     */
    public function getMenuTree(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? config('saas.current_tenant_id');
        
        $menus = Menu::where('tenant_id', $tenantId)
            ->enabled()
            ->visible()
            ->orderBy('sort')
            ->get();

        return $this->buildTree($menus);
    }

    /**
     * 构建菜单树
     *
     * @param $menus
     * @param int $parentId
     * @return array
     */
    protected function buildTree($menus, int $parentId = 0): array
    {
        $tree = [];
        
        foreach ($menus as $menu) {
            if ($menu->parent_id === $parentId) {
                $children = $this->buildTree($menus, $menu->id);
                
                $item = $menu->toArray();
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                
                $tree[] = $item;
            }
        }
        
        return $tree;
    }

    /**
     * 创建菜单
     *
     * @param array $data
     * @return Menu
     */
    public function createMenu(array $data): Menu
    {
        $tenantId = config('saas.current_tenant_id');
        
        return Menu::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'code' => $data['code'],
            'parent_id' => $data['parent_id'] ?? 0,
            'path' => $data['path'] ?? null,
            'component' => $data['component'] ?? null,
            'redirect' => $data['redirect'] ?? null,
            'icon' => $data['icon'] ?? null,
            'type' => $data['type'] ?? Menu::TYPE_MENU,
            'is_hidden' => $data['is_hidden'] ?? 0,
            'is_cache' => $data['is_cache'] ?? 0,
            'is_affix' => $data['is_affix'] ?? 0,
            'is_external' => $data['is_external'] ?? 0,
            'sort' => $data['sort'] ?? 0,
            'status' => $data['status'] ?? Menu::STATUS_ENABLED,
            'created_by' => auth()->id() ?? 0,
            'updated_by' => auth()->id() ?? 0,
        ]);
    }

    /**
     * 更新菜单
     *
     * @param int $id
     * @param array $data
     * @return Menu
     */
    public function updateMenu(int $id, array $data): Menu
    {
        $menu = Menu::findOrFail($id);
        
        $data['updated_by'] = auth()->id() ?? 0;
        $menu->update($data);
        
        return $menu;
    }

    /**
     * 删除菜单
     *
     * @param int $id
     * @return bool
     * @throws BusinessException
     */
    public function deleteMenu(int $id): bool
    {
        $menu = Menu::findOrFail($id);
        
        // 检查是否有子菜单
        if ($menu->children()->exists()) {
            throw new BusinessException('存在子菜单，无法删除');
        }
        
        return $menu->delete();
    }
}
