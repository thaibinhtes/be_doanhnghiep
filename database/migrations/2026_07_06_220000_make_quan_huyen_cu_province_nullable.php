<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('quan_huyen_cu', function (Blueprint $table) {
            $table->dropForeign(['tinh_thanh_cu_code']);
        });

        Schema::table('quan_huyen_cu', function (Blueprint $table) {
            $table->string('tinh_thanh_cu_code', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('quan_huyen_cu', function (Blueprint $table) {
            $table->string('tinh_thanh_cu_code', 32)->nullable(false)->change();
            $table->foreign('tinh_thanh_cu_code')
                ->references('code')
                ->on('tinh_thanh_cu')
                ->cascadeOnDelete();
        });
    }
};
