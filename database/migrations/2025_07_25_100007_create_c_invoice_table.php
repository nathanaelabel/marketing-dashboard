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
        Schema::create('c_invoice', function (Blueprint $table) {
            $table->integer('c_invoice_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->integer('c_bpartner_id');
            $table->char('isactive', 1);
            $table->char('issotrx', 1);
            $table->string('documentno', 30);
            $table->string('docstatus', 2);
            $table->timestamp('dateinvoiced');
            $table->decimal('totallines', 16, 2);
            $table->decimal('grandtotal', 16, 2);
            $table->char('ispaid', 1);
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();

            $table->foreign('ad_org_id')->references('ad_org_id')->on('ad_org');
            // Foreign key to c_bpartner removed due to cross-branch references in multi-source sync
            // $table->foreign('c_bpartner_id')->references('c_bpartner_id')->on('c_bpartner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_invoice');
    }
};
