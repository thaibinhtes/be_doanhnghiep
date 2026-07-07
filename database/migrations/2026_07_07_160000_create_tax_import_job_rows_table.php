<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_import_job_rows')) {
            return;
        }

        Schema::create('tax_import_job_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('tax_import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 20);
            $table->string('ma_so_doanh_nghiep', 50)->nullable();
            $table->string('ten_doanh_nghiep')->nullable();
            $table->string('tax_unit_code', 50)->nullable();
            $table->foreignId('doanh_nghiep_id')->nullable()->constrained('doanh_nghieps')->nullOnDelete();
            $table->foreignId('tax_unit_id')->nullable()->constrained('tax_units')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['import_job_id', 'status']);
            $table->index(['import_job_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_import_job_rows');
    }
};
