<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->comment('用户表');

            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->string('name')->comment('用户名');
            $table->string('email')->unique()->comment('邮箱');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->string('password')->comment('密码');
            $table->string('random_code', 6)->nullable()->comment('密码随机码');
            $table->string('avatar')->nullable()->comment('头像');
            $table->string('phone', 20)->nullable()->comment('手机号');
            $table->unsignedBigInteger('department_id')->nullable()->comment('部门ID');
            $table->unsignedBigInteger('position_id')->nullable()->comment('岗位ID');
            $table->unsignedBigInteger('level_id')->nullable()->comment('职级ID');
            $table->string('locale', 10)->default('zh_CN')->comment('语言');
            $table->string('timezone', 50)->default('Asia/Shanghai')->comment('时区');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->text('remark')->nullable()->comment('备注');
            $table->rememberToken();
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建者');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新者');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('department_id');
            $table->index('position_id');
            $table->index('level_id');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
