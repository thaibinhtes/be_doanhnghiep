<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for HTX list filters and tax management list sorts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hop_tac_xas')) {
            Schema::table('hop_tac_xas', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'hop_tac_xas', 'htx_don_vi_dinh_danh_index', ['don_vi_id', 'da_cap_nhat_dinh_danh']);
                $this->addIndexIfMissing($table, 'hop_tac_xas', 'htx_linh_vuc_index', ['linh_vuc']);
                $this->addIndexIfMissing($table, 'hop_tac_xas', 'htx_hoat_dong_index', ['hoat_dong']);
                $this->addIndexIfMissing($table, 'hop_tac_xas', 'htx_created_at_index', ['created_at']);
            });
        }

        if (Schema::hasTable('company_tax_managements')) {
            Schema::table('company_tax_managements', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'company_tax_managements', 'ctm_is_active_created_at_index', ['is_active', 'created_at']);
                $this->addIndexIfMissing($table, 'company_tax_managements', 'ctm_tax_unit_paid_at_index', ['tax_unit_id', 'tax_paid_at']);
                $this->addIndexIfMissing($table, 'company_tax_managements', 'ctm_is_active_paid_at_index', ['is_active', 'tax_paid_at']);
            });
        }

        if (Schema::hasTable('cooperative_tax_managements')) {
            Schema::table('cooperative_tax_managements', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'cooperative_tax_managements', 'cotm_is_active_created_at_index', ['is_active', 'created_at']);
                $this->addIndexIfMissing($table, 'cooperative_tax_managements', 'cotm_tax_unit_paid_at_index', ['tax_unit_id', 'tax_paid_at']);
                $this->addIndexIfMissing($table, 'cooperative_tax_managements', 'cotm_is_active_paid_at_index', ['is_active', 'tax_paid_at']);
            });
        }

        if (Schema::hasTable('dn_dinh_danh_lich_sus')) {
            Schema::table('dn_dinh_danh_lich_sus', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'dn_dinh_danh_lich_sus', 'dn_ddls_created_at_index', ['created_at']);
                $this->addIndexIfMissing($table, 'dn_dinh_danh_lich_sus', 'dn_ddls_nguon_created_at_index', ['nguon', 'created_at']);
                $this->addIndexIfMissing($table, 'dn_dinh_danh_lich_sus', 'dn_ddls_mst_index', ['ma_so_doanh_nghiep']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hop_tac_xas')) {
            Schema::table('hop_tac_xas', function (Blueprint $table) {
                foreach (['htx_don_vi_dinh_danh_index', 'htx_linh_vuc_index', 'htx_hoat_dong_index', 'htx_created_at_index'] as $name) {
                    $this->dropIndexIfExists($table, 'hop_tac_xas', $name);
                }
            });
        }

        if (Schema::hasTable('company_tax_managements')) {
            Schema::table('company_tax_managements', function (Blueprint $table) {
                foreach (['ctm_is_active_created_at_index', 'ctm_tax_unit_paid_at_index', 'ctm_is_active_paid_at_index'] as $name) {
                    $this->dropIndexIfExists($table, 'company_tax_managements', $name);
                }
            });
        }

        if (Schema::hasTable('cooperative_tax_managements')) {
            Schema::table('cooperative_tax_managements', function (Blueprint $table) {
                foreach (['cotm_is_active_created_at_index', 'cotm_tax_unit_paid_at_index', 'cotm_is_active_paid_at_index'] as $name) {
                    $this->dropIndexIfExists($table, 'cooperative_tax_managements', $name);
                }
            });
        }

        if (Schema::hasTable('dn_dinh_danh_lich_sus')) {
            Schema::table('dn_dinh_danh_lich_sus', function (Blueprint $table) {
                foreach (['dn_ddls_created_at_index', 'dn_ddls_nguon_created_at_index', 'dn_ddls_mst_index'] as $name) {
                    $this->dropIndexIfExists($table, 'dn_dinh_danh_lich_sus', $name);
                }
            });
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndexIfMissing(Blueprint $table, string $tableName, string $indexName, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        $table->index($columns, $indexName);
    }

    private function dropIndexIfExists(Blueprint $table, string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        $table->dropIndex($indexName);
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS ok
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$database, $tableName, $indexName],
        );

        return $row !== null;
    }
};
