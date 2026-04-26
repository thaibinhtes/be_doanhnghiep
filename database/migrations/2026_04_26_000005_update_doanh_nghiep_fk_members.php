<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropColumn('ngay_sinh_nguoi_dai_dien');
            $table->dropColumn('chu_so_huu');
            $table->dropColumn('nguoi_dai_dien');
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->foreignId('chu_so_huu')->nullable()->constrained('members')->onDelete('set null');
            $table->foreignId('nguoi_dai_dien')->nullable()->constrained('members')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropForeign(['chu_so_huu']);
            $table->dropForeign(['nguoi_dai_dien']);
            $table->dropColumn('chu_so_huu');
            $table->dropColumn('nguoi_dai_dien');
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->string('nguoi_dai_dien')->nullable();
            $table->string('ngay_sinh_nguoi_dai_dien')->nullable();
            $table->string('chu_so_huu')->nullable();
        });
    }
};
