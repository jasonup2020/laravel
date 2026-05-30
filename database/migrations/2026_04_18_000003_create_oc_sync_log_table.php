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
        Schema::create('oc_sync_log', function (Blueprint $table) {
            $table->commen('sync同步操作日志表');
            $table->bigIncrements('log_id');
            $table->string('source_site', 100);
            $table->string('source_table', 100);
            $table->string('source_id', 64);
            $table->string('action', 50)->comment('操作类型');
            $table->text('message')->nullable()->comment('详细信息');
            $table->integer('duration')->default(0)->comment('耗时(毫秒)');
            $table->datetime('created_at');

            $table->index(['source_site', 'source_table'], 'idx_site_table');
            $table->index('created_at', 'idx_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_sync_log');
    }
};
