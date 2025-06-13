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
        Schema::create('national_revenues', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->date('invoice_date');
            $table->decimal('total_revenue', 15, 2);
            $table->timestamps();

            $table->unique(['branch_name', 'invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('national_revenues');
    }
};
