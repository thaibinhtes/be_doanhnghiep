<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doanh_nghiep_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('type', 30)->default('companies');
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedSmallInteger('start_row')->nullable();
            $table->json('column_map')->nullable();
            $table->json('value_extensions')->nullable();
            $table->boolean('use_column_map')->default(false);
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doanh_nghiep_import_jobs');
    }
};
