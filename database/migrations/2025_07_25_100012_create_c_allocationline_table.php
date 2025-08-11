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
        Schema::create('c_allocationline', function (Blueprint $table) {
            $table->integer('c_allocationline_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->integer('c_allocationhdr_id');
            $table->integer('c_invoice_id')->nullable();
            $table->decimal('amount', 16, 2);
            $table->decimal('discountamt', 16, 2);
            $table->decimal('writeoffamt', 16, 2);
            $table->decimal('overunderamt', 16, 2);

            $table->foreign('c_allocationhdr_id')->references('c_allocationhdr_id')->on('c_allocationhdr');
            $table->foreign('c_invoice_id')->references('c_invoice_id')->on('c_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_allocationline');
    }
};
