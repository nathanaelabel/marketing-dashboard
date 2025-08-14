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
        Schema::create('c_order', function (Blueprint $table) {
            $table->integer('c_order_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->char('issotrx', 1)->default('Y');
            $table->string('documentno', 30);
            $table->string('docstatus', 2);
            $table->timestamp('dateordered')->nullable();
            $table->decimal('grandtotal', 16, 2);
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();

            $table->foreign('ad_org_id')->references('ad_org_id')->on('ad_org');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_order');
    }
};
