<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuth
{
    /**
     * JWT服务
     *
     * @var JwtService
     */
    protected JwtService $jwtService;

    /**
     * 构造函数
     *
     * @param JwtService $jwtService
     */
    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * 处理请求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');

        if (!$token) {
            return response()->json([
                'code' => 401,
                'message' => '未提供访问令牌',
                'data' => null,
            ], 401);
        }

        // 移除 Bearer 前缀
        $token = str_replace('Bearer ', '', $token);

        try {
            // 验证 token
            $payload = $this->jwtService->verify($token);

            // 设置用户信息
            $request->merge([
                'user_id' => $payload['user_id'],
                'tenant_id' => $payload['tenant_id'],
                'device_id' => $payload['device_id'] ?? null,
            ]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 401,
                'message' => $e->getMessage(),
                'data' => null,
            ], 401);
        }
    }
}
