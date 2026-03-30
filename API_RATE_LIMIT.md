# API 频次限制使用说明

## 概述

所有 API 默认限制为每分钟 60 次请求。你可以为个别 API 设置自定义的频次限制。

## 默认配置

所有 API 路由已应用默认的频次限制（每分钟 60 次）：

```php
Route::middleware(['api.ratelimit:60'])->group(function () {
    // 所有 API 路由
});
```

## 自定义频次限制

### 方法 1：为特定路由设置不同的限制

```php
// 登录接口限制为每分钟 5 次
Route::middleware(['api.ratelimit:5'])->post('login', [AuthController::class, 'login']);

// 注册接口限制为每分钟 10 次
Route::middleware(['api.ratelimit:10'])->post('register', [AuthController::class, 'register']);

// 其他接口使用默认限制（60次/分钟）
Route::middleware(['api.ratelimit:60'])->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### 方法 2：为路由组设置不同的限制

```php
// 高频访问接口组（100次/分钟）
Route::middleware(['api.ratelimit:100'])->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
});

// 低频访问接口组（10次/分钟）
Route::middleware(['api.ratelimit:10'])->group(function () {
    Route::post('upload', [UploadController::class, 'store']);
    Route::post('export', [ExportController::class, 'generate']);
});
```

### 方法 3：在控制器中动态设置

创建动态频次限制中间件：

```php
// app/Http/Middleware/DynamicRateLimit.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class DynamicRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        // 根据路由名称或用户角色动态设置限制
        $maxAttempts = $this->getMaxAttempts($request);

        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }

    protected function getMaxAttempts(Request $request): int
    {
        // VIP 用户可以有更高的限制
        if ($user = $request->user()) {
            if ($user->is_vip) {
                return 200; // VIP 用户每分钟 200 次
            }
        }

        // 根据路由名称设置不同的限制
        $routeName = $request->route()?->getName();

        return match($routeName) {
            'login' => 5,
            'register' => 10,
            'upload' => 5,
            default => 60,
        };
    }

    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return sha1($user->getAuthIdentifier());
        }
        return sha1($request->ip());
    }
}
```

## 响应头信息

频次限制中间件会在响应头中添加以下信息：

- `X-RateLimit-Limit`: 最大请求次数
- `X-RateLimit-Remaining`: 剩余请求次数

示例响应头：

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
```

## 超过限制的响应

当请求超过频次限制时，将返回 429 状态码：

```json
{
    "message": "Too many requests. Please try again later.",
    "retry_after": 30
}
```

`retry_after` 字段表示需要等待的秒数。

## 基于用户的限制

中间件会自动识别：

- **已认证用户**：基于用户 ID 进行限制
- **未认证用户**：基于 IP 地址进行限制

## 每个API独立限制

**重要特性**：同一用户的每个API端点都有独立的频次限制。

### 工作原理

频次限制键由以下部分组成：
- 用户ID 或 IP地址
- HTTP方法（GET、POST、PUT、DELETE等）
- 路由URI
- 路由名称

这意味着：
- 用户A访问 `/api/users` 有60次/分钟的限制
- 用户A访问 `/api/products` 也有独立的60次/分钟的限制
- 用户A访问 `/api/login` 有独立的5次/分钟的限制

### 示例说明

假设用户ID为123：

```
用户123的频次限制：
├─ GET /api/users      → 独立计数（60次/分钟）
├─ POST /api/users     → 独立计数（60次/分钟）
├─ GET /api/users/1    → 独立计数（60次/分钟）
├─ PUT /api/users/1    → 独立计数（60次/分钟）
├─ DELETE /api/users/1 → 独立计数（60次/分钟）
├─ POST /api/login     → 独立计数（5次/分钟）
└─ POST /api/register  → 独立计数（10次/分钟）
```

每个端点的限制互不影响，用户可以在同一分钟内：
- 调用 `/api/users` 60次
- 同时调用 `/api/products` 60次
- 同时调用 `/api/categories` 60次

### 技术实现

频次限制键生成算法：

```php
protected function resolveRequestSignature(Request $request): string
{
    $route = $request->route();
    $routeName = $route ? $route->getName() : '';
    $routeUri = $route ? $route->uri() : $request->path();
    $method = $request->method();

    // 为每个API端点创建唯一键
    $endpointKey = sha1($method . '|' . $routeUri . '|' . $routeName);

    // 结合用户ID或IP地址
    if ($user = $request->user()) {
        return sha1($user->getAuthIdentifier() . '|' . $endpointKey);
    }

    return sha1($request->ip() . '|' . $endpointKey);
}
```

### 优势

1. **精细化控制**：每个API端点独立管理频次
2. **公平性**：高频API不会影响低频API的访问
3. **灵活性**：可以为不同类型的API设置不同的限制
4. **安全性**：敏感操作（如登录）可以设置更严格的限制

## 配置建议

### 不同类型接口的建议限制

| 接口类型 | 建议限制 | 说明 |
|---------|---------|------|
| 登录 | 5-10 次/分钟 | 防止暴力破解 |
| 注册 | 10-20 次/分钟 | 防止恶意注册 |
| 密码重置 | 3-5 次/分钟 | 安全敏感操作 |
| 数据查询 | 60-100 次/分钟 | 正常业务需求 |
| 数据导出 | 5-10 次/分钟 | 资源密集型操作 |
| 文件上传 | 10-20 次/分钟 | 带宽和存储考虑 |

## 测试频次限制

可以使用以下方法测试频次限制是否生效：

```bash
# 快速发送多个请求测试
for i in {1..70}; do
    curl -X GET http://localhost/api/users \
         -H "Authorization: Bearer YOUR_TOKEN" \
         -i
    echo "Request $i"
done
```

## 注意事项

1. 频次限制基于 60 秒滑动窗口
2. 限制是针对每个用户或 IP 独立计算的
3. 建议在生产环境使用 Redis 作为缓存驱动以提高性能
4. 可以在 `.env` 文件中配置默认限制值

## 环境变量配置

可以在 `.env` 文件中添加以下配置：

```env
# 默认 API 频次限制
API_RATE_LIMIT_DEFAULT=60
API_RATE_LIMIT_LOGIN=5
API_RATE_LIMIT_REGISTER=10
```

然后在中间件中使用：

```php
$maxAttempts = config('api.rate_limit.default', 60);
```
