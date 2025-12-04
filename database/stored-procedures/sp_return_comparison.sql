-- Stored Procedures untuk Return Comparison
--
-- ===================================================================
-- 1. SP untuk Sales Bruto (dengan Validasi, Error Handling & Logging)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_return_comparison_sales_bruto (p_year INTEGER, p_month INTEGER) RETURNS TABLE (branch_name VARCHAR, total_rp NUMERIC) LANGUAGE plpgsql AS $$ DECLARE v_start_time TIMESTAMP;

v_row_count INTEGER;

BEGIN 
-- ==========================
-- Step 1: Validasi Parameter
-- ==========================
IF p_year IS NULL
OR p_month IS NULL THEN RAISE EXCEPTION 'Parameter p_year dan p_month tidak boleh NULL';

END IF;

IF p_month < 1
OR p_month > 12 THEN RAISE EXCEPTION 'Parameter p_month harus antara 1-12, diberikan: %',
p_month;

END IF;

IF p_year < 2020
OR p_year > 2030 THEN RAISE EXCEPTION 'Parameter p_year harus antara 2020-2030, diberikan: %',
p_year;

END IF;

v_start_time := clock_timestamp();

RAISE NOTICE '[sp_get_return_comparison_sales_bruto] Mulai eksekusi untuk periode: %-% ',
p_year,
p_month;

-- ==============================
-- Step 2: Query Data Sales Bruto
-- ==============================
RETURN QUERY
SELECT
    org.name :: VARCHAR as branch_name,
    COALESCE(SUM(invl.linenetamt), 0) :: NUMERIC as total_rp
FROM
    c_invoice inv
    INNER JOIN c_invoiceline invl ON inv.c_invoice_id = invl.c_invoice_id
    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
    INNER JOIN c_bpartner cust ON inv.c_bpartner_id = cust.c_bpartner_id
    INNER JOIN m_product p ON invl.m_product_id = p.m_product_id
    INNER JOIN m_product_category pc ON p.m_product_category_id = pc.m_product_category_id
    LEFT JOIN m_productsubcat psc ON p.m_productsubcat_id = psc.m_productsubcat_id
WHERE
    inv.ad_client_id = 1000001
    AND inv.issotrx = 'Y'
    AND invl.qtyinvoiced > 0
    AND invl.linenetamt > 0
    AND inv.docstatus IN ('CO', 'CL')
    AND inv.isactive = 'Y'
    AND EXTRACT(
        year
        FROM
            inv.dateinvoiced
    ) = p_year
    AND EXTRACT(
        month
        FROM
            inv.dateinvoiced
    ) = p_month
    AND inv.documentno LIKE 'INC%'
    AND (
        pc.value = 'MIKA'
        OR (
            pc.value = 'PRODUCT IMPORT'
            AND p.name NOT LIKE '%BOHLAM%'
            AND psc.value = 'MIKA'
        )
        OR (
            pc.value = 'PRODUCT IMPORT'
            AND (
                p.name LIKE '%FILTER UDARA%'
                OR p.name LIKE '%SWITCH REM%'
                OR p.name LIKE '%DOP RITING%'
            )
        )
    )
    AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
GROUP BY
    org.name;

-- =====================
-- Step 3: Logging hasil
-- =====================
GET DIAGNOSTICS v_row_count = ROW_COUNT;

RAISE NOTICE '[sp_get_return_comparison_sales_bruto] Selesai. Rows: %, Durasi: % ms',
v_row_count,
EXTRACT(
    MILLISECOND
    FROM
        (clock_timestamp() - v_start_time)
);

EXCEPTION
WHEN OTHERS THEN RAISE NOTICE '[sp_get_return_comparison_sales_bruto] ERROR!';

RAISE NOTICE 'Error Message: %',
SQLERRM;

RAISE NOTICE 'Error State: %',
SQLSTATE;

RAISE;

END;

$$;

-- ====================
-- 2. SP untuk CNC Data
-- ====================
CREATE OR REPLACE FUNCTION sp_get_return_comparison_cnc (p_year INTEGER, p_month INTEGER) RETURNS TABLE (
    branch_name VARCHAR,
    total_qty NUMERIC,
    total_rp NUMERIC
) LANGUAGE plpgsql AS $$ DECLARE v_start_time TIMESTAMP;

v_row_count INTEGER;

BEGIN 
-- ==========================
-- Step 1: Validasi Parameter
-- ==========================
IF p_year IS NULL
OR p_month IS NULL THEN RAISE EXCEPTION 'Parameter p_year dan p_month tidak boleh NULL';

END IF;

IF p_month < 1
OR p_month > 12 THEN RAISE EXCEPTION 'Parameter p_month harus antara 1-12, diberikan: %',
p_month;

END IF;

IF p_year < 2020
OR p_year > 2030 THEN RAISE EXCEPTION 'Parameter p_year harus antara 2020-2030, diberikan: %',
p_year;

END IF;

v_start_time := clock_timestamp();

RAISE NOTICE '[sp_get_return_comparison_cnc] Mulai eksekusi untuk periode: %-% ',
p_year,
p_month;

-- ======================
-- Step 2: Query Data CNC
-- ======================
RETURN QUERY
SELECT
    org.name :: VARCHAR as branch_name,
    COALESCE(SUM(d.qtyinvoiced), 0) :: NUMERIC as total_qty,
    COALESCE(SUM(d.linenetamt), 0) :: NUMERIC as total_rp
FROM
    c_invoiceline d
    INNER JOIN c_invoice h ON d.c_invoice_id = h.c_invoice_id
    INNER JOIN ad_org org ON h.ad_org_id = org.ad_org_id
    INNER JOIN m_product prd ON d.m_product_id = prd.m_product_id
    INNER JOIN m_product_category cat ON prd.m_product_category_id = cat.m_product_category_id
    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
    INNER JOIN m_inoutline miol ON miol.m_inoutline_id = d.m_inoutline_id
    INNER JOIN m_locator loc ON miol.m_locator_id = loc.m_locator_id
WHERE
    h.documentno LIKE 'CNC%'
    AND h.docstatus IN ('CO', 'CL')
    AND h.issotrx = 'Y'
    AND EXTRACT(
        year
        FROM
            h.dateinvoiced
    ) = p_year
    AND EXTRACT(
        month
        FROM
            h.dateinvoiced
    ) = p_month
    AND (
        cat.value = 'MIKA'
        OR (
            cat.value = 'PRODUCT IMPORT'
            AND prd.name NOT LIKE '%BOHLAM%'
            AND psc.value = 'MIKA'
        )
        OR (
            cat.value = 'PRODUCT IMPORT'
            AND (
                prd.name LIKE '%FILTER UDARA%'
                OR prd.name LIKE '%SWITCH REM%'
                OR prd.name LIKE '%DOP RITING%'
            )
        )
    )
    AND (
        loc.value LIKE '%Gudang Rusak%'
        OR loc.value LIKE '%Gudang Barang Rusak%'
        OR (
            org.name = 'PWM Denpasar'
            AND loc.value LIKE '%Gudang Barang QQ PWM DPS%'
        )
    )
GROUP BY
    org.name;

-- =====================
-- Step 3: Logging hasil
-- =====================
GET DIAGNOSTICS v_row_count = ROW_COUNT;

RAISE NOTICE '[sp_get_return_comparison_cnc] Selesai. Rows: %, Durasi: % ms',
v_row_count,
EXTRACT(
    MILLISECOND
    FROM
        (clock_timestamp() - v_start_time)
);

EXCEPTION
WHEN OTHERS THEN RAISE NOTICE '[sp_get_return_comparison_cnc] ERROR!';

RAISE NOTICE 'Error Message: %',
SQLERRM;

RAISE NOTICE 'Error State: %',
SQLSTATE;

RAISE;

END;

$$;

-- =======================
-- 3. SP untuk Barang Data
-- =======================
CREATE OR REPLACE FUNCTION sp_get_return_comparison_barang (p_year INTEGER, p_month INTEGER) RETURNS TABLE (
    branch_name VARCHAR,
    total_qty NUMERIC,
    total_nominal NUMERIC
) LANGUAGE plpgsql AS $$ DECLARE v_start_time TIMESTAMP;

v_row_count INTEGER;

BEGIN 
-- ==========================
-- Step 1: Validasi Parameter
-- ==========================
IF p_year IS NULL
OR p_month IS NULL THEN RAISE EXCEPTION 'Parameter p_year dan p_month tidak boleh NULL';

END IF;

IF p_month < 1
OR p_month > 12 THEN RAISE EXCEPTION 'Parameter p_month harus antara 1-12, diberikan: %',
p_month;

END IF;

IF p_year < 2020
OR p_year > 2030 THEN RAISE EXCEPTION 'Parameter p_year harus antara 2020-2030, diberikan: %',
p_year;

END IF;

v_start_time := clock_timestamp();

RAISE NOTICE '[sp_get_return_comparison_barang] Mulai eksekusi untuk periode: %-% ',
p_year,
p_month;

-- =========================
-- Step 2: Query Data Barang
-- =========================
RETURN QUERY
SELECT
    org.name :: VARCHAR as branch_name,
    COALESCE(SUM(miol.movementqty), 0) :: NUMERIC as total_qty,
    COALESCE(SUM(col.priceactual * miol.movementqty), 0) :: NUMERIC as total_nominal
FROM
    m_inoutline miol
    INNER JOIN m_inout mio ON miol.m_inout_id = mio.m_inout_id
    INNER JOIN c_orderline col ON miol.c_orderline_id = col.c_orderline_id
    INNER JOIN c_order co ON col.c_order_id = co.c_order_id
    INNER JOIN m_product prd ON col.m_product_id = prd.m_product_id
    INNER JOIN m_product_category pc ON prd.m_product_category_id = pc.m_product_category_id
    INNER JOIN ad_org org ON miol.ad_org_id = org.ad_org_id
WHERE
    co.documentno LIKE 'SOC%'
    AND co.docstatus = 'CL'
    AND mio.documentno LIKE 'SJC%'
    AND mio.docstatus IN ('CO', 'CL')
    AND pc.value = 'MIKA'
    AND EXTRACT(
        year
        FROM
            mio.movementdate
    ) = p_year
    AND EXTRACT(
        month
        FROM
            mio.movementdate
    ) = p_month
    AND NOT EXISTS (
        SELECT
            1
        FROM
            c_invoiceline cil
        WHERE
            cil.m_inoutline_id = miol.m_inoutline_id
    )
GROUP BY
    org.name;

-- =====================
-- Step 3: Logging hasil
-- =====================
GET DIAGNOSTICS v_row_count = ROW_COUNT;

RAISE NOTICE '[sp_get_return_comparison_barang] Selesai. Rows: %, Durasi: % ms',
v_row_count,
EXTRACT(
    MILLISECOND
    FROM
        (clock_timestamp() - v_start_time)
);

EXCEPTION
WHEN OTHERS THEN RAISE NOTICE '[sp_get_return_comparison_barang] ERROR!';

RAISE NOTICE 'Error Message: %',
SQLERRM;

RAISE NOTICE 'Error State: %',
SQLSTATE;

RAISE;

END;

$$;

-- ==============================
-- 4. SP untuk Cabang Pabrik Data
-- ==============================
CREATE OR REPLACE FUNCTION sp_get_return_comparison_cabang_pabrik (p_year INTEGER, p_month INTEGER) RETURNS TABLE (
    branch_name VARCHAR,
    total_qty NUMERIC,
    total_nominal NUMERIC
) LANGUAGE plpgsql AS $$ DECLARE v_start_time TIMESTAMP;

v_row_count INTEGER;

BEGIN 
-- ==========================
-- Step 1: Validasi Parameter
-- ==========================
IF p_year IS NULL
OR p_month IS NULL THEN RAISE EXCEPTION 'Parameter p_year dan p_month tidak boleh NULL';

END IF;

IF p_month < 1
OR p_month > 12 THEN RAISE EXCEPTION 'Parameter p_month harus antara 1-12, diberikan: %',
p_month;

END IF;

IF p_year < 2020
OR p_year > 2030 THEN RAISE EXCEPTION 'Parameter p_year harus antara 2020-2030, diberikan: %',
p_year;

END IF;

v_start_time := clock_timestamp();

RAISE NOTICE '[sp_get_return_comparison_cabang_pabrik] Mulai eksekusi untuk periode: %-% ',
p_year,
p_month;

-- ================================
-- Step 2: Query Data Cabang Pabrik
-- ================================
RETURN QUERY
WITH combined_data AS (
    -- Query 1: DNS (Debit Note Supplier) - Data existing
    SELECT
        org.name as branch_name,
        invl.qtyinvoiced as qty,
        invl.linenetamt as nominal
    FROM c_invoiceline invl
    INNER JOIN c_invoice inv ON invl.c_invoice_id = inv.c_invoice_id
    INNER JOIN m_product prd ON invl.m_product_id = prd.m_product_id
    INNER JOIN m_product_category pc ON prd.m_product_category_id = pc.m_product_category_id
    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
    WHERE inv.issotrx = 'N'
        AND inv.docstatus IN ('CO', 'CL')
        AND inv.isactive = 'Y'
        AND inv.documentno LIKE 'DNS-%'
        AND (
            pc.value = 'MIKA'
            OR (pc.value = 'PRODUCT IMPORT' AND prd.name NOT LIKE '%BOHLAM%' AND psc.value = 'MIKA')
            OR (pc.value = 'PRODUCT IMPORT' AND (prd.name LIKE '%FILTER UDARA%' OR prd.name LIKE '%SWITCH REM%' OR prd.name LIKE '%DOP RITING%'))
        )
        AND EXTRACT(year FROM inv.dateinvoiced) = p_year
        AND EXTRACT(month FROM inv.dateinvoiced) = p_month

    UNION ALL

    -- Query 2: FX013 - Retur CNC ke Pabrik
    SELECT
        org.name as branch_name,
        invl.qtyinvoiced as qty,
        invl.linenetamt as nominal
    FROM c_invoiceline invl
    INNER JOIN m_product prd ON invl.m_product_id = prd.m_product_id
    INNER JOIN m_product_category pc ON prd.m_product_category_id = pc.m_product_category_id
    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
    INNER JOIN c_invoice inv ON invl.c_invoice_id = inv.c_invoice_id
    INNER JOIN ad_org org ON inv.ad_org_id = org.ad_org_id
    INNER JOIN m_inoutline mil ON invl.m_inoutline_id = mil.m_inoutline_id
    INNER JOIN m_locator gdg ON mil.m_locator_id = gdg.m_locator_id
    WHERE inv.ad_client_id = 1000001
        AND inv.issotrx = 'Y'
        AND inv.docstatus IN ('CO', 'CL')
        AND inv.isactive = 'Y'
        AND inv.documentno LIKE 'CNC-%'
        AND gdg.value LIKE '%Gudang Barang Rusak%'
        AND (
            pc.value = 'MIKA'
            OR (pc.value = 'PRODUCT IMPORT' AND prd.name NOT LIKE '%BOHLAM%' AND psc.value = 'MIKA')
            OR (pc.value = 'PRODUCT IMPORT' AND (prd.name LIKE '%FILTER UDARA%' OR prd.name LIKE '%SWITCH REM%' OR prd.name LIKE '%DOP RITING%'))
        )
        AND EXTRACT(year FROM inv.dateinvoiced) = p_year
        AND EXTRACT(month FROM inv.dateinvoiced) = p_month

    UNION ALL

    -- Query 3: FX015 - Ganti Barang ke Pabrik
    SELECT
        org.name as branch_name,
        mil.movementqty as qty,
        (mil.movementqty * col.priceactual) as nominal
    FROM m_inoutline mil
    INNER JOIN m_inout mi ON mil.m_inout_id = mi.m_inout_id
    INNER JOIN c_orderline col ON mil.c_orderline_id = col.c_orderline_id
    INNER JOIN c_order co ON col.c_order_id = co.c_order_id
    INNER JOIN m_product prd ON mil.m_product_id = prd.m_product_id
    INNER JOIN m_product_category pc ON prd.m_product_category_id = pc.m_product_category_id
    LEFT JOIN m_productsubcat psc ON prd.m_productsubcat_id = psc.m_productsubcat_id
    INNER JOIN m_locator gdg ON mil.m_locator_id = gdg.m_locator_id
    INNER JOIN ad_org org ON mi.ad_org_id = org.ad_org_id
    WHERE mi.documentno LIKE 'MRC%'
        AND mi.docstatus IN ('CO', 'CL')
        AND co.documentno LIKE 'RSO%'
        AND co.docstatus = 'CL'
        AND mil.movementqty > 0
        AND gdg.value LIKE '%Gudang Barang Rusak%'
        AND (
            pc.value = 'MIKA'
            OR (pc.value = 'PRODUCT IMPORT' AND prd.name NOT LIKE '%BOHLAM%' AND psc.value = 'MIKA')
            OR (pc.value = 'PRODUCT IMPORT' AND (prd.name LIKE '%FILTER UDARA%' OR prd.name LIKE '%SWITCH REM%' OR prd.name LIKE '%DOP RITING%'))
        )
        AND EXTRACT(year FROM mi.movementdate) = p_year
        AND EXTRACT(month FROM mi.movementdate) = p_month
)
SELECT
    cd.branch_name::VARCHAR,
    COALESCE(SUM(cd.qty), 0)::NUMERIC as total_qty,
    COALESCE(SUM(cd.nominal), 0)::NUMERIC as total_nominal
FROM combined_data cd
GROUP BY cd.branch_name;

-- =====================
-- Step 3: Logging hasil
-- =====================
GET DIAGNOSTICS v_row_count = ROW_COUNT;

RAISE NOTICE '[sp_get_return_comparison_cabang_pabrik] Selesai. Rows: %, Durasi: % ms',
v_row_count,
EXTRACT(
    MILLISECOND
    FROM
        (clock_timestamp() - v_start_time)
);

EXCEPTION
WHEN OTHERS THEN RAISE NOTICE '[sp_get_return_comparison_cabang_pabrik] ERROR!';

RAISE NOTICE 'Error Message: %',
SQLERRM;

RAISE NOTICE 'Error State: %',
SQLSTATE;

RAISE;

END;

$$;

-- =========================
-- COMMENT untuk dokumentasi
-- =========================
COMMENT ON FUNCTION sp_get_return_comparison_sales_bruto (INTEGER, INTEGER) IS 'Mengambil data Sales Bruto per cabang untuk periode tertentu. Parameter: p_year (tahun), p_month (bulan 1-12)';

COMMENT ON FUNCTION sp_get_return_comparison_cnc (INTEGER, INTEGER) IS 'Mengambil data CNC (Customer ke Cabang) per cabang untuk periode tertentu. Parameter: p_year (tahun), p_month (bulan 1-12)';

COMMENT ON FUNCTION sp_get_return_comparison_barang (INTEGER, INTEGER) IS 'Mengambil data Barang retur per cabang untuk periode tertentu. Parameter: p_year (tahun), p_month (bulan 1-12)';

COMMENT ON FUNCTION sp_get_return_comparison_cabang_pabrik (INTEGER, INTEGER) IS 'Mengambil data Cabang ke Pabrik per cabang untuk periode tertentu. Gabungan dari: DNS (Debit Note), FX013 (Retur CNC), FX015 (Ganti Barang). Parameter: p_year (tahun), p_month (bulan 1-12)';

-- =================================
-- GRANT EXECUTE untuk user aplikasi
-- =================================
-- GRANT EXECUTE ON FUNCTION sp_get_return_comparison_sales_bruto(INTEGER, INTEGER) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_return_comparison_cnc(INTEGER, INTEGER) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_return_comparison_barang(INTEGER, INTEGER) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_return_comparison_cabang_pabrik(INTEGER, INTEGER) TO your_app_user;