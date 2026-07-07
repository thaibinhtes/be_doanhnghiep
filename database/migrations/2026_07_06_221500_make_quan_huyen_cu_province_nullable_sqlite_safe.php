<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('quan_huyen_cu_tmp', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->string('tinh_thanh_cu_code', 32)->nullable();
            $table->timestamps();

            $table->foreign('tinh_thanh_cu_code')
                ->references('code')
                ->on('tinh_thanh_cu')
                ->cascadeOnDelete();

            $table->index(['tinh_thanh_cu_code', 'full_name']);
        });

        DB::statement('
            INSERT INTO quan_huyen_cu_tmp (code, full_name, tinh_thanh_cu_code, created_at, updated_at)
            SELECT code, full_name, tinh_thanh_cu_code, created_at, updated_at
            FROM quan_huyen_cu
        ');

        Schema::drop('quan_huyen_cu');
        Schema::rename('quan_huyen_cu_tmp', 'quan_huyen_cu');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('quan_huyen_cu_tmp', function (Blueprint $table) {
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

        DB::statement('
            INSERT INTO quan_huyen_cu_tmp (code, full_name, tinh_thanh_cu_code, created_at, updated_at)
            SELECT code, full_name, COALESCE(tinh_thanh_cu_code, "CU-AG"), created_at, updated_at
            FROM quan_huyen_cu
        ');

        Schema::drop('quan_huyen_cu');
        Schema::rename('quan_huyen_cu_tmp', 'quan_huyen_cu');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};

