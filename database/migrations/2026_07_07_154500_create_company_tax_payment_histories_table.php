<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_tax_payment_histories')) {
            return;
        }

        Schema::create('company_tax_payment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doanh_nghiep_id')->constrained('doanh_nghieps')->cascadeOnDelete();
            $table->foreignId('tax_unit_id')->constrained('tax_units')->cascadeOnDelete();
            $table->string('tax_code', 50);
            $table->date('tax_paid_at');
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('manual');
            $table->timestamps();

            $table->index(['doanh_nghiep_id', 'tax_paid_at']);
            $table->index(['tax_unit_id', 'tax_paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_tax_payment_histories');
    }
};
