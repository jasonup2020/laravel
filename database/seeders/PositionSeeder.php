<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * 岗位数据填充
 */
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['id' => 1, 'tenant_id' => 1, 'name' => 'CEO', 'code' => 'CEO', 'description' => '首席执行官', 'sort' => 1],
            ['id' => 2, 'tenant_id' => 1, 'name' => 'CTO', 'code' => 'CTO', 'description' => '首席技术官', 'sort' => 2],
            ['id' => 3, 'tenant_id' => 1, 'name' => '技术总监', 'code' => 'TECH_DIRECTOR', 'description' => '技术部门负责人', 'sort' => 3],
            ['id' => 4, 'tenant_id' => 1, 'name' => '技术经理', 'code' => 'TECH_MANAGER', 'description' => '技术团队经理', 'sort' => 4],
            ['id' => 5, 'tenant_id' => 1, 'name' => '高级工程师', 'code' => 'SENIOR_ENGINEER', 'description' => '高级开发工程师', 'sort' => 5],
            ['id' => 6, 'tenant_id' => 1, 'name' => '中级工程师', 'code' => 'MIDDLE_ENGINEER', 'description' => '中级开发工程师', 'sort' => 6],
            ['id' => 7, 'tenant_id' => 1, 'name' => '初级工程师', 'code' => 'JUNIOR_ENGINEER', 'description' => '初级开发工程师', 'sort' => 7],
            ['id' => 8, 'tenant_id' => 1, 'name' => '产品经理', 'code' => 'PRODUCT_MANAGER', 'description' => '产品经理', 'sort' => 8],
            ['id' => 9, 'tenant_id' => 1, 'name' => '运营经理', 'code' => 'OPERATION_MANAGER', 'description' => '运营经理', 'sort' => 9],
        ];

        foreach ($positions as $position) {
            Position::create(array_merge($position, ['status' => Position::STATUS_ENABLED]));
        }
    }
}
