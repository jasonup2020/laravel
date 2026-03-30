<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 主数据填充
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            MenuSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
