<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 角色表迁移
 * 
 * 创建角色表，用于RBAC权限体系
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->comment('角色表');

            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->string('name')->comment('角色名称');
            $table->string('slug')->comment('角色标识');
            $table->string('description')->nullable()->comment('角色描述');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建者');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新者');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->unique(['tenant_id', 'slug'], 'unique_tenant_role_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
