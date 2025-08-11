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
        Schema::create('c_orderline', function (Blueprint $table) {
            $table->integer('c_orderline_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
            $table->integer('c_order_id');
            $table->integer('m_product_id');
            $table->decimal('qtyordered', 16, 4);
            $table->decimal('qtydelivered', 16, 4);
            $table->decimal('qtyinvoiced', 16, 4);
            $table->decimal('priceactual', 16, 4);

            $table->foreign('c_order_id')->references('c_order_id')->on('c_order');
            $table->foreign('m_product_id')->references('m_product_id')->on('m_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_orderline');
    }
};
