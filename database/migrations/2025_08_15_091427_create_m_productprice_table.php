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
        Schema::create('m_productprice', function (Blueprint $table) {
            $table->integer('m_pricelist_version_id');
            $table->integer('m_product_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->decimal('pricelist', 13, 2)->default(0);
            $table->decimal('pricestd', 13, 2)->default(0);
            $table->decimal('pricelimit', 13, 2)->default(0);
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();

            // Composite Primary Key
            $table->primary(['m_pricelist_version_id', 'm_product_id'], 'm_productprice_pkey');

            // Foreign Keys
            $table->foreign('m_pricelist_version_id')->references('m_pricelist_version_id')->on('m_pricelist_version');
            $table->foreign('m_product_id')->references('m_product_id')->on('m_product');
            $table->foreign('ad_org_id')->references('ad_org_id')->on('ad_org');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_productprice');
    }
};
