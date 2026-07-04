<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dn_dinh_danh_lich_sus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doanh_nghiep_id')->constrained('doanh_nghieps')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ma_so_doanh_nghiep', 50)->nullable();
            $table->string('ten_doanh_nghiep')->nullable();
            $table->string('hanh_dong', 20);
            $table->boolean('gia_tri_cu')->default(false);
            $table->boolean('gia_tri_moi')->default(false);
            $table->string('nguon', 30)->default('he_thong');
            $table->text('ghi_chu')->nullable();
            $table->timestamps();

            $table->index(['doanh_nghiep_id', 'created_at']);
            $table->index('hanh_dong');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dn_dinh_danh_lich_sus');
    }
};
