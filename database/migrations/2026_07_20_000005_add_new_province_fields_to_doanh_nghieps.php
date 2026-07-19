<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->string('tinh_thanh_moi')->nullable()->after('tinh_thanh_cu_id');
            $table->foreignId('tinh_thanh_moi_id')->nullable()->after('tinh_thanh_moi')
                ->constrained('hanh_chinh_tinh')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tinh_thanh_moi_id');
            $table->dropColumn('tinh_thanh_moi');
        });
    }
};
