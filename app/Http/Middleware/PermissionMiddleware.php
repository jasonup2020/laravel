<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\BusinessException;

/**
 * 权限中间件
 * 
 * 检查用户是否有指定权限
 */
class PermissionMiddleware
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string $permission 权限标识
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // 从JWT payload获取用户ID
        $payload = $request->attributes->get('jwt_payload');
        
        if (!$payload || !isset($payload['user_id'])) {
            throw new BusinessException(__('messages.unauthorized'), 401);
        }

        $user = \App\Models\User::find($payload['user_id']);
        
        if (!$user) {
            throw new BusinessException(__('messages.unauthorized'), 401);
        }

        // 超级管理员跳过权限检查
        if ($user->roles()->where('slug', 'super_admin')->exists()) {
            return $next($request);
        }

        // 检查权限
        if (!$user->hasPermission($permission)) {
            throw new BusinessException(__('messages.permission_denied'), 403);
        }

        return $next($request);
    }
}
