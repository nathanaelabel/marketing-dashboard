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
        Schema::create('m_inoutline', function (Blueprint $table) {
            $table->bigInteger('m_inoutline_id')->primary();
            $table->bigInteger('ad_client_id');
            $table->bigInteger('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->bigInteger('m_inout_id');
            $table->bigInteger('c_orderline_id')->nullable();
            $table->bigInteger('m_product_id');
            $table->decimal('movementqty', 20, 2)->default(0);
            $table->integer('line')->nullable();
            $table->timestamp('created')->useCurrent();
            $table->timestamp('updated')->useCurrent()->useCurrentOnUpdate();

            // Indexes
            $table->index('ad_org_id');
            $table->index('m_inout_id');
            $table->index('c_orderline_id');
            $table->index('m_product_id');

            // Foreign keys
            $table->foreign('ad_org_id')
                ->references('ad_org_id')
                ->on('ad_org')
                ->onDelete('restrict');

            $table->foreign('m_inout_id')
                ->references('m_inout_id')
                ->on('m_inout')
                ->onDelete('cascade');

            $table->foreign('c_orderline_id')
                ->references('c_orderline_id')
                ->on('c_orderline')
                ->onDelete('restrict');

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
        Schema::dropIfExists('m_inoutline');
    }
};
