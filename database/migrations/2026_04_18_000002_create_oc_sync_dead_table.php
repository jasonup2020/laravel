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
        Schema::create('oc_sync_dead', function (Blueprint $table) {
            $table->commen('sync死信队列表');
            $table->bigIncrements('dead_id');
            $table->bigInteger('sync_id');
            $table->string('source_site', 100);
            $table->string('source_table', 100);
            $table->string('source_id', 64);
            $table->text('error_message');
            $table->integer('retry_count');
            $table->datetime('last_attempt_at');
            $table->datetime('created_at');

            $table->index('sync_id', 'idx_sync');
            $table->index(['source_site', 'source_table'], 'idx_site_table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_sync_dead');
    }
};
