<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_tax_managements') && !Schema::hasColumn('company_tax_managements', 'is_active')) {
            Schema::table('company_tax_managements', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('imported_by_user_id');
                $table->index('is_active');
            });

            DB::table('company_tax_managements')->update(['is_active' => true]);
        }

        if (Schema::hasTable('tax_import_job_rows') && !Schema::hasColumn('tax_import_job_rows', 'mapped_values')) {
            Schema::table('tax_import_job_rows', function (Blueprint $table) {
                $table->json('mapped_values')->nullable()->after('message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_tax_managements') && Schema::hasColumn('company_tax_managements', 'is_active')) {
            Schema::table('company_tax_managements', function (Blueprint $table) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasTable('tax_import_job_rows') && Schema::hasColumn('tax_import_job_rows', 'mapped_values')) {
            Schema::table('tax_import_job_rows', function (Blueprint $table) {
                $table->dropColumn('mapped_values');
            });
        }
    }
};
