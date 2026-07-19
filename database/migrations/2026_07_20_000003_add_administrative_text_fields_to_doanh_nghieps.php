<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('doanh_nghieps')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (!Schema::hasColumn('doanh_nghieps', 'xa_phuong_cu')) {
                $table->string('xa_phuong_cu', 255)->nullable()->after('phuong_xa');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'xa_phuong_moi')) {
                $table->string('xa_phuong_moi', 255)->nullable()->after('xa_phuong_cu');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'quan_huyen_cu')) {
                $table->string('quan_huyen_cu', 255)->nullable()->after('xa_phuong_moi');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'quan_huyen_moi')) {
                $table->string('quan_huyen_moi', 255)->nullable()->after('quan_huyen_cu');
            }
            if (!Schema::hasColumn('doanh_nghieps', 'tinh_thanh_cu')) {
                $table->string('tinh_thanh_cu', 255)->nullable()->after('quan_huyen_moi');
            }
        });

        // Backfill text từ danh mục đã liên kết (nếu có), giữ nguyên mã cho bước đồng bộ sau.
        if (Schema::hasTable('tinh_thanh_cu') && Schema::hasColumn('doanh_nghieps', 'tinh_thanh_cu_code')) {
            DB::table('doanh_nghieps')
                ->whereNull('tinh_thanh_cu')
                ->whereNotNull('tinh_thanh_cu_code')
                ->update([
                    'tinh_thanh_cu' => DB::raw(
                        '(SELECT full_name FROM tinh_thanh_cu WHERE tinh_thanh_cu.code = doanh_nghieps.tinh_thanh_cu_code)'
                    ),
                ]);
        }

        if (Schema::hasTable('quan_huyen_cu') && Schema::hasColumn('doanh_nghieps', 'quan_huyen_cu_code')) {
            DB::table('doanh_nghieps')
                ->whereNull('quan_huyen_cu')
                ->whereNotNull('quan_huyen_cu_code')
                ->update([
                    'quan_huyen_cu' => DB::raw(
                        '(SELECT full_name FROM quan_huyen_cu WHERE quan_huyen_cu.code = doanh_nghieps.quan_huyen_cu_code)'
                    ),
                ]);
        }

        if (Schema::hasTable('xa_phuong_cu') && Schema::hasColumn('doanh_nghieps', 'xa_phuong_cu_code')) {
            DB::table('doanh_nghieps')
                ->whereNull('xa_phuong_cu')
                ->whereNotNull('xa_phuong_cu_code')
                ->update([
                    'xa_phuong_cu' => DB::raw(
                        '(SELECT full_name FROM xa_phuong_cu WHERE xa_phuong_cu.code = doanh_nghieps.xa_phuong_cu_code)'
                    ),
                ]);
        }

        if (Schema::hasTable('tinh_thanh') && Schema::hasColumn('doanh_nghieps', 'tinh_thanh_code')) {
            DB::table('doanh_nghieps')
                ->whereNull('quan_huyen_moi')
                ->whereNotNull('tinh_thanh_code')
                ->update([
                    'quan_huyen_moi' => DB::raw(
                        '(SELECT full_name FROM tinh_thanh WHERE tinh_thanh.code = doanh_nghieps.tinh_thanh_code)'
                    ),
                ]);
        }

        if (Schema::hasTable('xa_phuong') && Schema::hasColumn('doanh_nghieps', 'xa_phuong_code')) {
            DB::table('doanh_nghieps')
                ->whereNull('xa_phuong_moi')
                ->whereNotNull('xa_phuong_code')
                ->update([
                    'xa_phuong_moi' => DB::raw(
                        '(SELECT full_name FROM xa_phuong WHERE xa_phuong.code = doanh_nghieps.xa_phuong_code)'
                    ),
                ]);
        }

        DB::table('doanh_nghieps')
            ->whereNull('quan_huyen_cu')
            ->whereNotNull('quan_huyen')
            ->update(['quan_huyen_cu' => DB::raw('quan_huyen')]);

        DB::table('doanh_nghieps')
            ->whereNull('xa_phuong_cu')
            ->whereNotNull('phuong_xa')
            ->update(['xa_phuong_cu' => DB::raw('phuong_xa')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('doanh_nghieps')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            foreach (['tinh_thanh_cu', 'quan_huyen_moi', 'quan_huyen_cu', 'xa_phuong_moi', 'xa_phuong_cu'] as $column) {
                if (Schema::hasColumn('doanh_nghieps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
