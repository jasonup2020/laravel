<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 设备表迁移
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->comment('设备表');
            
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('device_id')->comment('设备ID');
            $table->string('device_name')->nullable()->comment('设备名称');
            $table->string('device_type')->nullable()->comment('设备类型');
            $table->string('platform')->nullable()->comment('平台');
            $table->string('browser')->nullable()->comment('浏览器');
            $table->string('ip_address', 45)->nullable()->comment('IP地址');
            $table->text('user_agent')->nullable()->comment('User Agent');
            $table->string('access_token')->nullable()->comment('访问令牌');
            $table->timestamp('last_active_at')->nullable()->comment('最后活跃时间');
            $table->tinyInteger('status')->default(1)->comment('状态：0=离线，1=在线');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('device_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
