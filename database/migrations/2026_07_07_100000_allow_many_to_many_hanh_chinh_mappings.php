<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable();

            return;
        }

        Schema::table('hanh_chinh_mappings', function (Blueprint $table) {
            $table->dropUnique(['xa_phuong_cu_code']);
            $table->unique(
                ['xa_phuong_cu_code', 'xa_phuong_moi_code'],
                'hanh_chinh_mappings_cu_moi_unique',
            );
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(restoreOneToOne: true);

            return;
        }

        Schema::table('hanh_chinh_mappings', function (Blueprint $table) {
            $table->dropUnique('hanh_chinh_mappings_cu_moi_unique');
            $table->unique('xa_phuong_cu_code');
        });
    }

    private function rebuildSqliteTable(bool $restoreOneToOne = false): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('hanh_chinh_mappings_tmp', function (Blueprint $table) use ($restoreOneToOne) {
            $table->id();
            $table->unsignedInteger('group_no')->nullable();
            $table->string('xa_phuong_cu_code', 32);
            $table->string('xa_phuong_moi_code', 20);
            $table->string('new_unit_type', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('xa_phuong_cu_code')
                ->references('code')
                ->on('xa_phuong_cu')
                ->cascadeOnDelete();

            $table->foreign('xa_phuong_moi_code')
                ->references('code')
                ->on('xa_phuong')
                ->cascadeOnDelete();

            if ($restoreOneToOne) {
                $table->unique('xa_phuong_cu_code');
            } else {
                $table->unique(
                    ['xa_phuong_cu_code', 'xa_phuong_moi_code'],
                    'hanh_chinh_mappings_cu_moi_unique',
                );
            }

            $table->index('xa_phuong_moi_code');
        });

        if ($restoreOneToOne) {
            DB::statement('
                INSERT INTO hanh_chinh_mappings_tmp (id, group_no, xa_phuong_cu_code, xa_phuong_moi_code, new_unit_type, notes, created_at, updated_at)
                SELECT m.id, m.group_no, m.xa_phuong_cu_code, m.xa_phuong_moi_code, m.new_unit_type, m.notes, m.created_at, m.updated_at
                FROM hanh_chinh_mappings m
                INNER JOIN (
                    SELECT xa_phuong_cu_code, MIN(id) AS keep_id
                    FROM hanh_chinh_mappings
                    GROUP BY xa_phuong_cu_code
                ) kept ON kept.keep_id = m.id
            ');
        } else {
            DB::statement('
                INSERT INTO hanh_chinh_mappings_tmp (id, group_no, xa_phuong_cu_code, xa_phuong_moi_code, new_unit_type, notes, created_at, updated_at)
                SELECT id, group_no, xa_phuong_cu_code, xa_phuong_moi_code, new_unit_type, notes, created_at, updated_at
                FROM hanh_chinh_mappings
            ');
        }

        Schema::drop('hanh_chinh_mappings');
        Schema::rename('hanh_chinh_mappings_tmp', 'hanh_chinh_mappings');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
