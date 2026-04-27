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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('cccd')->unique()->nullable();
            $table->string('full_name');
            $table->string('birthday')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('full_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
