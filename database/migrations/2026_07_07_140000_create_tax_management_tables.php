<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tax_units')) {
            Schema::create('tax_units', function (Blueprint $table) {
                $table->id();
                $table->string('unit_code', 50)->unique();
                $table->string('unit_name', 255);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('company_tax_managements')) {
            Schema::create('company_tax_managements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('doanh_nghiep_id')->constrained('doanh_nghieps')->cascadeOnDelete();
                $table->string('tax_code', 50);
                $table->foreignId('tax_unit_id')->constrained('tax_units')->cascadeOnDelete();
                $table->timestamps();

                $table->unique('doanh_nghiep_id');
                $table->index('tax_code');
            });
        }

        if (!Schema::hasTable('cooperative_tax_managements')) {
            Schema::create('cooperative_tax_managements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hop_tac_xa_id')->constrained('hop_tac_xas')->cascadeOnDelete();
                $table->string('tax_code', 50);
                $table->foreignId('tax_unit_id')->constrained('tax_units')->cascadeOnDelete();
                $table->timestamps();

                $table->unique('hop_tac_xa_id');
                $table->index('tax_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_tax_managements');
        Schema::dropIfExists('company_tax_managements');
        Schema::dropIfExists('tax_units');
    }
};
