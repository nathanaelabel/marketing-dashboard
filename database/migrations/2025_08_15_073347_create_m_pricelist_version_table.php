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
        Schema::create('m_pricelist_version', function (Blueprint $table) {
            $table->integer('m_pricelist_version_id')->primary();
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->integer('createdby');
            $table->string('name', 255);
            $table->timestamps();

            $table->foreign('ad_org_id')->references('ad_org_id')->on('ad_org');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_pricelist_version');
    }
};
