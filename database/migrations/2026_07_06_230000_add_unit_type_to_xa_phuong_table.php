<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xa_phuong', function (Blueprint $table) {
            $table->string('unit_type', 32)->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('xa_phuong', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};
