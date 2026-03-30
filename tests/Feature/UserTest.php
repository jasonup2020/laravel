<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * 用户管理功能测试
 * 
 * @package Tests\Feature
 * @author  SaaS Platform
 * @version 1.0.0
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理员用户
     *
     * @var User
     */
    protected User $adminUser;

    /**
     * 访问令牌
     *
     * @var string
     */
    protected string $token;

    /**
     * 测试前置设置
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建租户
        $tenant = Tenant::factory()->create();

        // 创建管理员用户
        $salt = \Illuminate\Support\Str::random(6);
        $this->adminUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'admin',
            'password' => Hash::make('admin123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
            'is_admin' => 1,
        ]);

        // 登录获取token
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $this->token = $response->json('data.access_token');
    }

    /**
     * 测试获取用户列表
     *
     * @return void
     */
    public function test_can_get_user_list(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'list',
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page',
                    ],
                ],
                'success',
            ]);
    }

    /**
     * 测试获取用户列表带分页
     *
     * @return void
     */
    public function test_can_get_user_list_with_pagination(): void
    {
        $tenant = Tenant::first();
        
        // 创建多个用户
        User::factory()->count(20)->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/users?page=2&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 10);
    }

    /**
     * 测试获取用户详情
     *
     * @return void
     */
    public function test_can_get_user_detail(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/users/' . $this->adminUser->id);

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
            ])
            ->assertJsonPath('data.id', $this->adminUser->id);
    }

    /**
     * 测试获取不存在的用户详情
     *
     * @return void
     */
    public function test_cannot_get_nonexistent_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/users/99999');

        $response->assertStatus(404);
    }

    /**
     * 测试创建用户
     *
     * @return void
     */
    public function test_can_create_user(): void
    {
        $tenant = Tenant::first();

        $userData = [
            'username' => 'newuser',
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'phone' => '13800138000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'id',
                    'username',
                    'name',
                ],
                'success',
            ])
            ->assertJsonPath('data.username', 'newuser');

        // 验证数据库
        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    }

    /**
     * 测试创建用户验证失败
     *
     * @return void
     */
    public function test_create_user_validation_fails(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/users', [
            'username' => '', // 用户名不能为空
            'name' => 'Test User',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 测试创建用户用户名重复
     *
     * @return void
     */
    public function test_create_user_with_duplicate_username(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/users', [
            'username' => 'admin', // 已存在的用户名
            'name' => 'Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 测试更新用户
     *
     * @return void
     */
    public function test_can_update_user(): void
    {
        $tenant = Tenant::first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/users/' . $user->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /**
     * 测试更新不存在的用户
     *
     * @return void
     */
    public function test_update_nonexistent_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/users/99999', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    /**
     * 测试删除用户
     *
     * @return void
     */
    public function test_can_delete_user(): void
    {
        $tenant = Tenant::first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/users/' . $user->id);

        $response->assertStatus(200);

        // 验证软删除
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * 测试删除不存在的用户
     *
     * @return void
     */
    public function test_delete_nonexistent_user(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/users/99999');

        $response->assertStatus(404);
    }

    /**
     * 测试批量删除用户
     *
     * @return void
     */
    public function test_can_batch_delete_users(): void
    {
        $tenant = Tenant::first();
        $users = User::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
        ]);

        $ids = $users->pluck('id')->toArray();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/users/batch-delete', [
            'ids' => $ids,
        ]);

        $response->assertStatus(200);

        foreach ($ids as $id) {
            $this->assertSoftDeleted('users', ['id' => $id]);
        }
    }

    /**
     * 测试切换用户状态
     *
     * @return void
     */
    public function test_can_toggle_user_status(): void
    {
        $tenant = Tenant::first();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/users/' . $user->id . '/toggle-status');

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 0,
        ]);
    }

    /**
     * 测试修改密码
     *
     * @return void
     */
    public function test_can_change_password(): void
    {
        $tenant = Tenant::first();
        $salt = \Illuminate\Support\Str::random(6);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('oldpassword' . $salt),
            'password_salt' => $salt,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/users/' . $user->id . '/password', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
    }

    /**
     * 测试搜索用户
     *
     * @return void
     */
    public function test_can_search_users(): void
    {
        $tenant = Tenant::first();
        
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'John Doe',
        ]);
        
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Jane Smith',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/users?search=John');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1);
    }
}
