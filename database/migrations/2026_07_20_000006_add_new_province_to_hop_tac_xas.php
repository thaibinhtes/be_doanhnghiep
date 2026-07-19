<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hop_tac_xas', function (Blueprint $table) {
            $table->string('tinh_thanh_moi')->nullable()->after('tinh_thanh_cu');
        });
    }

    public function down(): void
    {
        Schema::table('hop_tac_xas', function (Blueprint $table) {
            $table->dropColumn('tinh_thanh_moi');
        });
    }
};
