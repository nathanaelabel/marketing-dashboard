# Sales Comparison Date Filter - Penjelasan Perubahan

## Masalah yang Ditemukan

Pada section **Rekap Sales, Stok, dan BDP**, nilai **Stock** dan **BDP** tidak berubah ketika filter tanggal diubah. Nilai-nilai tersebut selalu sama untuk setiap pilihan tanggal.

## Analisis Masalah

### 1. Query BDP (Barang Dalam Perjalanan)
**Masalah**: Query BDP tidak memfilter berdasarkan tanggal invoice yang dipilih user.

**Penyebab**: 
- Query mengambil semua invoice dengan `documentno LIKE 'INS-%'` tanpa mempertimbangkan tanggal
- Tidak ada filter pada `h.dateinvoiced` dan `m_matchinv.created`

**Solusi**: 
- Menambahkan filter `h.dateinvoiced::date <= ?::date` untuk membatasi invoice sampai tanggal yang dipilih
- Menambahkan filter `mi.created::date <= ?::date` pada LEFT JOIN m_matchinv untuk membatasi data matching

### 2. Query Stock
**Masalah**: Nilai stock selalu sama untuk setiap tanggal.

**Penyebab**: 
- Tabel `m_storage` hanya menyimpan **snapshot terkini** dari stock
- Tidak ada kolom tanggal/timestamp pada tabel `m_storage`
- Ini sesuai dengan query original ADempiere yang menggunakan `productqty(locator_id, product_id, date('now'))` yang selalu mengambil data terkini

**Solusi**: 
- **TIDAK ADA PERUBAHAN** pada query stock karena memang sesuai dengan desain sistem
- Menambahkan dokumentasi yang menjelaskan bahwa data stock adalah snapshot terkini
- Nilai stock akan selalu menampilkan data terakhir yang di-sync, tidak berdasarkan tanggal historis

## Perubahan yang Dilakukan

### File: `app/Http/Controllers/SalesComparisonController.php`

#### 1. Method `getAllBranchesData()` - Query BDP
**Sebelum**:
```php
$bdpQuery = "
    SELECT
        org.name as branch_name,
        cat.name as category,
        SUM((d.qtyinvoiced - COALESCE(match_qty.qtymr, 0)) * d.priceactual) AS bdp_value
    FROM c_invoice h
    INNER JOIN c_invoiceline d ON d.c_invoice_id = h.c_invoice_id
    LEFT JOIN (
        SELECT c_invoiceline_id, SUM(qty) as qtymr
        FROM m_matchinv
        GROUP BY c_invoiceline_id
    ) match_qty ON d.c_invoiceline_id = match_qty.c_invoiceline_id
    ...
    WHERE h.documentno LIKE 'INS-%'
        AND h.docstatus = 'CO'
        AND h.issotrx = 'N'
        AND cat.name IN ('MIKA', 'SPARE PART')
        AND d.qtyinvoiced <> COALESCE(match_qty.qtymr, 0)
    GROUP BY org.name, cat.name
";

$bdpData = DB::select($bdpQuery);
```

**Sesudah**:
```php
$bdpQuery = "
    SELECT
        org.name as branch_name,
        cat.name as category,
        SUM((d.qtyinvoiced - COALESCE(match_qty.qtymr, 0)) * d.priceactual) AS bdp_value
    FROM c_invoice h
    INNER JOIN c_invoiceline d ON d.c_invoice_id = h.c_invoice_id
    LEFT JOIN (
        SELECT c_invoiceline_id, SUM(qty) as qtymr
        FROM m_matchinv
        WHERE created::date <= ?::date  -- FILTER BARU
        GROUP BY c_invoiceline_id
    ) match_qty ON d.c_invoiceline_id = match_qty.c_invoiceline_id
    ...
    WHERE h.documentno LIKE 'INS-%'
        AND h.docstatus = 'CO'
        AND h.issotrx = 'N'
        AND cat.name IN ('MIKA', 'SPARE PART')
        AND h.dateinvoiced::date <= ?::date  -- FILTER BARU
        AND d.qtyinvoiced <> COALESCE(match_qty.qtymr, 0)
    GROUP BY org.name, cat.name
";

$bdpData = DB::select($bdpQuery, [$date, $date]);  -- PARAMETER BARU
```

#### 2. Method `getBranchData()` - Query BDP
Perubahan serupa juga diterapkan pada method `getBranchData()` untuk konsistensi, meskipun method ini sudah tidak digunakan lagi (digantikan oleh `getAllBranchesData()`).

#### 3. Dokumentasi Query Stock
Menambahkan komentar dokumentasi pada query stock:
```php
// 2. Get STOCK data for all branches at once
// NOTE: Stock data from m_storage table is a CURRENT SNAPSHOT only
// The m_storage table does not have historical data with dates
// Stock values shown will always reflect the LATEST synced data regardless of selected date
// This matches the original ADempiere query behavior using productqty(locator_id, product_id, date('now'))
```

## Hasil yang Diharapkan

### BDP (Barang Dalam Perjalanan)
✅ Nilai BDP sekarang akan **berubah** berdasarkan tanggal yang dipilih
- Hanya menghitung invoice dengan `documentno LIKE 'INS-%'` yang dibuat sampai tanggal yang dipilih
- Hanya menghitung matching yang terjadi sampai tanggal yang dipilih

### Stock
ℹ️ Nilai Stock akan **tetap sama** untuk setiap tanggal yang dipilih
- Ini adalah **behavior yang benar** sesuai dengan desain sistem
- Data stock di tabel `m_storage` adalah snapshot terkini
- Tidak ada data historis stock berdasarkan tanggal
- Sesuai dengan query original ADempiere yang menggunakan `date('now')`

### Sales
✅ Nilai Sales sudah bekerja dengan benar sejak awal
- Memfilter berdasarkan `inv.dateinvoiced` sesuai tanggal yang dipilih

## Catatan Penting

### Tentang Konfigurasi Branch (`getBranchConfig()`)
Konfigurasi hardcoded untuk `m_locator_id` dan `m_pricelist_version_id` masih dipertahankan karena:
1. Lebih cepat (tidak perlu query tambahan ke database)
2. Mapping sudah jelas dan stabil
3. Data sudah di-sync dari semua cabang via `SyncAllAdempiereDataCommand`

Jika ingin mengambil data ini secara dinamis dari database, bisa dilakukan dengan query ke tabel `m_locator` dan `m_pricelist_version` berdasarkan `ad_org_id`.

## Testing

Untuk menguji perubahan ini:

1. Pilih tanggal yang berbeda pada filter tanggal
2. Perhatikan nilai **BDP** - seharusnya berubah berdasarkan tanggal
3. Perhatikan nilai **Stock** - akan tetap sama (ini adalah behavior yang benar)
4. Perhatikan nilai **Sales** - sudah bekerja dengan benar sejak awal

## Referensi Query Original

Query original dari ADempiere menggunakan:
```sql
-- STOK menggunakan date('now') - selalu data terkini
SUM(productqty(1000010, prd.m_product_id, date('now')) * prc.pricelist * 0.615) AS nilai_stok_mika

-- BDP tidak ada filter tanggal di query original
-- Namun seharusnya difilter berdasarkan tanggal invoice untuk mendapatkan BDP historis yang akurat
```

---
**Tanggal Perubahan**: 6 November 2025  
**Developer**: Cascade AI Assistant
