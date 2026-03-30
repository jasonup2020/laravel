<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token黑名单表迁移
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('token_blacklists', function (Blueprint $table) {
            $table->comment('Token黑名单表');

            $table->id();
            $table->text('token')->comment('Token');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('reason')->nullable()->comment('加入黑名单原因');
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_blacklists');
    }
};
