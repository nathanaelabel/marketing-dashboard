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
        Schema::create('c_bpartner', function (Blueprint $table) {
            $table->integer('c_bpartner_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1);
            $table->string('value', 40); // Business Partner code
            $table->string('name', 60); // Business Partner name
            $table->char('iscustomer', 1);
            $table->char('isvendor', 1);
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
        Schema::dropIfExists('c_bpartner');
    }
};
