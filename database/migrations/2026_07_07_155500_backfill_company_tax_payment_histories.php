<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_tax_managements') || !Schema::hasTable('company_tax_payment_histories')) {
            return;
        }

        $rows = DB::table('company_tax_managements')
            ->select([
                'doanh_nghiep_id',
                'tax_unit_id',
                'tax_code',
                DB::raw("coalesce(tax_paid_at, date(coalesce(updated_at, created_at, CURRENT_TIMESTAMP))) as tax_paid_at"),
                'imported_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('company_tax_payment_histories')->insert([
                'doanh_nghiep_id' => $row->doanh_nghiep_id,
                'tax_unit_id' => $row->tax_unit_id,
                'tax_code' => $row->tax_code,
                'tax_paid_at' => $row->tax_paid_at,
                'imported_by_user_id' => $row->imported_by_user_id,
                'source' => 'backfill',
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_tax_payment_histories')) {
            return;
        }

        DB::table('company_tax_payment_histories')
            ->where('source', 'backfill')
            ->delete();
    }
};
