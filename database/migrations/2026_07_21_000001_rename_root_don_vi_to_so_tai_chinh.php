<?php

use App\Models\DonVi;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DonVi::query()
            ->where('ma', DonVi::ROOT_MA)
            ->update([
                'ten' => DonVi::ROOT_TEN,
                'mo_ta' => DonVi::ROOT_MO_TA,
            ]);
    }

    public function down(): void
    {
        DonVi::query()
            ->where('ma', DonVi::ROOT_MA)
            ->update([
                'ten' => 'Ban quản lý doanh nghiệp',
                'mo_ta' => 'Đơn vị gốc hệ thống',
            ]);
    }
};
