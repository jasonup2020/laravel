<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 用户数据填充
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'id' => 1,
                'tenant_id' => 1,
                'name' => 'admin',
                'email' => 'admin@example.com',
                'phone' => '13800138000',
                'password' => 'admin123',
                'status' => User::STATUS_ENABLED,
            ],
            [
                'id' => 2,
                'tenant_id' => 1,
                'name' => 'test',
                'email' => 'test@example.com',
                'phone' => '13800138001',
                'password' => 'test123',
                'status' => User::STATUS_ENABLED,
            ],
        ];

        foreach ($users as $userData) {
            $password = $userData['password'];
            $randomCode = Str::random(6);
            
            // 直接插入数据，避免触发model的mutator
            DB::table('users')->insert([
                'id' => $userData['id'],
                'tenant_id' => $userData['tenant_id'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'password' => Hash::make($password . $randomCode),
                'random_code' => $randomCode,
                'status' => $userData['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = User::find($userData['id']);

            // 分配角色
            if ($user->id === 1) {
                $user->roles()->sync([1]); // 超级管理员角色
            } else {
                $user->roles()->sync([2]); // 普通用户角色
            }
        }
    }
}
