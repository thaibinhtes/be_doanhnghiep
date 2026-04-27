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
        Schema::create('member_companies', function (Blueprint $table) {
            $table->id();
        
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('doanh_nghiep_id');
            $table->string('date_join')->nullable();
            $table->string('position')->nullable();
            $table->decimal('investment_amount', 20, 2)->nullable();
        
            $table->timestamps();
        
            $table->unique(['member_id', 'doanh_nghiep_id']);
        
            $table->foreign('member_id')
                  ->references('id')
                  ->on('members')
                  ->onDelete('cascade');
        
            $table->foreign('doanh_nghiep_id')
                  ->references('id')
                  ->on('doanh_nghieps')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_companies');
    }
};
