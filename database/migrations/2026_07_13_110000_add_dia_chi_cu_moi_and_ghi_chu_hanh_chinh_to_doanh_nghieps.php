<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('doanh_nghieps')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (!Schema::hasColumn('doanh_nghieps', 'dia_chi_cu')) {
                $table->text('dia_chi_cu')->nullable()->after('dia_chi');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'dia_chi_moi')) {
                $table->text('dia_chi_moi')->nullable()->after('dia_chi_cu');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'ghi_chu_hanh_chinh')) {
                $table->text('ghi_chu_hanh_chinh')->nullable()->after('xa_phuong_code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('doanh_nghieps')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            foreach (['dia_chi_cu', 'dia_chi_moi', 'ghi_chu_hanh_chinh'] as $column) {
                if (Schema::hasColumn('doanh_nghieps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
