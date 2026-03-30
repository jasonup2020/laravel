<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\BusinessException;

/**
 * 请求限流中间件
 * 
 * 基于IP和用户ID的请求限流
 */
class RateLimitMiddleware
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param int $maxAttempts 最大尝试次数
     * @param int $decayMinutes 时间窗口（分钟）
     * @return Response
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            throw new BusinessException(
                __('messages.too_many_requests'),
                429
            );
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        // 添加限流头信息
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));

        return $response;
    }

    /**
     * 生成请求签名
     *
     * @param Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = auth()->id() ?? 'guest';
        $ip = $request->ip();
        $route = $request->route()?->getName() ?? $request->path();

        return sha1("rate_limit:{$userId}:{$ip}:{$route}");
    }
}
