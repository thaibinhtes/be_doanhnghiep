<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tax_import_jobs')) {
            return;
        }

        Schema::table('tax_import_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('tax_import_jobs', 'tax_paid_at')) {
                $table->date('tax_paid_at')->nullable()->after('start_row');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tax_import_jobs')) {
            return;
        }

        Schema::table('tax_import_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('tax_import_jobs', 'tax_paid_at')) {
                $table->dropColumn('tax_paid_at');
            }
        });
    }
};
