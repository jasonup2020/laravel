<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * 租户管理功能测试
 * 
 * @package Tests\Feature
 * @author  SaaS Platform
 * @version 1.0.0
 */
class TenantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 超级管理员用户
     *
     * @var User
     */
    protected User $superAdmin;

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

        // 创建超级管理员
        $salt = \Illuminate\Support\Str::random(6);
        $this->superAdmin = User::factory()->create([
            'username' => 'superadmin',
            'password' => Hash::make('admin123' . $salt),
            'password_salt' => $salt,
            'status' => 1,
            'is_admin' => 1,
        ]);

        // 登录获取token
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'superadmin',
            'password' => 'admin123',
        ]);

        $this->token = $response->json('data.access_token');
    }

    /**
     * 测试获取租户列表
     *
     * @return void
     */
    public function test_can_get_tenant_list(): void
    {
        // 创建租户
        Tenant::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/tenants');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'list',
                    'pagination',
                ],
                'success',
            ]);
    }

    /**
     * 测试获取租户详情
     *
     * @return void
     */
    public function test_can_get_tenant_detail(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/tenants/' . $tenant->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tenant->id);
    }

    /**
     * 测试创建租户
     *
     * @return void
     */
    public function test_can_create_tenant(): void
    {
        $tenantData = [
            'name' => 'Test Company',
            'code' => 'test001',
            'domain' => 'test.example.com',
            'contact_name' => 'John Doe',
            'contact_phone' => '13800138000',
            'contact_email' => 'john@example.com',
            'status' => 1,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tenants', $tenantData);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Company')
            ->assertJsonPath('data.code', 'test001');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Company',
            'code' => 'test001',
        ]);
    }

    /**
     * 测试创建租户验证失败
     *
     * @return void
     */
    public function test_create_tenant_validation_fails(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tenants', [
            'name' => '', // 名称不能为空
        ]);

        $response->assertStatus(422);
    }

    /**
     * 测试更新租户
     *
     * @return void
     */
    public function test_can_update_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $updateData = [
            'name' => 'Updated Company',
            'contact_name' => 'Jane Doe',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/v1/tenants/' . $tenant->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Company');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Company',
        ]);
    }

    /**
     * 测试删除租户
     *
     * @return void
     */
    public function test_can_delete_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/v1/tenants/' . $tenant->id);

        $response->assertStatus(200);

        $this->assertSoftDeleted('tenants', [
            'id' => $tenant->id,
        ]);
    }

    /**
     * 测试切换租户状态
     *
     * @return void
     */
    public function test_can_toggle_tenant_status(): void
    {
        $tenant = Tenant::factory()->create(['status' => 1]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tenants/' . $tenant->id . '/toggle-status');

        $response->assertStatus(200);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 0,
        ]);
    }

    /**
     * 测试租户编码唯一性
     *
     * @return void
     */
    public function test_tenant_code_must_be_unique(): void
    {
        Tenant::factory()->create(['code' => 'test001']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tenants', [
            'name' => 'Test Company',
            'code' => 'test001', // 重复的编码
            'contact_name' => 'John Doe',
        ]);

        $response->assertStatus(422);
    }

    /**
     * 测试租户域名唯一性
     *
     * @return void
     */
    public function test_tenant_domain_must_be_unique(): void
    {
        Tenant::factory()->create(['domain' => 'test.example.com']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tenants', [
            'name' => 'Test Company',
            'code' => 'test002',
            'domain' => 'test.example.com', // 重复的域名
            'contact_name' => 'John Doe',
        ]);

        $response->assertStatus(422);
    }
}
