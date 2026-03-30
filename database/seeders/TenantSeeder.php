<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 租户数据填充
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'id' => 1,
                'name' => '默认租户',
                'code' => 'default',
                'domain' => null,
                'contact_name' => '系统管理员',
                'contact_email' => 'admin@example.com',
                'status' => Tenant::STATUS_ENABLED,
            ],
        ];

        foreach ($tenants as $tenant) {
            Tenant::create($tenant);
        }
    }
}
