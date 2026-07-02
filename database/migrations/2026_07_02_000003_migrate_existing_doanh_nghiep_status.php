<?php

use App\Models\DnTrangThai;
use App\Models\DoanhNghiep;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $chuaDinhDanh = DnTrangThai::where('ma', 'chua_dinh_danh')->first();
        $daDinhDanh = DnTrangThai::where('ma', 'da_dinh_danh')->first();

        if (!$chuaDinhDanh || !$daDinhDanh) {
            return;
        }

        DoanhNghiep::whereNull('dn_trang_thai_id')
            ->where('da_cap_nhat_dinh_danh', true)
            ->update(['dn_trang_thai_id' => $daDinhDanh->id]);

        DoanhNghiep::whereNull('dn_trang_thai_id')
            ->update(['dn_trang_thai_id' => $chuaDinhDanh->id]);
    }

    public function down(): void
    {
        DoanhNghiep::query()->update(['dn_trang_thai_id' => null]);
    }
};
