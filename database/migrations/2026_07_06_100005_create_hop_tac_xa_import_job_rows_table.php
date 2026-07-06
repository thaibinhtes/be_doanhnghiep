<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hop_tac_xa_import_job_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('hop_tac_xa_import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 20);
            $table->string('ma_so_thue', 50)->nullable();
            $table->string('ten_htx')->nullable();
            $table->foreignId('hop_tac_xa_id')->nullable()->constrained('hop_tac_xas')->nullOnDelete();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['import_job_id', 'status']);
            $table->index(['import_job_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hop_tac_xa_import_job_rows');
    }
};
