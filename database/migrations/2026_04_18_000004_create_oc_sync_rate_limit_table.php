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
        Schema::create('oc_sync_rate_limit', function (Blueprint $table) {
            $table->commen('sync速率限制表 - 记录IP请求频率，防止API滥用');
            $table->increments('rate_id')->comment('主键ID，自增');
            $table->string('ip_address', 45)->unique()->comment('客户端IP地址（支持IPv4和IPv6）');
            $table->integer('request_count')->default(0)->comment('当前时间窗口内的请求计数');
            $table->datetime('last_request_time')->comment('最后一次请求时间');
            $table->datetime('blocked_until')->nullable()->comment('封禁截止时间（NULL表示未封禁）');
            $table->datetime('created_at')->useCurrent()->comment('记录创建时间');

            $table->index('blocked_until', 'idx_blocked_until')->comment('索引：筛选被封禁的IP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_sync_rate_limit');
    }
};
