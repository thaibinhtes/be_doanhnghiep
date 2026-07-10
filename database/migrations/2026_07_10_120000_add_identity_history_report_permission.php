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

        $identityHistory = Permission::where('key', 'menu.reports.identity-history')->first();
        if (!$identityHistory) {
            return;
        }

        $sourceKeys = [
            'menu.reports.progress',
            'menu.companies.identity',
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
                'permission_id' => $identityHistory->id,
            ])
            ->all();

        DB::table('permission_role')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Non-destructive: keep granted permissions on rollback.
    }
};
