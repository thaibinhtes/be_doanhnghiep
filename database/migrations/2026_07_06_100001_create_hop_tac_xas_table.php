<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hop_tac_xas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tt')->nullable();
            $table->string('ten_htx');
            $table->string('ma_so_thue', 50)->nullable()->index();
            $table->string('nam_thanh_lap', 10)->nullable();
            $table->string('chu_tich_hdqt_ten')->nullable();
            $table->string('dien_thoai', 50)->nullable();
            $table->text('dia_chi')->nullable();
            $table->string('phuong_xa', 150)->nullable()->index();
            $table->decimal('dien_tich_ha', 12, 4)->nullable();
            $table->string('von_dieu_le', 100)->nullable();
            $table->unsignedInteger('so_thanh_vien')->nullable();
            $table->unsignedInteger('so_nguoi_lao_dong')->nullable();
            $table->string('linh_vuc')->nullable();
            $table->string('hoat_dong')->nullable();
            $table->text('ds_thanh_vien')->nullable();
            $table->text('dia_chi_moi')->nullable();
            $table->text('ghi_chu')->nullable();
            $table->foreignId('don_vi_id')->nullable()->constrained('don_vis')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['don_vi_id', 'ten_htx']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hop_tac_xas');
    }
};
