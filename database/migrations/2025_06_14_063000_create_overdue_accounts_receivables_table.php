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
        Schema::create('overdue_accounts_receivables', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->date('calculation_date'); // Tanggal kalkulasi piutang
            $table->decimal('days_1_30_overdue_amount', 15, 2)->default(0);
            $table->decimal('days_31_60_overdue_amount', 15, 2)->default(0);
            $table->decimal('days_61_90_overdue_amount', 15, 2)->default(0);
            $table->decimal('days_over_90_overdue_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['branch_name', 'calculation_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overdue_accounts_receivables');
    }
};
