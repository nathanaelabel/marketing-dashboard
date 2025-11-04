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
        Schema::create('m_inout', function (Blueprint $table) {
            $table->bigInteger('m_inout_id')->primary();
            $table->bigInteger('ad_client_id');
            $table->bigInteger('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->char('issotrx', 1)->default('Y');
            $table->string('documentno', 30);
            $table->char('docstatus', 2);
            $table->date('movementdate');
            $table->string('movementtype', 2)->nullable();
            $table->bigInteger('c_order_id')->nullable();
            $table->bigInteger('c_invoice_id')->nullable();
            $table->timestamp('created')->useCurrent();
            $table->timestamp('updated')->useCurrent()->useCurrentOnUpdate();

            // Indexes
            $table->index('ad_org_id');
            $table->index('documentno');
            $table->index('docstatus');
            $table->index('movementdate');
            $table->index('c_order_id');
            $table->index('c_invoice_id');

            // Foreign keys
            $table->foreign('ad_org_id')
                ->references('ad_org_id')
                ->on('ad_org')
                ->onDelete('restrict');

            $table->foreign('c_order_id')
                ->references('c_order_id')
                ->on('c_order')
                ->onDelete('restrict');

            $table->foreign('c_invoice_id')
                ->references('c_invoice_id')
                ->on('c_invoice')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_inout');
    }
};
