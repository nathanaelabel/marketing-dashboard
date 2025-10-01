<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to improve Accounts Receivable query performance
        // Note: Using regular CREATE INDEX (not CONCURRENTLY) for compatibility with migrations
        
        Schema::table('c_invoice', function (Blueprint $table) {
            $table->index(['c_bpartner_id', 'dateinvoiced', 'docstatus', 'issotrx', 'isactive'], 'idx_c_invoice_ar_lookup');
        });
        
        Schema::table('c_allocationline', function (Blueprint $table) {
            $table->index(['c_invoice_id', 'c_allocationhdr_id'], 'idx_c_allocationline_invoice');
        });
        
        Schema::table('c_allocationhdr', function (Blueprint $table) {
            $table->index(['datetrx', 'docstatus', 'ad_client_id'], 'idx_c_allocationhdr_datetrx');
        });
        
        Schema::table('c_bpartner', function (Blueprint $table) {
            $table->index(['c_bpartner_id', 'iscustomer', 'isactive'], 'idx_c_bpartner_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('c_invoice', function (Blueprint $table) {
            $table->dropIndex('idx_c_invoice_ar_lookup');
        });
        
        Schema::table('c_allocationline', function (Blueprint $table) {
            $table->dropIndex('idx_c_allocationline_invoice');
        });
        
        Schema::table('c_allocationhdr', function (Blueprint $table) {
            $table->dropIndex('idx_c_allocationhdr_datetrx');
        });
        
        Schema::table('c_bpartner', function (Blueprint $table) {
            $table->dropIndex('idx_c_bpartner_customer');
        });
    }
};
