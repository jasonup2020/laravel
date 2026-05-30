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
        Schema::create('oc_sync_record', function (Blueprint $table) {
            $table->commen('sync同步记录映射表');
            $table->bigIncrements('sync_id');
            $table->string('source_site', 100)->comment('来源站点');
            $table->string('source_table', 100)->comment('来源表名');
            $table->string('source_id', 64)->comment('来源ID');
            $table->string('target_id', 64)->comment('目标ID');
            $table->enum('sync_type', ['insert', 'update', 'delete', 'skip'])->comment('同步类型');
            $table->enum('status', ['success', 'failed', 'pending'])->default('success')->comment('状态');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->integer('retry_count')->default(0)->comment('重试次数');
            $table->datetime('created_at');
            $table->datetime('updated_at');

            $table->unique(['source_site', 'source_table', 'source_id'], 'uk_source');
            $table->index('target_id', 'idx_target');
            $table->index('status', 'idx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_sync_record');
    }
};
