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
        Schema::create('m_product_category', function (Blueprint $table) {
            $table->integer('m_product_category_id')->primary();
            $table->string('value', 40);
            $table->string('name', 60);
            $table->integer('ad_client_id');
            $table->char('isactive', 1)->default('Y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_product_category');
    }
};
