<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RBACSeeder extends Seeder {
public function run(){
DB::table('roles')->insert(['name'=>'admin']);
DB::table('permissions')->insert(['code'=>'user.manage']);
}
}
