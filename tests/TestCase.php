<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        Tenant::updateOrCreate(
            ['domain' => 'test.com'],
            [
                'name' => 'Test Tenant',
                'plan' => 'basic',
                'expires_at' => now()->addYear(),
                'is_active' => true,
            ]
        );
    }
    
    protected function createTestUser(array $attributes = [], $role = 'user')
    {
        $randomCode = Str::random(6);
        
        $user = User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password' . $randomCode),
            'random_code' => $randomCode,
            'tenant_id' => 1,
            'phone' => '13800138000',
            'avatar' => null,
            'status' => 'active',
            'locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
        ], $attributes));
        
        if ($role === 'admin') {
            $adminRole = Role::where('name', 'admin')->first();
            if ($adminRole) {
                $user->roles()->attach($adminRole->id);
            }
        } else {
            $userRole = Role::where('name', 'user')->first();
            if ($userRole) {
                $user->roles()->attach($userRole->id);
            }
        }
        
        return $user;
    }
    
    protected function getTokenForUser(User $user)
    {
        return \App\Support\JwtManager::issue($user, 1);
    }
}
