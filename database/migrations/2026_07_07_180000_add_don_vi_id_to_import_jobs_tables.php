<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'doanh_nghiep_import_jobs',
        'hop_tac_xa_import_jobs',
        'tax_import_jobs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'don_vi_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('don_vi_id')->nullable()->after('user_id')->constrained('don_vis')->nullOnDelete();
                $table->index('don_vi_id');
            });
        }

        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'don_vi_id')) {
                continue;
            }

            $rows = DB::table($tableName)
                ->select('id', 'user_id')
                ->whereNull('don_vi_id')
                ->get();

            foreach ($rows as $row) {
                $donViId = DB::table('users')->where('id', $row->user_id)->value('don_vi_id');
                if ($donViId !== null) {
                    DB::table($tableName)->where('id', $row->id)->update(['don_vi_id' => $donViId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'don_vi_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['don_vi_id']);
                $table->dropIndex(['don_vi_id']);
                $table->dropColumn('don_vi_id');
            });
        }
    }
};
