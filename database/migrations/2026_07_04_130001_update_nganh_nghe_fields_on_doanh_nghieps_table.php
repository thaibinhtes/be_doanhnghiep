<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('danh_muc_nganh_nghes')) {
            return;
        }

        Schema::table('danh_muc_nganh_nghes', function (Blueprint $table) {
            $table->unique('ma');
        });

        DB::table('doanh_nghieps')->update([
            'nganh_nghe_kd_chinh' => null,
            'nganh_nghe_kd' => null,
        ]);

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->string('nganh_nghe_kd_chinh', 20)->nullable()->change();
            $table->json('nganh_nghe_kd')->nullable()->change();
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->foreign('nganh_nghe_kd_chinh')
                ->references('ma')
                ->on('danh_muc_nganh_nghes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropForeign(['nganh_nghe_kd_chinh']);
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->string('nganh_nghe_kd_chinh', 255)->nullable()->change();
            $table->text('nganh_nghe_kd')->nullable()->change();
        });

        Schema::table('danh_muc_nganh_nghes', function (Blueprint $table) {
            $table->dropUnique(['ma']);
        });
    }
};
