<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doanh_nghiep_import_job_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('doanh_nghiep_import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 20);
            $table->string('ma_so_doanh_nghiep', 50)->nullable();
            $table->string('ten_doanh_nghiep')->nullable();
            $table->foreignId('doanh_nghiep_id')->nullable()->constrained('doanh_nghieps')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['import_job_id', 'status']);
            $table->index(['import_job_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doanh_nghiep_import_job_rows');
    }
};
