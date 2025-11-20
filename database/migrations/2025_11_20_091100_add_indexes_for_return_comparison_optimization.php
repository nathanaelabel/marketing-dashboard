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
     * Indexes untuk optimasi Return Comparison queries:
     * - getAllSalesBruto
     * - getAllCNCData
     * - getAllBarangData
     * - getAllCabangPabrikData
     */
    public function up(): void
    {
        // ============================================================
        // 1. c_invoice - Untuk getAllSalesBruto, getAllCNCData, getAllCabangPabrikData
        // ============================================================

        // Index untuk getAllSalesBruto (INC%)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cinvoice_salesbruto 
            ON c_invoice (ad_client_id, issotrx, docstatus, isactive, dateinvoiced, documentno) 
            WHERE documentno LIKE \'INC%\'');

        // Index untuk getAllCNCData (CNC%)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cinvoice_cnc 
            ON c_invoice (issotrx, docstatus, dateinvoiced, documentno) 
            WHERE documentno LIKE \'CNC%\'');

        // Index untuk getAllCabangPabrikData (DNS-%, NCS-%)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cinvoice_cabangpabrik 
            ON c_invoice (issotrx, docstatus, isactive, dateinvoiced, documentno) 
            WHERE documentno LIKE \'DNS-%\' OR documentno LIKE \'NCS-%\'');

        // Index untuk JOIN operations
        Schema::table('c_invoice', function (Blueprint $table) {
            $table->index(['c_invoice_id', 'ad_org_id'], 'idx_cinvoice_join');
            $table->index(['c_invoice_id', 'c_bpartner_id'], 'idx_cinvoice_bpartner');
        });

        // ============================================================
        // 2. c_invoiceline - Untuk semua query
        // ============================================================
        Schema::table('c_invoiceline', function (Blueprint $table) {
            // Index untuk JOIN ke c_invoice
            $table->index(['c_invoice_id', 'm_product_id'], 'idx_cinvoiceline_invoice_product');

            // Index untuk JOIN ke m_inoutline (getAllCNCData, getAllCabangPabrikData)
            $table->index(['m_inoutline_id', 'c_invoice_id'], 'idx_cinvoiceline_inoutline');

            // Index untuk EXISTS subquery di getAllBarangData
            $table->index('m_inoutline_id', 'idx_cinvoiceline_inoutline_exists');
        });

        // ============================================================
        // 3. m_inoutline - Untuk getAllBarangData, getAllCabangPabrikData
        // ============================================================
        Schema::table('m_inoutline', function (Blueprint $table) {
            // Index untuk JOIN operations
            $table->index(['m_inout_id', 'c_orderline_id'], 'idx_minoutline_inout_order');
            $table->index(['m_inout_id', 'ad_org_id'], 'idx_minoutline_inout_org');

            // Index untuk EXISTS subquery di getAllCNCData (m_locator_id sudah ada index dari migration sebelumnya)
            $table->index(['m_inoutline_id', 'm_locator_id'], 'idx_minoutline_locator_exists');
        });

        // ============================================================
        // 4. m_inout - Untuk getAllBarangData, getAllCabangPabrikData
        // ============================================================

        // Index untuk getAllBarangData (SJC%)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_minout_barang 
            ON m_inout (docstatus, movementdate, documentno) 
            WHERE documentno LIKE \'SJC%\'');

        // Index untuk getAllCabangPabrikData
        Schema::table('m_inout', function (Blueprint $table) {
            $table->index(['m_inout_id', 'docstatus'], 'idx_minout_join');
        });

        // ============================================================
        // 5. m_product - Untuk semua query
        // ============================================================
        Schema::table('m_product', function (Blueprint $table) {
            $table->index(['m_product_id', 'm_product_category_id'], 'idx_mproduct_category');
        });

        // ============================================================
        // 6. m_product_category - Untuk filter MIKA
        // ============================================================
        Schema::table('m_product_category', function (Blueprint $table) {
            $table->index(['m_product_category_id', 'name'], 'idx_mproductcat_name');
        });

        // Partial index untuk kategori MIKA
        DB::statement('CREATE INDEX IF NOT EXISTS idx_mproductcat_mika 
            ON m_product_category (m_product_category_id) 
            WHERE name = \'MIKA\'');

        // ============================================================
        // 7. m_locator - Untuk getAllCNCData EXISTS subquery
        // ============================================================

        // Partial index untuk Gudang Rusak
        DB::statement('CREATE INDEX IF NOT EXISTS idx_mlocator_gudangrusak 
            ON m_locator (m_locator_id, value) 
            WHERE value LIKE \'Gudang Rusak%\' OR value LIKE \'Gudang Barang Rusak%\'');

        // ============================================================
        // 8. c_orderline - Untuk getAllBarangData
        // ============================================================
        Schema::table('c_orderline', function (Blueprint $table) {
            $table->index(['c_orderline_id', 'c_order_id'], 'idx_corderline_order');
            $table->index(['c_orderline_id', 'm_product_id'], 'idx_corderline_product');
        });

        // ============================================================
        // 9. c_order - Untuk getAllBarangData
        // ============================================================

        // Partial index untuk SOC%
        DB::statement('CREATE INDEX IF NOT EXISTS idx_corder_barang 
            ON c_order (c_order_id, docstatus, documentno) 
            WHERE documentno LIKE \'SOC%\' AND docstatus = \'CL\'');

        // ============================================================
        // 10. c_bpartner - Untuk getAllSalesBruto
        // ============================================================
        Schema::table('c_bpartner', function (Blueprint $table) {
            $table->index(['c_bpartner_id', 'name'], 'idx_cbpartner_name');
        });

        // Partial index untuk exclude KARYAWAN
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cbpartner_not_karyawan 
            ON c_bpartner (c_bpartner_id) 
            WHERE UPPER(name) NOT LIKE \'%KARYAWAN%\'');

        // ============================================================
        // 11. ad_org - Untuk GROUP BY di semua query
        // ============================================================
        Schema::table('ad_org', function (Blueprint $table) {
            $table->index(['ad_org_id', 'name'], 'idx_adorg_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial indexes (created with DB::statement)
        DB::statement('DROP INDEX IF EXISTS idx_cinvoice_salesbruto');
        DB::statement('DROP INDEX IF EXISTS idx_cinvoice_cnc');
        DB::statement('DROP INDEX IF EXISTS idx_cinvoice_cabangpabrik');
        DB::statement('DROP INDEX IF EXISTS idx_minout_barang');
        DB::statement('DROP INDEX IF EXISTS idx_mproductcat_mika');
        DB::statement('DROP INDEX IF EXISTS idx_mlocator_gudangrusak');
        DB::statement('DROP INDEX IF EXISTS idx_corder_barang');
        DB::statement('DROP INDEX IF EXISTS idx_cbpartner_not_karyawan');

        // Drop regular indexes
        Schema::table('c_invoice', function (Blueprint $table) {
            $table->dropIndex('idx_cinvoice_join');
            $table->dropIndex('idx_cinvoice_bpartner');
        });

        Schema::table('c_invoiceline', function (Blueprint $table) {
            $table->dropIndex('idx_cinvoiceline_invoice_product');
            $table->dropIndex('idx_cinvoiceline_inoutline');
            $table->dropIndex('idx_cinvoiceline_inoutline_exists');
        });

        Schema::table('m_inoutline', function (Blueprint $table) {
            $table->dropIndex('idx_minoutline_inout_order');
            $table->dropIndex('idx_minoutline_inout_org');
            $table->dropIndex('idx_minoutline_locator_exists');
        });

        Schema::table('m_inout', function (Blueprint $table) {
            $table->dropIndex('idx_minout_join');
        });

        Schema::table('m_product', function (Blueprint $table) {
            $table->dropIndex('idx_mproduct_category');
        });

        Schema::table('m_product_category', function (Blueprint $table) {
            $table->dropIndex('idx_mproductcat_name');
        });

        Schema::table('c_orderline', function (Blueprint $table) {
            $table->dropIndex('idx_corderline_order');
            $table->dropIndex('idx_corderline_product');
        });

        Schema::table('c_bpartner', function (Blueprint $table) {
            $table->dropIndex('idx_cbpartner_name');
        });

        Schema::table('ad_org', function (Blueprint $table) {
            $table->dropIndex('idx_adorg_name');
        });
    }
};
