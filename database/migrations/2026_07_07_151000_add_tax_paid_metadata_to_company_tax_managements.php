<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_tax_managements')) {
            return;
        }

        Schema::table('company_tax_managements', function (Blueprint $table) {
            if (!Schema::hasColumn('company_tax_managements', 'tax_paid_at')) {
                $table->date('tax_paid_at')->nullable()->after('tax_unit_id');
                $table->index('tax_paid_at');
            }

            if (!Schema::hasColumn('company_tax_managements', 'imported_by_user_id')) {
                $table->foreignId('imported_by_user_id')
                    ->nullable()
                    ->after('tax_paid_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        DB::table('company_tax_managements')
            ->whereNull('tax_paid_at')
            ->update([
                'tax_paid_at' => DB::raw("date(coalesce(created_at, CURRENT_TIMESTAMP))"),
            ]);

        // Đồng bộ trạng thái doanh nghiệp theo việc có đóng thuế hay không.
        DB::table('doanh_nghieps')
            ->whereIn('id', function ($query) {
                $query->select('doanh_nghiep_id')->from('company_tax_managements');
            })
            ->update(['trang_thai' => 'Hoạt động']);

        DB::table('doanh_nghieps')
            ->whereNotIn('id', function ($query) {
                $query->select('doanh_nghiep_id')->from('company_tax_managements');
            })
            ->update(['trang_thai' => 'Không hoạt động']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_tax_managements')) {
            return;
        }

        Schema::table('company_tax_managements', function (Blueprint $table) {
            if (Schema::hasColumn('company_tax_managements', 'imported_by_user_id')) {
                $table->dropConstrainedForeignId('imported_by_user_id');
            }

            if (Schema::hasColumn('company_tax_managements', 'tax_paid_at')) {
                $table->dropIndex(['tax_paid_at']);
                $table->dropColumn('tax_paid_at');
            }
        });
    }
};
