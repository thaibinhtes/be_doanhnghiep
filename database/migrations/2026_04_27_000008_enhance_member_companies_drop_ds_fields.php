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
        Schema::table('member_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('member_companies', 'date_join')) {
                $table->string('date_join')->nullable()->after('doanh_nghiep_id');
            }
            if (!Schema::hasColumn('member_companies', 'position')) {
                $table->string('position')->nullable()->after('date_join');
            }
            if (!Schema::hasColumn('member_companies', 'investment_amount')) {
                $table->decimal('investment_amount', 20, 2)->nullable()->after('position');
            }
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (Schema::hasColumn('doanh_nghieps', 'ds_thanh_vien_gop_von')) {
                $table->dropColumn('ds_thanh_vien_gop_von');
            }
            if (Schema::hasColumn('doanh_nghieps', 'ds_co_dong')) {
                $table->dropColumn('ds_co_dong');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_companies', function (Blueprint $table) {
            if (Schema::hasColumn('member_companies', 'date_join')) {
                $table->dropColumn('date_join');
            }
            if (Schema::hasColumn('member_companies', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('member_companies', 'investment_amount')) {
                $table->dropColumn('investment_amount');
            }
        });

        Schema::table('doanh_nghieps', function (Blueprint $table) {
            if (!Schema::hasColumn('doanh_nghieps', 'ds_thanh_vien_gop_von')) {
                $table->text('ds_thanh_vien_gop_von')->nullable();
            }
            if (!Schema::hasColumn('doanh_nghieps', 'ds_co_dong')) {
                $table->text('ds_co_dong')->nullable();
            }
        });
    }
};
