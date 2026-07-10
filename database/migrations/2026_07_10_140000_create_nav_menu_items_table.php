<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('nav_menu_items')->nullOnDelete();
            $table->string('label');
            $table->string('path')->nullable();
            $table->string('icon')->nullable();
            $table->string('permission_key')->nullable();
            $table->json('permission_keys')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_dashboard')->default(false);
            $table->boolean('is_root_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menu_items');
    }
};
