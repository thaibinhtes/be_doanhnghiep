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
            $table->renameColumn('chu_so_huu', 'chu_so_huu_id');
            $table->renameColumn('nguoi_dai_dien', 'nguoi_dai_dien_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->renameColumn('chu_so_huu_id', 'chu_so_huu');
            $table->renameColumn('nguoi_dai_dien_id', 'nguoi_dai_dien');
        });
    }
};
