<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DemoDataClearService
{
    /**
     * Chỉ xóa dữ liệu doanh nghiệp + hợp tác xã (và bảng phụ trợ import/định danh).
     * Thứ tự xóa an toàn FK: bảng con trước.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            'hop_tac_xa_import_job_rows',
            'hop_tac_xa_import_jobs',
            'hop_tac_xas',
            'doanh_nghiep_import_job_rows',
            'doanh_nghiep_import_jobs',
            'dn_dinh_danh_lich_sus',
            'member_companies',
            'doanh_nghieps',
        ];
    }

    /**
     * Danh mục / cấu hình hệ thống — không bao giờ xóa bởi script này.
     *
     * @return list<string>
     */
    public static function preservedCatalogTables(): array
    {
        return [
            'members',
            'users',
            'roles',
            'permissions',
            'permission_role',
            'don_vis',
            'dn_trang_thais',
            'dn_loai_hinhs',
            'danh_muc_nganh_nghes',
            'doanh_nghiep_import_configs',
            'doanh_nghiep_import_formats',
            'hop_tac_xa_import_configs',
            'hop_tac_xa_import_formats',
            'tinh_thanh',
            'xa_phuong',
            'tinh_thanh_cu',
            'quan_huyen_cu',
            'xa_phuong_cu',
            'hanh_chinh_mappings',
            'settings',
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function preview(): array
    {
        $counts = [];
        foreach (self::tables() as $table) {
            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @return array<string, int> deleted row counts per table
     */
    public static function clear(): array
    {
        return DB::transaction(function () {
            $deleted = [];

            foreach (self::tables() as $table) {
                $deleted[$table] = DB::table($table)->delete();
            }

            return $deleted;
        });
    }
}
