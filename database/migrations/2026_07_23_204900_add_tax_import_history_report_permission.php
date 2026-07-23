<?php

use App\Models\Permission;
use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PermissionRegistry::all() as $permission) {
            Permission::updateOrCreate(
                ['key' => $permission['key']],
                $permission
            );
        }

        $taxImportHistory = Permission::where('key', 'menu.reports.tax-import-history')->first();
        if (! $taxImportHistory) {
            return;
        }

        $sourceKeys = [
            'menu.import-history',
            'menu.admin.tax-units',
            'menu.admin.org-units',
            'menu.reports.identity-history',
            'menu.reports.summary',
        ];

        $roleIds = DB::table('permission_role')
            ->whereIn('permission_id', Permission::whereIn('key', $sourceKeys)->pluck('id'))
            ->pluck('role_id')
            ->unique();

        if ($roleIds->isEmpty()) {
            return;
        }

        $rows = $roleIds
            ->map(fn (int $roleId) => [
                'role_id' => $roleId,
                'permission_id' => $taxImportHistory->id,
            ])
            ->all();

        DB::table('permission_role')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Non-destructive: keep granted permissions on rollback.
    }
};
