<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('don_vi_id')
                ->nullable()
                ->after('role_id')
                ->constrained('don_vis')
                ->nullOnDelete();
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->foreignId('don_vi_id')
                ->nullable()
                ->after('loai_dn')
                ->constrained('don_vis')
                ->nullOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('don_vi_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropForeign(['don_vi_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['don_vi_id', 'created_by_user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['don_vi_id']);
            $table->dropColumn('don_vi_id');
        });
    }
};
