-- Stored Procedures untuk National Yearly Revenue
--
-- ===================================================================
-- 1. SP untuk National Yearly Revenue - BRUTO (Per Cabang)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_national_yearly_bruto (
    p_start_date DATE,
    p_end_date DATE,
    p_category VARCHAR
) RETURNS TABLE (branch_name VARCHAR, total_revenue NUMERIC) LANGUAGE plpgsql AS $$
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

    RAISE NOTICE '[sp_get_national_yearly_bruto] Mulai eksekusi untuk periode: % - %, kategori: %',
        p_start_date, p_end_date, p_category;

    -- ================================
    -- Step 2: Query Data Revenue Bruto
    -- ================================
    RETURN QUERY
    SELECT
        org.name::VARCHAR AS branch_name,
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
        AND org.name NOT LIKE '%HEAD OFFICE%'
        AND inv.dateinvoiced::date >= p_start_date AND inv.dateinvoiced::date <= p_end_date
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
        org.name
    ORDER BY
        org.name;

    -- =====================
    -- Step 3: Logging hasil
    -- =====================
    GET DIAGNOSTICS v_row_count = ROW_COUNT;

    RAISE NOTICE '[sp_get_national_yearly_bruto] Selesai. Rows: %, Durasi: % ms',
        v_row_count,
        EXTRACT(MILLISECOND FROM (clock_timestamp() - v_start_time));

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '[sp_get_national_yearly_bruto] ERROR!';
        RAISE NOTICE 'Error Message: %', SQLERRM;
        RAISE NOTICE 'Error State: %', SQLSTATE;
        RAISE;
END;
$$;

-- ===================================================================
-- 2. SP untuk National Yearly Revenue - NETTO (Per Cabang)
-- ===================================================================
CREATE OR REPLACE FUNCTION sp_get_national_yearly_netto (
    p_start_date DATE,
    p_end_date DATE,
    p_category VARCHAR
) RETURNS TABLE (branch_name VARCHAR, total_revenue NUMERIC) LANGUAGE plpgsql AS $$
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

    RAISE NOTICE '[sp_get_national_yearly_netto] Mulai eksekusi untuk periode: % - %, kategori: %',
        p_start_date, p_end_date, p_category;

    -- ================================
    -- Step 2: Query Data Revenue Netto
    -- ================================
    RETURN QUERY
    SELECT
        org.name::VARCHAR AS branch_name,
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
        AND org.name NOT LIKE '%HEAD OFFICE%'
        AND inv.dateinvoiced::date >= p_start_date AND inv.dateinvoiced::date <= p_end_date
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
        org.name
    ORDER BY
        org.name;

    -- =====================
    -- Step 3: Logging hasil
    -- =====================
    GET DIAGNOSTICS v_row_count = ROW_COUNT;

    RAISE NOTICE '[sp_get_national_yearly_netto] Selesai. Rows: %, Durasi: % ms',
        v_row_count,
        EXTRACT(MILLISECOND FROM (clock_timestamp() - v_start_time));

EXCEPTION
    WHEN OTHERS THEN
        RAISE NOTICE '[sp_get_national_yearly_netto] ERROR!';
        RAISE NOTICE 'Error Message: %', SQLERRM;
        RAISE NOTICE 'Error State: %', SQLSTATE;
        RAISE;
END;
$$;

-- =========================
-- COMMENT untuk dokumentasi
-- =========================
COMMENT ON FUNCTION sp_get_national_yearly_bruto (DATE, DATE, VARCHAR) IS 'Mengambil data revenue tahunan BRUTO (hanya INC) per cabang untuk National Yearly. Parameter: p_start_date, p_end_date, p_category';

COMMENT ON FUNCTION sp_get_national_yearly_netto (DATE, DATE, VARCHAR) IS 'Mengambil data revenue tahunan NETTO (INC - CNC) per cabang untuk National Yearly. Parameter: p_start_date, p_end_date, p_category';

-- =================================
-- GRANT EXECUTE untuk user aplikasi
-- =================================
-- GRANT EXECUTE ON FUNCTION sp_get_national_yearly_bruto(DATE, DATE, VARCHAR) TO your_app_user;
-- GRANT EXECUTE ON FUNCTION sp_get_national_yearly_netto(DATE, DATE, VARCHAR) TO your_app_user;
