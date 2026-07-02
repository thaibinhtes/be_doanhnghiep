<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->foreignId('dn_trang_thai_id')
                ->nullable()
                ->after('trang_thai')
                ->constrained('dn_trang_thais')
                ->nullOnDelete();
            $table->text('ly_do_trang_thai')->nullable()->after('dn_trang_thai_id');
        });
    }

    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dn_trang_thai_id');
            $table->dropColumn('ly_do_trang_thai');
        });
    }
};
