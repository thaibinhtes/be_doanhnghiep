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
        Schema::create('doanh_nghieps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tt')->nullable();
            $table->string('ma_so_doanh_nghiep')->nullable()->unique();
            $table->string('ten_doanh_nghiep');
            $table->text('dia_chi')->nullable();
            $table->string('quan_huyen')->nullable();
            $table->string('phuong_xa')->nullable();
            $table->string('von_dieu_le')->nullable();
            $table->string('trang_thai')->nullable();
            $table->string('dien_thoai')->nullable();
            $table->string('nguoi_dai_dien')->nullable();
            $table->string('ngay_sinh_nguoi_dai_dien')->nullable();
            $table->string('chu_so_huu')->nullable();
            $table->string('nganh_nghe_kd_chinh')->nullable();
            $table->text('nganh_nghe_kd')->nullable();
            $table->string('ngay_cap')->nullable();
            $table->string('ngay_dang_ky_thay_doi')->nullable();
            $table->string('loai_hinh_dn')->nullable();
            $table->unsignedInteger('so_luong_lao_dong')->nullable()->default(0);
            $table->text('ds_thanh_vien_gop_von')->nullable();
            $table->text('ds_co_dong')->nullable();
            $table->string('loai_dn')->nullable();
            $table->timestamps();

            $table->index('ma_so_doanh_nghiep');
            $table->index('ten_doanh_nghiep');
            $table->index('quan_huyen');
            $table->index('phuong_xa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doanh_nghieps');
    }
};
