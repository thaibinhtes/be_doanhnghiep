<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dn_trang_thais', function (Blueprint $table) {
            $table->id();
            $table->string('ma', 50)->unique();
            $table->string('ten');
            $table->string('loai', 20)->default('bao_cao');
            $table->boolean('yeu_cau_ly_do')->default(false);
            $table->boolean('hien_thi_bao_cao')->default(false);
            $table->unsignedSmallInteger('thu_tu_bao_cao')->nullable();
            $table->boolean('mac_dinh')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('mo_ta')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['ma' => 'dang_hoat_dong', 'ten' => 'Số doanh nghiệp đang hoạt động (STC)', 'loai' => 'hoat_dong', 'yeu_cau_ly_do' => false, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 1, 'mac_dinh' => false],
            ['ma' => 'da_dinh_danh', 'ten' => 'Đã định danh thành công (Công an)', 'loai' => 'dinh_danh', 'yeu_cau_ly_do' => false, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 2, 'mac_dinh' => false],
            ['ma' => 'chua_dinh_danh', 'ten' => 'Số DN định danh chưa thành công', 'loai' => 'dinh_danh', 'yeu_cau_ly_do' => false, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 3, 'mac_dinh' => true],
            ['ma' => 'giai_the_hop_nhat', 'ten' => 'Đang làm thủ tục giải thể, đã bị chia, bị hợp nhất, bị sáp nhập', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 4, 'mac_dinh' => false],
            ['ma' => 'khong_hoat_dong_dia_chi', 'ten' => 'Không còn hoạt động kinh doanh tại địa chỉ đã đăng ký', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 5, 'mac_dinh' => false],
            ['ma' => 'thu_hoi_gcn', 'ten' => 'Bị thu hồi Giấy chứng nhận đăng ký doanh nghiệp do cưỡng chế về quản lý thuế', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 6, 'mac_dinh' => false],
            ['ma' => 'tam_ngung', 'ten' => 'Tạm ngừng kinh doanh', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 7, 'mac_dinh' => false],
            ['ma' => 'giai_the_pha_san', 'ten' => 'Đã giải thể, phá sản, chấm dứt tồn tại', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 8, 'mac_dinh' => false],
            ['ma' => 'canh_bao_vi_pham', 'ten' => 'Bị cảnh báo vi phạm', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 9, 'mac_dinh' => false],
            ['ma' => 'khac', 'ten' => 'Khác', 'loai' => 'bao_cao', 'yeu_cau_ly_do' => true, 'hien_thi_bao_cao' => true, 'thu_tu_bao_cao' => 10, 'mac_dinh' => false],
        ];

        foreach ($rows as $row) {
            DB::table('dn_trang_thais')->insert(array_merge($row, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dn_trang_thais');
    }
};
