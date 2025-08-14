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
        Schema::create('c_invoiceline', function (Blueprint $table) {
            $table->integer('c_invoiceline_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
            $table->integer('c_invoice_id');
            $table->integer('m_product_id')->nullable();
            $table->decimal('qtyinvoiced', 16, 4);
            $table->decimal('priceactual', 16, 4);
            $table->decimal('linenetamt', 16, 2);

            $table->foreign('c_invoice_id')->references('c_invoice_id')->on('c_invoice');
            $table->foreign('m_product_id')->references('m_product_id')->on('m_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_invoiceline');
    }
};
