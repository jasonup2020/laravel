<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 权限表迁移
 * 
 * 创建权限表，用于RBAC权限体系
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->comment('权限表');

            $table->bigIncrements('id')->comment('主键ID');
            $table->string('name')->comment('权限名称');
            $table->string('slug')->unique()->comment('权限标识');
            $table->string('description')->nullable()->comment('权限描述');
            $table->string('module')->nullable()->comment('所属模块');
            $table->string('controller')->nullable()->comment('控制器');
            $table->string('action')->nullable()->comment('方法');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建者');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新者');
            $table->timestamps();
            $table->softDeletes();

            $table->index('module');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
