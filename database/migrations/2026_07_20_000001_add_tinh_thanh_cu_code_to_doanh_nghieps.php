<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('doanh_nghieps') || Schema::hasColumn('doanh_nghieps', 'tinh_thanh_cu_code')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->string('tinh_thanh_cu_code', 32)->nullable()->after('phuong_xa');
            $table->index('tinh_thanh_cu_code');
        });

        if (Schema::hasTable('quan_huyen_cu')) {
            DB::table('doanh_nghieps')
                ->whereNotNull('quan_huyen_cu_code')
                ->update([
                    'tinh_thanh_cu_code' => DB::raw(
                        '(SELECT tinh_thanh_cu_code FROM quan_huyen_cu WHERE quan_huyen_cu.code = doanh_nghieps.quan_huyen_cu_code)'
                    ),
                ]);
        }

        if (Schema::hasTable('tinh_thanh_cu')) {
            Schema::table('doanh_nghieps', function (Blueprint $table) {
                $table->foreign('tinh_thanh_cu_code')
                    ->references('code')
                    ->on('tinh_thanh_cu')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('doanh_nghieps') || !Schema::hasColumn('doanh_nghieps', 'tinh_thanh_cu_code')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (Schema::hasTable('tinh_thanh_cu')) {
                $table->dropForeign(['tinh_thanh_cu_code']);
            }
            $table->dropIndex(['tinh_thanh_cu_code']);
            $table->dropColumn('tinh_thanh_cu_code');
        });
    }
};
