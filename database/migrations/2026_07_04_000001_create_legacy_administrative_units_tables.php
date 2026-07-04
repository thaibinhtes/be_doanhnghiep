<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tinh_thanh_cu', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->timestamps();

            $table->index('full_name');
        });

        Schema::create('quan_huyen_cu', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->string('tinh_thanh_cu_code', 32);
            $table->timestamps();

            $table->foreign('tinh_thanh_cu_code')
                ->references('code')
                ->on('tinh_thanh_cu')
                ->cascadeOnDelete();

            $table->index(['tinh_thanh_cu_code', 'full_name']);
        });

        Schema::create('xa_phuong_cu', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->string('unit_type', 32)->nullable();
            $table->string('quan_huyen_cu_code', 32);
            $table->timestamps();

            $table->foreign('quan_huyen_cu_code')
                ->references('code')
                ->on('quan_huyen_cu')
                ->cascadeOnDelete();

            $table->index(['quan_huyen_cu_code', 'full_name']);
        });

        Schema::create('hanh_chinh_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('group_no')->nullable();
            $table->string('xa_phuong_cu_code', 32);
            $table->string('xa_phuong_moi_code', 20);
            $table->string('new_unit_type', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('xa_phuong_cu_code')
                ->references('code')
                ->on('xa_phuong_cu')
                ->cascadeOnDelete();

            $table->foreign('xa_phuong_moi_code')
                ->references('code')
                ->on('xa_phuong')
                ->cascadeOnDelete();

            $table->unique('xa_phuong_cu_code');
            $table->index('xa_phuong_moi_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hanh_chinh_mappings');
        Schema::dropIfExists('xa_phuong_cu');
        Schema::dropIfExists('quan_huyen_cu');
        Schema::dropIfExists('tinh_thanh_cu');
    }
};
