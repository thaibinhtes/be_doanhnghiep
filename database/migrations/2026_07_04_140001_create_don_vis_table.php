<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('don_vis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('don_vis')
                ->nullOnDelete();
            $table->unsignedTinyInteger('cap')->default(1);
            $table->string('ma', 50);
            $table->string('ten');
            $table->text('mo_ta')->nullable();
            $table->unsignedInteger('thu_tu')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'ma']);
            $table->index(['cap', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('don_vis');
    }
};
