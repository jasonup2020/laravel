<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * 认证功能测试
 * 
 * @package Tests\Feature
 * @author  SaaS Platform
 * @version 1.0.0
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试用户登录成功
     *
     * @return void
     */
    public function test_user_can_login_successfully(): void
    {
        // 创建租户
        $tenant = Tenant::factory()->create();
        
        // 创建用户
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'testuser',
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
        ]);

        // 发送登录请求
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        // 断言响应
        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                    'user' => [
                        'id',
                        'username',
                        'name',
                    ],
                ],
                'success',
            ]);
    }

    /**
     * 测试用户登录失败（密码错误）
     *
     * @return void
     */
    public function test_user_login_fails_with_wrong_password(): void
    {
        $tenant = Tenant::factory()->create();
        
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'testuser',
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 测试用户登录失败（用户不存在）
     *
     * @return void
     */
    public function test_user_login_fails_with_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nonexistent',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 测试用户登录失败（账号被禁用）
     *
     * @return void
     */
    public function test_user_login_fails_with_disabled_account(): void
    {
        $tenant = Tenant::factory()->create();
        
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'testuser',
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'status' => 0, // 禁用状态
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 测试用户登出
     *
     * @return void
     */
    public function test_user_can_logout(): void
    {
        $tenant = Tenant::factory()->create();
        
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'testuser',
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
        ]);

        // 先登录获取token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');

        // 使用token登出
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * 测试刷新Token
     *
     * @return void
     */
    public function test_user_can_refresh_token(): void
    {
        $tenant = Tenant::factory()->create();
        
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'testuser',
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
        ]);

        // 先登录获取refresh token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        // 使用refresh token刷新
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                ],
                'success',
            ]);
    }

    /**
     * 测试获取当前用户信息
     *
     * @return void
     */
    public function test_user_can_get_profile(): void
    {
        $tenant = Tenant::factory()->create();
        
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'testuser',
            'password' => Hash::make('password123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
        ]);

        // 先登录获取token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.access_token');

        // 获取用户信息
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'id',
                    'username',
                    'name',
                ],
                'success',
            ]);
    }

    /**
     * 测试未认证访问受保护接口
     *
     * @return void
     */
    public function test_unauthenticated_access_protected_route(): void
    {
        $response = $this->getJson('/api/v1/auth/profile');

        $response->assertStatus(401);
    }
}
