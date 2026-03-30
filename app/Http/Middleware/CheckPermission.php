<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use App\Exceptions\BusinessException;

/**
 * 权限检查中间件
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $permission
     * @return mixed
     * @throws BusinessException
     */
    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $user = auth()->user();

        if (!$user) {
            throw new BusinessException('未认证', 401);
        }

        // 超级管理员跳过权限检查
        if ($this->isSuperAdmin($user)) {
            return $next($request);
        }

        // 如果没有指定权限，从路由获取
        if (!$permission) {
            $permission = $this->getPermissionFromRoute($request);
        }

        // 如果还是没有权限，放行（由控制器自行处理）
        if (!$permission) {
            return $next($request);
        }

        // 检查用户是否有该权限
        if (!$user->hasPermission($permission)) {
            throw new BusinessException('没有操作权限', 403);
        }

        return $next($request);
    }

    /**
     * 检查是否是超级管理员
     *
     * @param User $user
     * @return bool
     */
    protected function isSuperAdmin(User $user): bool
    {
        // 检查用户是否有超管角色
        return $user->hasRole('super_admin') || $user->hasRole('admin');
    }

    /**
     * 从路由获取权限标识
     *
     * @param Request $request
     * @return string|null
     */
    protected function getPermissionFromRoute(Request $request): ?string
    {
        $route = $request->route();
        
        if (!$route) {
            return null;
        }

        // 从路由名称获取
        $routeName = $route->getName();
        if ($routeName) {
            return $routeName;
        }

        // 从控制器方法构建权限标识
        $action = $route->getActionName();
        if ($action && $action !== 'Closure') {
            // 格式：Controller@method -> controller.method
            $parts = explode('@', $action);
            if (count($parts) === 2) {
                $controller = class_basename($parts[0]);
                $method = $parts[1];
                return strtolower(str_replace('Controller', '', $controller)) . '.' . $method;
            }
        }

        return null;
    }
}
