<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 菜单表迁移
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->comment('菜单表');

            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父菜单ID');
            $table->string('name')->comment('菜单名称');
            $table->string('slug')->comment('菜单标识');
            $table->string('icon')->nullable()->comment('图标');
            $table->string('path')->nullable()->comment('路由路径');
            $table->string('component')->nullable()->comment('组件路径');
            $table->string('redirect')->nullable()->comment('重定向');
            $table->integer('sort')->default(0)->comment('排序');
            $table->boolean('is_hidden')->default(false)->comment('是否隐藏');
            $table->boolean('is_external')->default(false)->comment('是否外链');
            $table->boolean('is_cached')->default(true)->comment('是否缓存');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建者');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新者');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('parent_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
