<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\TokenService;
use App\Models\TokenBlacklist;
use App\Exceptions\BusinessException;

/**
 * JWT认证中间件
 * 
 * 验证JWT Token并处理黑名单检查
 */
class JwtMiddleware
{
    /**
     * TokenService实例
     *
     * @var TokenService
     */
    protected TokenService $tokenService;

    /**
     * 跳过JWT验证的路由
     *
     * @var array
     */
    protected array $except = [
        'api/v1/auth/login',
        'api/v1/auth/refresh',
        'api/health',
    ];

    /**
     * 构造函数
     */
    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 检查是否跳过验证
        if ($this->shouldPassThrough($request)) {
            return $next($request);
        }

        // 获取Token
        $token = $this->getToken($request);

        if (!$token) {
            \Log::error('JWT: No token provided');
            throw new BusinessException(__('messages.unauthorized'), 401);
        }

        // 验证Token
        try {
            $payload = $this->tokenService->validateToken($token, TokenService::TYPE_ACCESS);
        } catch (\Exception $e) {
            \Log::error('JWT: Token validation failed', ['error' => $e->getMessage(), 'token' => substr($token, 0, 50)]);
            throw new BusinessException('Invalid token: ' . $e->getMessage(), 401);
        }

        if (!$payload) {
            \Log::error('JWT: Invalid token payload', ['token' => substr($token, 0, 50)]);
            throw new BusinessException(__('messages.token_invalid'), 401);
        }

        // 将payload存储到请求中
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('jwt_token', $token);

        return $next($request);
    }

    /**
     * 判断是否跳过验证
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldPassThrough(Request $request): bool
    {
        foreach ($this->except as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 从请求中获取Token
     *
     * @param Request $request
     * @return string|null
     */
    protected function getToken(Request $request): ?string
    {
        // 从Authorization header获取
        $header = $request->header('Authorization', '');
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        // 从查询参数获取
        return $request->query('token');
    }
}
