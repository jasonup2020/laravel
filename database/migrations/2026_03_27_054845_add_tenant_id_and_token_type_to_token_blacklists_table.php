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
        Schema::table('token_blacklists', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('user_id')->comment('租户ID');
            $table->string('token_type', 20)->default('access')->after('token')->comment('Token类型');
            
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('token_blacklists', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'token_type']);
        });
    }
};
