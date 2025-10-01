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
        Schema::create('c_bpartner_location', function (Blueprint $table) {
            $table->integer('c_bpartner_location_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1);
            $table->integer('c_bpartner_id');
            $table->string('name', 60); // Location name (e.g., city)
            $table->char('isshipto', 1)->default('N'); // Is ship to address?
            $table->char('isbillto', 1)->default('N'); // Is bill to address?
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
        Schema::dropIfExists('c_bpartner_location');
    }
};
