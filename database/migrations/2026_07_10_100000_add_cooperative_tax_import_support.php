<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cooperative_tax_managements')) {
            Schema::table('cooperative_tax_managements', function (Blueprint $table) {
                if (!Schema::hasColumn('cooperative_tax_managements', 'tax_paid_at')) {
                    $table->date('tax_paid_at')->nullable()->after('tax_unit_id');
                }
                if (!Schema::hasColumn('cooperative_tax_managements', 'imported_by_user_id')) {
                    $table->foreignId('imported_by_user_id')->nullable()->after('tax_paid_at')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('cooperative_tax_managements', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('imported_by_user_id');
                    $table->index('is_active');
                }
            });

            if (Schema::hasColumn('cooperative_tax_managements', 'is_active')) {
                DB::table('cooperative_tax_managements')->update(['is_active' => true]);
            }
        }

        if (!Schema::hasTable('cooperative_tax_payment_histories')) {
            Schema::create('cooperative_tax_payment_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hop_tac_xa_id')->constrained('hop_tac_xas')->cascadeOnDelete();
                $table->foreignId('tax_unit_id')->constrained('tax_units')->cascadeOnDelete();
                $table->string('tax_code', 50);
                $table->date('tax_paid_at');
                $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source', 30)->default('manual');
                $table->timestamps();

                $table->index(['hop_tac_xa_id', 'tax_paid_at'], 'coop_tax_hist_htx_paid_idx');
                $table->index(['tax_unit_id', 'tax_paid_at'], 'coop_tax_hist_unit_paid_idx');
            });
        } else {
            // Resume after a failed MySQL run that created the table but not the indexes
            // (auto-generated index names exceeded MySQL's 64-char limit).
            Schema::table('cooperative_tax_payment_histories', function (Blueprint $table) {
                $sm = Schema::getConnection()->getSchemaBuilder();
                $indexes = collect($sm->getIndexes('cooperative_tax_payment_histories'))
                    ->pluck('name')
                    ->all();

                if (! in_array('coop_tax_hist_htx_paid_idx', $indexes, true)) {
                    $table->index(['hop_tac_xa_id', 'tax_paid_at'], 'coop_tax_hist_htx_paid_idx');
                }
                if (! in_array('coop_tax_hist_unit_paid_idx', $indexes, true)) {
                    $table->index(['tax_unit_id', 'tax_paid_at'], 'coop_tax_hist_unit_paid_idx');
                }
            });
        }

        if (Schema::hasTable('tax_import_job_rows') && !Schema::hasColumn('tax_import_job_rows', 'hop_tac_xa_id')) {
            Schema::table('tax_import_job_rows', function (Blueprint $table) {
                $table->foreignId('hop_tac_xa_id')->nullable()->after('doanh_nghiep_id')->constrained('hop_tac_xas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_import_job_rows') && Schema::hasColumn('tax_import_job_rows', 'hop_tac_xa_id')) {
            Schema::table('tax_import_job_rows', function (Blueprint $table) {
                $table->dropConstrainedForeignId('hop_tac_xa_id');
            });
        }

        Schema::dropIfExists('cooperative_tax_payment_histories');

        if (Schema::hasTable('cooperative_tax_managements')) {
            Schema::table('cooperative_tax_managements', function (Blueprint $table) {
                if (Schema::hasColumn('cooperative_tax_managements', 'is_active')) {
                    $table->dropIndex(['is_active']);
                    $table->dropColumn('is_active');
                }
                if (Schema::hasColumn('cooperative_tax_managements', 'imported_by_user_id')) {
                    $table->dropConstrainedForeignId('imported_by_user_id');
                }
                if (Schema::hasColumn('cooperative_tax_managements', 'tax_paid_at')) {
                    $table->dropColumn('tax_paid_at');
                }
            });
        }
    }
};
