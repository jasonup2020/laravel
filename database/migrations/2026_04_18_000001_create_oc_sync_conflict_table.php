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
        Schema::create('oc_sync_conflict', function (Blueprint $table) {
            $table->bigIncrements('conflict_id');
            $table->string('source_site', 100);
            $table->string('source_table', 100);
            $table->string('conflict_type', 50)->comment('冲突类型');
            $table->json('local_data')->nullable()->comment('本地数据');
            $table->json('remote_data')->nullable()->comment('远程数据');
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
        Schema::dropIfExists('oc_sync_conflict');
    }
};
