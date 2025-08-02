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
        Schema::create('m_product', function (Blueprint $table) {
            $table->integer('m_product_id')->primary();
            $table->char('isactive', 1)->default('Y');
            $table->string('name', 255);
            $table->integer('m_product_category_id')->foreign()->references('m_product_category_id')->on('m_product_category');
            $table->integer('m_productsubcat_id')->nullable()->foreign()->references('m_productsubcat_id')->on('m_productsubcat');
            $table->string('group1', 255)->nullable();
            $table->string('status', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_product');
    }
};
