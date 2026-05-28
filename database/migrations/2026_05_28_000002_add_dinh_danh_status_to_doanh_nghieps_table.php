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
            if (!Schema::hasColumn('doanh_nghieps', 'da_cap_nhat_dinh_danh')) {
                $table->boolean('da_cap_nhat_dinh_danh')->default(false)->after('trang_thai');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (Schema::hasColumn('doanh_nghieps', 'da_cap_nhat_dinh_danh')) {
                $table->dropColumn('da_cap_nhat_dinh_danh');
            }
        });
    }
};

