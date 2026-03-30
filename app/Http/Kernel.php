<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class, // 信任代理中间件
        \Illuminate\Http\Middleware\HandleCors::class, // 跨域资源共享中间件
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class, // 维护模式中间件
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class, // POST请求大小验证中间件
        \App\Http\Middleware\TrimStrings::class, // 字符串修剪中间件
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class, // 空字符串转null中间件
        \App\Http\Middleware\SanitizeInput::class, // XSS防御中间件
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class, // 加密cookie中间件
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class, // 队列cookie响应中间件
            \Illuminate\Session\Middleware\StartSession::class, // 会话开始中间件
            \Illuminate\View\Middleware\ShareErrorsFromSession::class, // 共享错误会话中间件
            \App\Http\Middleware\VerifyCsrfToken::class, // CSRF验证中间件
            \Illuminate\Routing\Middleware\SubstituteBindings::class, // 绑定替换中间件
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class, // Sanctum前端请求状态检查
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api', // API限流中间件
            \Illuminate\Routing\Middleware\SubstituteBindings::class, // 绑定替换中间件
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class, // 认证中间件
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class, // 基本认证中间件
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class, // 会话认证中间件
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,  // 缓存头设置中间件
        'can' => \Illuminate\Auth\Middleware\Authorize::class,  // 权限检查中间件
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,  // 重定向已认证用户中间件
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,  // 密码确认中间件
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,  // 预知请求处理中间件
        'signed' => \App\Http\Middleware\ValidateSignature::class,  // 签名验证中间件
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,  // 通用限流中间件
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,  // 邮箱验证中间件
        
        // 自定义中间件
        'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class, // JWT认证中间件
        'tenant.identify' => \App\Http\Middleware\TenantMiddleware::class, // 租户识别中间件
        'permission' => \App\Http\Middleware\PermissionMiddleware::class, // 权限检查中间件
        'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class, // 速率限制中间件
        'tenant.ratelimit' => \App\Http\Middleware\TenantRateLimiter::class, // 租户精细化限流中间件
    ];
}
