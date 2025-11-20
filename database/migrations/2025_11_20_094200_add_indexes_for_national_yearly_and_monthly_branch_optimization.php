<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Indexes tambahan untuk optimasi NationalYearlyController dan MonthlyBranchController:
     * - Kedua controller ini menggunakan query yang mirip dengan ReturnComparisonController
     * - Sebagian besar index sudah dibuat di migration sebelumnya (2025_11_20_091100)
     * - Migration ini hanya menambahkan index spesifik yang belum ada
     */
    public function up(): void
    {
        // ============================================================
        // 1. c_invoice - Index untuk date range queries
        // ============================================================

        // Composite index untuk filter date range + category
        // Ini akan sangat membantu query yang filter berdasarkan dateinvoiced BETWEEN
        Schema::table('c_invoice', function (Blueprint $table) {
            // Index untuk date range queries (NationalYearly & MonthlyBranch)
            $table->index(['dateinvoiced', 'ad_client_id', 'issotrx', 'docstatus', 'isactive'], 'idx_cinvoice_daterange');

            // Index untuk EXTRACT(month) dan EXTRACT(year) operations
            // PostgreSQL dapat menggunakan index ini untuk date extraction
            $table->index(['dateinvoiced', 'ad_org_id'], 'idx_cinvoice_date_org');
        });

        // Partial index khusus untuk INC documents dengan date range
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cinvoice_inc_daterange 
            ON c_invoice (dateinvoiced, ad_org_id, ad_client_id) 
            WHERE documentno LIKE \'INC%\' AND issotrx = \'Y\' AND docstatus IN (\'CO\', \'CL\') AND isactive = \'Y\'');

        // Partial index khusus untuk CNC documents dengan date range (untuk NETTO queries)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cinvoice_cnc_daterange 
            ON c_invoice (dateinvoiced, ad_org_id, ad_client_id) 
            WHERE documentno LIKE \'CNC%\' AND issotrx = \'Y\' AND docstatus IN (\'CO\', \'CL\') AND isactive = \'Y\'');

        // Partial index untuk INC dan CNC combined (NETTO type)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cinvoice_inc_cnc_daterange 
            ON c_invoice (dateinvoiced, ad_org_id, ad_client_id, documentno) 
            WHERE SUBSTR(documentno, 1, 3) IN (\'INC\', \'CNC\') AND issotrx = \'Y\' AND docstatus IN (\'CO\', \'CL\') AND isactive = \'Y\'');

        // ============================================================
        // 2. ad_org - Index untuk branch filtering
        // ============================================================

        // Index untuk filter by specific branch name (MonthlyBranch)
        Schema::table('ad_org', function (Blueprint $table) {
            $table->index('name', 'idx_adorg_name_only');
        });

        // Partial index untuk exclude HEAD OFFICE
        DB::statement('CREATE INDEX IF NOT EXISTS idx_adorg_not_headoffice 
            ON ad_org (ad_org_id, name) 
            WHERE name NOT LIKE \'%HEAD OFFICE%\'');

        // ============================================================
        // 3. c_invoiceline - Index tambahan untuk aggregation
        // ============================================================

        Schema::table('c_invoiceline', function (Blueprint $table) {
            // Index untuk SUM(linenetamt) aggregation dengan filter
            $table->index(['c_invoice_id', 'qtyinvoiced', 'linenetamt'], 'idx_cinvoiceline_aggregation');
        });

        // ============================================================
        // 4. m_product - Index untuk JOIN optimization
        // ============================================================

        // Index sudah ada di migration sebelumnya, tapi kita tambahkan covering index
        Schema::table('m_product', function (Blueprint $table) {
            // Covering index untuk menghindari lookup ke table
            $table->index(['m_product_id', 'm_product_category_id', 'name'], 'idx_mproduct_covering');
        });

        // ============================================================
        // 5. c_bpartner - Index untuk UPPER(name) NOT LIKE '%KARYAWAN%'
        // ============================================================

        // Functional index untuk UPPER(name) - PostgreSQL specific
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cbpartner_upper_name 
            ON c_bpartner (UPPER(name))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial indexes (created with DB::statement)
        DB::statement('DROP INDEX IF EXISTS idx_cinvoice_inc_daterange');
        DB::statement('DROP INDEX IF EXISTS idx_cinvoice_cnc_daterange');
        DB::statement('DROP INDEX IF EXISTS idx_cinvoice_inc_cnc_daterange');
        DB::statement('DROP INDEX IF EXISTS idx_adorg_not_headoffice');
        DB::statement('DROP INDEX IF EXISTS idx_cbpartner_upper_name');

        // Drop regular indexes
        Schema::table('c_invoice', function (Blueprint $table) {
            $table->dropIndex('idx_cinvoice_daterange');
            $table->dropIndex('idx_cinvoice_date_org');
        });

        Schema::table('ad_org', function (Blueprint $table) {
            $table->dropIndex('idx_adorg_name_only');
        });

        Schema::table('c_invoiceline', function (Blueprint $table) {
            $table->dropIndex('idx_cinvoiceline_aggregation');
        });

        Schema::table('m_product', function (Blueprint $table) {
            $table->dropIndex('idx_mproduct_covering');
        });
    }
};
