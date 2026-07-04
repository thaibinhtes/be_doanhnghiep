<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doanh_nghiep_import_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('don_vi_id')->nullable()->constrained('don_vis')->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('start_row')->default(13);
            $table->json('column_map');
            $table->json('value_extensions')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doanh_nghiep_import_formats');
    }
};
