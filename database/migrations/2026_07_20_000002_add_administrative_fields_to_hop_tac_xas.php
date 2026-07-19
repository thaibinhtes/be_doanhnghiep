<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hop_tac_xas')) {
            return;
        }

        Schema::table('hop_tac_xas', function (Blueprint $table) {
            $table->text('dia_chi_cu')->nullable()->after('dia_chi');
            $table->string('xa_phuong_cu', 255)->nullable()->after('phuong_xa');
            $table->string('xa_phuong_moi', 255)->nullable()->after('xa_phuong_cu');
            $table->string('quan_huyen_cu', 255)->nullable()->after('xa_phuong_moi');
            $table->string('quan_huyen_moi', 255)->nullable()->after('quan_huyen_cu');
            $table->string('tinh_thanh_cu', 255)->nullable()->after('quan_huyen_moi');
        });

        DB::table('hop_tac_xas')
            ->whereNull('dia_chi_cu')
            ->update(['dia_chi_cu' => DB::raw('dia_chi')]);

        DB::table('hop_tac_xas')
            ->whereNull('xa_phuong_cu')
            ->update(['xa_phuong_cu' => DB::raw('phuong_xa')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('hop_tac_xas')) {
            return;
        }

        Schema::table('hop_tac_xas', function (Blueprint $table) {
            $table->dropColumn([
                'dia_chi_cu',
                'xa_phuong_cu',
                'xa_phuong_moi',
                'quan_huyen_cu',
                'quan_huyen_moi',
                'tinh_thanh_cu',
            ]);
        });
    }
};
