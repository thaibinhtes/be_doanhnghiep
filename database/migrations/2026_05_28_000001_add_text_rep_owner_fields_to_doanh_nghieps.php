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
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (!Schema::hasColumn('doanh_nghieps', 'nguoi_dai_dien_ten')) {
                $table->string('nguoi_dai_dien_ten')->nullable()->after('dien_thoai');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'ngay_sinh_nguoi_dai_dien')) {
                $table->string('ngay_sinh_nguoi_dai_dien')->nullable()->after('nguoi_dai_dien_ten');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'chu_so_huu_ten')) {
                $table->string('chu_so_huu_ten')->nullable()->after('ngay_sinh_nguoi_dai_dien');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'ds_co_dong')) {
                $table->text('ds_co_dong')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $columns = ['nguoi_dai_dien_ten', 'ngay_sinh_nguoi_dai_dien', 'chu_so_huu_ten', 'ds_co_dong'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('doanh_nghieps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
