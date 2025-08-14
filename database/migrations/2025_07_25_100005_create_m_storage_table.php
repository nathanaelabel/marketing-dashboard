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
        Schema::create('m_storage', function (Blueprint $table) {
            $table->integer('m_product_id');
            $table->integer('m_locator_id');
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->decimal('qtyonhand', 16, 4);
            $table->integer('m_attributesetinstance_id')->nullable();

            $table->foreign('ad_org_id')->references('ad_org_id')->on('ad_org');
            // Primary key now only contains essential columns
            $table->primary(['m_product_id', 'm_locator_id'], 'm_storage_pkey');

            // Add individual indexes for performance on lookups
            $table->index('m_product_id');
            $table->index('m_locator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_storage');
    }
};
