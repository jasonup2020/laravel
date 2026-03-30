<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;

class ApiRateLimitTest extends TestCase
{
    public function test_rate_limit_middleware_exists()
    {
        // 测试中间件类是否存在
        $this->assertTrue(class_exists(\App\Http\Middleware\ApiRateLimit::class));
        
        // 测试中间件是否可以实例化
        $middleware = new \App\Http\Middleware\ApiRateLimit();
        $this->assertInstanceOf(\App\Http\Middleware\ApiRateLimit::class, $middleware);
    }

    public function test_rate_limit_key_generation()
    {
        $middleware = new \App\Http\Middleware\ApiRateLimit();
        
        // 创建模拟请求
        $request = \Illuminate\Http\Request::create('/api/users', 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = new \Illuminate\Routing\Route(['GET'], '/api/users', function () {});
            $route->name('users.index');
            return $route;
        });

        // 使用反射调用 protected 方法
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $key1 = $method->invoke($middleware, $request);
        $key2 = $method->invoke($middleware, $request);

        // 相同请求应该生成相同的键
        $this->assertEquals($key1, $key2);
    }

    public function test_different_endpoints_generate_different_keys()
    {
        $middleware = new \App\Http\Middleware\ApiRateLimit();
        
        // 创建两个不同的请求
        $request1 = \Illuminate\Http\Request::create('/api/users', 'GET');
        $request1->setRouteResolver(function () use ($request1) {
            $route = new \Illuminate\Routing\Route(['GET'], '/api/users', function () {});
            $route->name('users.index');
            return $route;
        });

        $request2 = \Illuminate\Http\Request::create('/api/products', 'GET');
        $request2->setRouteResolver(function () use ($request2) {
            $route = new \Illuminate\Routing\Route(['GET'], '/api/products', function () {});
            $route->name('products.index');
            return $route;
        });

        // 使用反射调用 protected 方法
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $key1 = $method->invoke($middleware, $request1);
        $key2 = $method->invoke($middleware, $request2);

        // 不同端点应该生成不同的键
        $this->assertNotEquals($key1, $key2);
    }

    public function test_different_methods_generate_different_keys()
    {
        $middleware = new \App\Http\Middleware\ApiRateLimit();
        
        // 创建两个不同方法的请求
        $request1 = \Illuminate\Http\Request::create('/api/users', 'GET');
        $request1->setRouteResolver(function () use ($request1) {
            $route = new \Illuminate\Routing\Route(['GET'], '/api/users', function () {});
            $route->name('users.index');
            return $route;
        });

        $request2 = \Illuminate\Http\Request::create('/api/users', 'POST');
        $request2->setRouteResolver(function () use ($request2) {
            $route = new \Illuminate\Routing\Route(['POST'], '/api/users', function () {});
            $route->name('users.store');
            return $route;
        });

        // 使用反射调用 protected 方法
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $key1 = $method->invoke($middleware, $request1);
        $key2 = $method->invoke($middleware, $request2);

        // 不同方法应该生成不同的键
        $this->assertNotEquals($key1, $key2);
    }

    public function test_rate_limiter_functionality()
    {
        $key = 'test_rate_limit_key';
        $maxAttempts = 5;

        // 清除之前的测试数据
        RateLimiter::clear($key);

        // 测试频次限制
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->assertFalse(RateLimiter::tooManyAttempts($key, $maxAttempts));
            RateLimiter::hit($key, 60);
        }

        // 超过限制后应该返回 true
        $this->assertTrue(RateLimiter::tooManyAttempts($key, $maxAttempts));

        // 清除测试数据
        RateLimiter::clear($key);
    }
}
