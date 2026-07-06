<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DemoDataClearService
{
    /**
     * Business/demo tables cleared in FK-safe order (children first).
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
            'members',
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
