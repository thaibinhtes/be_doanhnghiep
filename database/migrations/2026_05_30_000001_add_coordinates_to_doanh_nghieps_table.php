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
            $table->decimal('long', 11, 8)->nullable()->after('dia_chi');
            $table->decimal('lat', 10, 8)->nullable()->after('long');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropColumn(['long', 'lat']);
        });
    }
};
