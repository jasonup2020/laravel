<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->comment('租户表');

            $table->bigIncrements('id')->comment('主键ID');
            $table->string('name', 100)->comment('租户名称');
            $table->string('code', 50)->unique()->comment('租户编码');
            $table->string('domain', 100)->nullable()->unique()->comment('租户域名');
            $table->string('logo', 255)->nullable()->comment('租户logo');
            $table->string('contact_name', 50)->nullable()->comment('联系人姓名');
            $table->string('contact_phone', 20)->nullable()->comment('联系人手机号');
            $table->string('contact_email', 100)->nullable()->comment('联系人邮箱');
            $table->string('address', 255)->nullable()->comment('联系人地址');
            $table->json('config')->nullable()->comment('租户配置');
            $table->timestamp('expire_at')->nullable()->comment('过期时间');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态');
            $table->unsignedBigInteger('created_by')->default(0)->comment('创建人ID');
            $table->unsignedBigInteger('updated_by')->default(0)->comment('更新人ID');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index('expire_at');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenants');
    }
};
