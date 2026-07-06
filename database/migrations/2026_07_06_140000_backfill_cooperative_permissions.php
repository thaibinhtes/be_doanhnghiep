<?php

use App\Models\Permission;
use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grant HTX menu/features to roles that already have the matching DN permissions.
     */
    public function up(): void
    {
        foreach (PermissionRegistry::all() as $permission) {
            Permission::updateOrCreate(
                ['key' => $permission['key']],
                $permission
            );
        }

        $pairs = [
            'menu.companies.list' => ['menu.cooperatives.list'],
            'feature.companies.export' => ['feature.cooperatives.export'],
            'feature.companies.import' => ['feature.cooperatives.import'],
        ];

        foreach ($pairs as $sourceKey => $targetKeys) {
            $source = Permission::where('key', $sourceKey)->first();
            if (!$source) {
                continue;
            }

            $roleIds = DB::table('permission_role')
                ->where('permission_id', $source->id)
                ->pluck('role_id');

            if ($roleIds->isEmpty()) {
                continue;
            }

            foreach ($targetKeys as $targetKey) {
                $target = Permission::where('key', $targetKey)->first();
                if (!$target) {
                    continue;
                }

                $rows = $roleIds
                    ->map(fn (int $roleId) => [
                        'role_id' => $roleId,
                        'permission_id' => $target->id,
                    ])
                    ->all();

                DB::table('permission_role')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: keep granted permissions on rollback.
    }
};
