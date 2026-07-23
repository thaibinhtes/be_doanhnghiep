<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes tuned for dashboard area breakdown + company list filters.
 *
 * Hot paths:
 * - scope by don_vi_id
 * - count/filter by da_cap_nhat_dinh_danh
 * - list ORDER BY created_at DESC
 * - filter dn_trang_thai_id / dn_loai_hinh_id
 * - dashboard LEFT JOIN on *_id (+ text fallback columns)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('doanh_nghieps')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_da_cap_nhat_dinh_danh_index', ['da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_don_vi_dinh_danh_index', ['don_vi_id', 'da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_don_vi_created_at_index', ['don_vi_id', 'created_at']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_don_vi_trang_thai_index', ['don_vi_id', 'dn_trang_thai_id']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_don_vi_loai_hinh_index', ['don_vi_id', 'dn_loai_hinh_id']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_created_at_index', ['created_at']);

            // Dashboard GROUP BY / filter theo id danh mục + đếm định danh
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_xa_cu_id_dinh_danh_index', ['xa_phuong_cu_id', 'da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_xa_moi_id_dinh_danh_index', ['xa_phuong_moi_id', 'da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_huyen_cu_id_dinh_danh_index', ['quan_huyen_cu_id', 'da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_huyen_moi_id_dinh_danh_index', ['quan_huyen_moi_id', 'da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_tinh_cu_id_dinh_danh_index', ['tinh_thanh_cu_id', 'da_cap_nhat_dinh_danh']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_tinh_moi_id_dinh_danh_index', ['tinh_thanh_moi_id', 'da_cap_nhat_dinh_danh']);

            // Text thô dùng fallback join / group-by khi *_id còn null
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_xa_phuong_cu_text_index', ['xa_phuong_cu']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_xa_phuong_moi_text_index', ['xa_phuong_moi']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_quan_huyen_cu_text_index', ['quan_huyen_cu']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_quan_huyen_moi_text_index', ['quan_huyen_moi']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_tinh_thanh_cu_text_index', ['tinh_thanh_cu']);
            $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_tinh_thanh_moi_text_index', ['tinh_thanh_moi']);

            if (Schema::hasColumn('doanh_nghieps', 'tinh_thanh_code')) {
                $this->addIndexIfMissing($table, 'doanh_nghieps', 'dn_tinh_thanh_code_index', ['tinh_thanh_code']);
            }
        });

        // Catalog: match theo loai + ten (dashboard text fallback)
        if (Schema::hasTable('hanh_chinh_phuong_xa')) {
            Schema::table('hanh_chinh_phuong_xa', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'hanh_chinh_phuong_xa', 'hc_px_loai_ten_index', ['loai', 'ten']);
            });
        }

        if (Schema::hasTable('hanh_chinh_quan_huyen')) {
            Schema::table('hanh_chinh_quan_huyen', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'hanh_chinh_quan_huyen', 'hc_qh_loai_ten_index', ['loai', 'ten']);
            });
        }

        if (Schema::hasTable('hanh_chinh_tinh')) {
            Schema::table('hanh_chinh_tinh', function (Blueprint $table) {
                $this->addIndexIfMissing($table, 'hanh_chinh_tinh', 'hc_tinh_loai_ten_index', ['loai', 'ten']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doanh_nghieps')) {
            Schema::table('doanh_nghieps', function (Blueprint $table) {
                foreach ([
                    'dn_da_cap_nhat_dinh_danh_index',
                    'dn_don_vi_dinh_danh_index',
                    'dn_don_vi_created_at_index',
                    'dn_don_vi_trang_thai_index',
                    'dn_don_vi_loai_hinh_index',
                    'dn_created_at_index',
                    'dn_xa_cu_id_dinh_danh_index',
                    'dn_xa_moi_id_dinh_danh_index',
                    'dn_huyen_cu_id_dinh_danh_index',
                    'dn_huyen_moi_id_dinh_danh_index',
                    'dn_tinh_cu_id_dinh_danh_index',
                    'dn_tinh_moi_id_dinh_danh_index',
                    'dn_xa_phuong_cu_text_index',
                    'dn_xa_phuong_moi_text_index',
                    'dn_quan_huyen_cu_text_index',
                    'dn_quan_huyen_moi_text_index',
                    'dn_tinh_thanh_cu_text_index',
                    'dn_tinh_thanh_moi_text_index',
                    'dn_tinh_thanh_code_index',
                ] as $name) {
                    $this->dropIndexIfExists($table, 'doanh_nghieps', $name);
                }
            });
        }

        if (Schema::hasTable('hanh_chinh_phuong_xa')) {
            Schema::table('hanh_chinh_phuong_xa', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'hanh_chinh_phuong_xa', 'hc_px_loai_ten_index');
            });
        }

        if (Schema::hasTable('hanh_chinh_quan_huyen')) {
            Schema::table('hanh_chinh_quan_huyen', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'hanh_chinh_quan_huyen', 'hc_qh_loai_ten_index');
            });
        }

        if (Schema::hasTable('hanh_chinh_tinh')) {
            Schema::table('hanh_chinh_tinh', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'hanh_chinh_tinh', 'hc_tinh_loai_ten_index');
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
