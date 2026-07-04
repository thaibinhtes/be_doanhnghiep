<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->string('xa_phuong_cu_code', 32)->nullable()->after('phuong_xa');
            $table->string('tinh_thanh_code', 20)->nullable()->after('xa_phuong_cu_code');
            $table->string('xa_phuong_code', 20)->nullable()->after('tinh_thanh_code');
            $table->timestamp('hanh_chinh_synced_at')->nullable()->after('xa_phuong_code');

            $table->index('xa_phuong_cu_code');
            $table->index('xa_phuong_code');
        });
    }

    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropIndex(['xa_phuong_cu_code']);
            $table->dropIndex(['xa_phuong_code']);
            $table->dropColumn([
                'xa_phuong_cu_code',
                'tinh_thanh_code',
                'xa_phuong_code',
                'hanh_chinh_synced_at',
            ]);
        });
    }
};
