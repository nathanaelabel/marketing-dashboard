-- Stored Procedures untuk Monthly Branch Revenue
--
-- ===================================================================
-- 1. SP untuk Monthly Revenue Data - BRUTO (Single Branch/National)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_monthly_revenue_bruto (
    p_start_date DATE,
    p_end_date DATE,
    p_category VARCHAR,
    p_branch VARCHAR DEFAULT NULL
) RETURNS TABLE (month_number INTEGER, total_revenue NUMERIC) LANGUAGE plpgsql AS $$
DECLARE
    v_start_time TIMESTAMP;
    v_row_count INTEGER;
BEGIN
    -- ==========================
    -- Step 1: Validasi Parameter
    -- ==========================
    IF p_start_date IS NULL OR p_end_date IS NULL THEN
        RAISE EXCEPTION 'Parameter p_start_date dan p_end_date tidak boleh NULL';
    END IF;

    IF p_end_date < p_start_date THEN
        RAISE EXCEPTION 'Parameter p_end_date (%) harus >= p_start_date (%)', p_end_date, p_start_date;
    END IF;

    IF p_category IS NULL OR p_category = '' THEN
        RAISE EXCEPTION 'Parameter p_category tidak boleh NULL atau kosong';
    END IF;

    v_start_time := clock_timestamp();

    RAISE NOTICE '[sp_get_monthly_revenue_bruto] Mulai eksekusi untuk periode: % - %, kategori: %, branch: %',
        p_start_date, p_end_date, p_category, COALESCE(p_branch, 'National');

    -- ================================
    -- Step 2: Query Data Revenue Bruto
    -- ================================
    RETURN QUERY
    SELECT
        EXTRACT(month FROM inv.dateinvoiced)::INTEGER AS month_number,
        COALESCE(SUM(invl.linenetamt), 0)::NUMERIC AS total_revenue
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
        AND inv.dateinvoiced::date BETWEEN p_start_date AND p_end_date
        AND inv.documentno LIKE 'INC%'
        AND (
            CASE 
                WHEN p_category = 'MIKA' THEN (
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
                ELSE pc.name = p_category
            END
        )
        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
        AND (p_branch IS NULL OR org.name = p_branch)
    GROUP BY
        EXTRACT(month FROM inv.dateinvoiced)
    ORDER BY
        month_number;

    -- =====================
    -- Step 3: Logging hasil
    -- =====================
    GET DIAGNOSTICS v_row_count = ROW_COUNT;

    RAISE NOTICE '[sp_get_monthly_revenue_bruto] Selesai. Rows: %, Durasi: % ms',
        v_row_count,
        EXTRACT(MILLISECOND FROM (clock_timestamp() - v_start_time));

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '[sp_get_monthly_revenue_bruto] ERROR!';
        RAISE NOTICE 'Error Message: %', SQLERRM;
        RAISE NOTICE 'Error State: %', SQLSTATE;
        RAISE;
END;
$$;

-- ===================================================================
-- 2. SP untuk Monthly Revenue Data - NETTO (Single Branch/National)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_monthly_revenue_netto (
    p_start_date DATE,
    p_end_date DATE,
    p_category VARCHAR,
    p_branch VARCHAR DEFAULT NULL
) RETURNS TABLE (month_number INTEGER, total_revenue NUMERIC) LANGUAGE plpgsql AS $$
DECLARE
    v_start_time TIMESTAMP;
    v_row_count INTEGER;
BEGIN
    -- ==========================
    -- Step 1: Validasi Parameter
    -- ==========================
    IF p_start_date IS NULL OR p_end_date IS NULL THEN
        RAISE EXCEPTION 'Parameter p_start_date dan p_end_date tidak boleh NULL';
    END IF;

    IF p_end_date < p_start_date THEN
        RAISE EXCEPTION 'Parameter p_end_date (%) harus >= p_start_date (%)', p_end_date, p_start_date;
    END IF;

    IF p_category IS NULL OR p_category = '' THEN
        RAISE EXCEPTION 'Parameter p_category tidak boleh NULL atau kosong';
    END IF;

    v_start_time := clock_timestamp();

    RAISE NOTICE '[sp_get_monthly_revenue_netto] Mulai eksekusi untuk periode: % - %, kategori: %, branch: %',
        p_start_date, p_end_date, p_category, COALESCE(p_branch, 'National');

    -- ================================
    -- Step 2: Query Data Revenue Netto
    -- ================================
    RETURN QUERY
    SELECT
        EXTRACT(month FROM inv.dateinvoiced)::INTEGER AS month_number,
        COALESCE(SUM(CASE
            WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
            WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
        END), 0)::NUMERIC AS total_revenue
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
        AND inv.dateinvoiced::date BETWEEN p_start_date AND p_end_date
        AND SUBSTR(inv.documentno, 1, 3) IN ('INC', 'CNC')
        AND (
            CASE 
                WHEN p_category = 'MIKA' THEN (
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
                ELSE pc.name = p_category
            END
        )
        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
        AND (p_branch IS NULL OR org.name = p_branch)
    GROUP BY
        EXTRACT(month FROM inv.dateinvoiced)
    ORDER BY
        month_number;

    -- =====================
    -- Step 3: Logging hasil
    -- =====================
    GET DIAGNOSTICS v_row_count = ROW_COUNT;

    RAISE NOTICE '[sp_get_monthly_revenue_netto] Selesai. Rows: %, Durasi: % ms',
        v_row_count,
        EXTRACT(MILLISECOND FROM (clock_timestamp() - v_start_time));

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '[sp_get_monthly_revenue_netto] ERROR!';
        RAISE NOTICE 'Error Message: %', SQLERRM;
        RAISE NOTICE 'Error State: %', SQLSTATE;
        RAISE;
END;
$$;

-- ===================================================================
-- 3. SP untuk Export Excel - BRUTO (Semua Cabang, 2 Tahun)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_monthly_revenue_export_bruto (
    p_prev_start_date DATE,
    p_prev_end_date DATE,
    p_curr_start_date DATE,
    p_curr_end_date DATE,
    p_category VARCHAR
) RETURNS TABLE (
    branch_name VARCHAR,
    month_number INTEGER,
    year_number INTEGER,
    total_revenue NUMERIC
) LANGUAGE plpgsql AS $$
DECLARE
    v_start_time TIMESTAMP;
    v_row_count INTEGER;
BEGIN
    -- ==========================
    -- Step 1: Validasi Parameter
    -- ==========================
    IF p_prev_start_date IS NULL OR p_prev_end_date IS NULL 
       OR p_curr_start_date IS NULL OR p_curr_end_date IS NULL THEN
        RAISE EXCEPTION 'Semua parameter tanggal tidak boleh NULL';
    END IF;

    IF p_category IS NULL OR p_category = '' THEN
        RAISE EXCEPTION 'Parameter p_category tidak boleh NULL atau kosong';
    END IF;

    v_start_time := clock_timestamp();

    RAISE NOTICE '[sp_get_monthly_revenue_export_bruto] Mulai eksekusi untuk periode prev: % - %, curr: % - %, kategori: %',
        p_prev_start_date, p_prev_end_date, p_curr_start_date, p_curr_end_date, p_category;

    -- ====================================
    -- Step 2: Query Data Export Revenue Bruto
    -- ====================================
    RETURN QUERY
    SELECT
        org.name::VARCHAR AS branch_name,
        EXTRACT(month FROM inv.dateinvoiced)::INTEGER AS month_number,
        EXTRACT(year FROM inv.dateinvoiced)::INTEGER AS year_number,
        COALESCE(SUM(invl.linenetamt), 0)::NUMERIC AS total_revenue
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
        AND (
            (inv.dateinvoiced::date BETWEEN p_prev_start_date AND p_prev_end_date)
            OR (inv.dateinvoiced::date BETWEEN p_curr_start_date AND p_curr_end_date)
        )
        AND inv.documentno LIKE 'INC%'
        AND (
            CASE 
                WHEN p_category = 'MIKA' THEN (
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
                ELSE pc.name = p_category
            END
        )
        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
    GROUP BY
        org.name, EXTRACT(month FROM inv.dateinvoiced), EXTRACT(year FROM inv.dateinvoiced)
    ORDER BY
        org.name, year_number, month_number;

    -- =====================
    -- Step 3: Logging hasil
    -- =====================
    GET DIAGNOSTICS v_row_count = ROW_COUNT;

    RAISE NOTICE '[sp_get_monthly_revenue_export_bruto] Selesai. Rows: %, Durasi: % ms',
        v_row_count,
        EXTRACT(MILLISECOND FROM (clock_timestamp() - v_start_time));

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '[sp_get_monthly_revenue_export_bruto] ERROR!';
        RAISE NOTICE 'Error Message: %', SQLERRM;
        RAISE NOTICE 'Error State: %', SQLSTATE;
        RAISE;
END;
$$;

-- ===================================================================
-- 4. SP untuk Export Excel - NETTO (Semua Cabang, 2 Tahun)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_monthly_revenue_export_netto (
    p_prev_start_date DATE,
    p_prev_end_date DATE,
    p_curr_start_date DATE,
    p_curr_end_date DATE,
    p_category VARCHAR
) RETURNS TABLE (
    branch_name VARCHAR,
    month_number INTEGER,
    year_number INTEGER,
    total_revenue NUMERIC
) LANGUAGE plpgsql AS $$
DECLARE
    v_start_time TIMESTAMP;
    v_row_count INTEGER;
BEGIN
    -- ==========================
    -- Step 1: Validasi Parameter
    -- ==========================
    IF p_prev_start_date IS NULL OR p_prev_end_date IS NULL 
       OR p_curr_start_date IS NULL OR p_curr_end_date IS NULL THEN
        RAISE EXCEPTION 'Semua parameter tanggal tidak boleh NULL';
    END IF;

    IF p_category IS NULL OR p_category = '' THEN
        RAISE EXCEPTION 'Parameter p_category tidak boleh NULL atau kosong';
    END IF;

    v_start_time := clock_timestamp();

    RAISE NOTICE '[sp_get_monthly_revenue_export_netto] Mulai eksekusi untuk periode prev: % - %, curr: % - %, kategori: %',
        p_prev_start_date, p_prev_end_date, p_curr_start_date, p_curr_end_date, p_category;

    -- ====================================
    -- Step 2: Query Data Export Revenue Netto
    -- ====================================
    RETURN QUERY
    SELECT
        org.name::VARCHAR AS branch_name,
        EXTRACT(month FROM inv.dateinvoiced)::INTEGER AS month_number,
        EXTRACT(year FROM inv.dateinvoiced)::INTEGER AS year_number,
        COALESCE(SUM(CASE
            WHEN SUBSTR(inv.documentno, 1, 3) IN ('INC') THEN invl.linenetamt
            WHEN SUBSTR(inv.documentno, 1, 3) IN ('CNC') THEN -invl.linenetamt
        END), 0)::NUMERIC AS total_revenue
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
        AND (
            (inv.dateinvoiced::date BETWEEN p_prev_start_date AND p_prev_end_date)
            OR (inv.dateinvoiced::date BETWEEN p_curr_start_date AND p_curr_end_date)
        )
        AND SUBSTR(inv.documentno, 1, 3) IN ('INC', 'CNC')
        AND (
            CASE 
                WHEN p_category = 'MIKA' THEN (
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
                ELSE pc.name = p_category
            END
        )
        AND UPPER(cust.name) NOT LIKE '%KARYAWAN%'
    GROUP BY
        org.name, EXTRACT(month FROM inv.dateinvoiced), EXTRACT(year FROM inv.dateinvoiced)
    ORDER BY
        org.name, year_number, month_number;

    -- =====================
    -- Step 3: Logging hasil
    -- =====================
    GET DIAGNOSTICS v_row_count = ROW_COUNT;

    RAISE NOTICE '[sp_get_monthly_revenue_export_netto] Selesai. Rows: %, Durasi: % ms',
        v_row_count,
        EXTRACT(MILLISECOND FROM (clock_timestamp() - v_start_time));

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '[sp_get_monthly_revenue_export_netto] ERROR!';
        RAISE NOTICE 'Error Message: %', SQLERRM;
        RAISE NOTICE 'Error State: %', SQLSTATE;
        RAISE;
END;
$$;

-- =========================
-- COMMENT untuk dokumentasi
-- =========================
COMMENT ON FUNCTION sp_get_monthly_revenue_bruto (DATE, DATE, VARCHAR, VARCHAR) IS 'Mengambil data revenue bulanan BRUTO (hanya INC) per bulan untuk cabang tertentu atau National. Parameter: p_start_date, p_end_date, p_category, p_branch (NULL untuk National)';

COMMENT ON FUNCTION sp_get_monthly_revenue_netto (DATE, DATE, VARCHAR, VARCHAR) IS 'Mengambil data revenue bulanan NETTO (INC - CNC) per bulan untuk cabang tertentu atau National. Parameter: p_start_date, p_end_date, p_category, p_branch (NULL untuk National)';

COMMENT ON FUNCTION sp_get_monthly_revenue_export_bruto (DATE, DATE, DATE, DATE, VARCHAR) IS 'Mengambil data revenue bulanan BRUTO untuk export Excel (semua cabang, 2 periode tahun). Parameter: p_prev_start_date, p_prev_end_date, p_curr_start_date, p_curr_end_date, p_category';

COMMENT ON FUNCTION sp_get_monthly_revenue_export_netto (DATE, DATE, DATE, DATE, VARCHAR) IS 'Mengambil data revenue bulanan NETTO untuk export Excel (semua cabang, 2 periode tahun). Parameter: p_prev_start_date, p_prev_end_date, p_curr_start_date, p_curr_end_date, p_category';

-- =================================
-- GRANT EXECUTE untuk user aplikasi
-- =================================
-- GRANT EXECUTE ON FUNCTION sp_get_monthly_revenue_bruto(DATE, DATE, VARCHAR, VARCHAR) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_monthly_revenue_netto(DATE, DATE, VARCHAR, VARCHAR) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_monthly_revenue_export_bruto(DATE, DATE, DATE, DATE, VARCHAR) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_monthly_revenue_export_netto(DATE, DATE, DATE, DATE, VARCHAR) TO your_app_user;
