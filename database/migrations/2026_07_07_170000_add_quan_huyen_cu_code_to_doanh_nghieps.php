<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('doanh_nghieps')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (!Schema::hasColumn('doanh_nghieps', 'quan_huyen_cu_code')) {
                $table->string('quan_huyen_cu_code', 32)->nullable()->after('phuong_xa');
                $table->index('quan_huyen_cu_code');
            }
        });

        if (Schema::hasTable('quan_huyen_cu')) {
            Schema::table('doanh_nghieps', function (Blueprint $table) {
                if (Schema::hasColumn('doanh_nghieps', 'quan_huyen_cu_code')) {
                    $table->foreign('quan_huyen_cu_code')
                        ->references('code')
                        ->on('quan_huyen_cu')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('doanh_nghieps') || !Schema::hasColumn('doanh_nghieps', 'quan_huyen_cu_code')) {
            return;
        }

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropForeign(['quan_huyen_cu_code']);
            $table->dropIndex(['quan_huyen_cu_code']);
            $table->dropColumn('quan_huyen_cu_code');
        });
    }
};
