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
        Schema::create('m_matchinv', function (Blueprint $table) {
            $table->bigInteger('m_matchinv_id')->primary();
            $table->bigInteger('ad_client_id');
            $table->bigInteger('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->bigInteger('c_invoiceline_id');
            $table->bigInteger('m_inoutline_id')->nullable();
            $table->bigInteger('m_product_id');
            $table->decimal('qty', 20, 2)->default(0);
            $table->date('datetrx')->nullable();
            $table->timestamp('created')->useCurrent();
            $table->timestamp('updated')->useCurrent()->useCurrentOnUpdate();

            // Indexes
            $table->index('ad_org_id');
            $table->index('c_invoiceline_id');
            $table->index('m_inoutline_id');
            $table->index('m_product_id');
            $table->index('datetrx');

            // Foreign keys
            $table->foreign('ad_org_id')
                ->references('ad_org_id')
                ->on('ad_org')
                ->onDelete('restrict');

            $table->foreign('c_invoiceline_id')
                ->references('c_invoiceline_id')
                ->on('c_invoiceline')
                ->onDelete('cascade');

            $table->foreign('m_inoutline_id')
                ->references('m_inoutline_id')
                ->on('m_inoutline')
                ->onDelete('cascade');

            $table->foreign('m_product_id')
                ->references('m_product_id')
                ->on('m_product')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_matchinv');
    }
};
