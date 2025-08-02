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
        Schema::create('c_allocationhdr', function (Blueprint $table) {
            $table->integer('c_allocationhdr_id')->primary();
            $table->integer('ad_client_id');
            $table->integer('ad_org_id');
            $table->char('isactive', 1)->default('Y');
            $table->string('documentno', 30);
            $table->timestamp('datetrx')->nullable();
            $table->decimal('approvalamt', 16, 2);
            $table->string('docstatus', 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_allocationhdr');
    }
};
