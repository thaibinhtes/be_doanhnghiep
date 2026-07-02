<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tinh_thanh', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('full_name');
            $table->timestamps();

            $table->index('full_name');
        });

        Schema::create('xa_phuong', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('full_name');
            $table->string('tinh_thanh_code', 20);
            $table->timestamps();

            $table->foreign('tinh_thanh_code')
                ->references('code')
                ->on('tinh_thanh')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('tinh_thanh_code');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xa_phuong');
        Schema::dropIfExists('tinh_thanh');
    }
};
