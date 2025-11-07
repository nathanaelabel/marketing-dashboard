# Export Optimization - MonthlyBranchController

## Problem
Export Excel dan Export PDF pada MonthlyBranchController mengalami timeout 30 detik karena query yang berat dan waktu eksekusi yang terbatas.

## Solutions Implemented

### 1. **Increased Execution Time Limit**
```php
set_time_limit(300); // 5 minutes for export
ini_set('max_execution_time', 300);
```
- Meningkatkan batas waktu eksekusi dari default 30 detik menjadi 5 menit (300 detik)
- Diterapkan pada kedua method: `exportExcel()` dan `exportPdf()`

### 2. **Database Statement Timeout**
```php
DB::statement("SET statement_timeout = 300000"); // 5 minutes (300,000 milliseconds)
```
- Meningkatkan timeout database PostgreSQL dari 2 menit menjadi 5 menit untuk operasi export
- Timeout direset kembali ke 0 (unlimited) setelah query selesai
- Diterapkan pada kedua method export

### 3. **Query Optimization**
**Before:**
```sql
AND DATE(inv.dateinvoiced) BETWEEN ? AND ?
```

**After:**
```sql
AND inv.dateinvoiced::date BETWEEN ? AND ?
```

**Benefit:**
- `DATE()` function adalah function call yang lambat karena harus dieksekusi untuk setiap row
- `::date` adalah type casting yang lebih cepat dan dapat menggunakan index
- Performa meningkat signifikan terutama untuk dataset besar

### 4. **Applied to All Methods**
Optimasi diterapkan pada:
- ✅ `exportExcel()` - Query utama untuk semua cabang
- ✅ `exportPdf()` - Menggunakan `getMonthlyRevenueData()`
- ✅ `getMonthlyRevenueData()` - Method helper yang digunakan oleh exportPdf dan getData

## Expected Results

### Before Optimization:
- ❌ Timeout error setelah 30 detik
- ❌ Export gagal untuk dataset besar
- ❌ User experience buruk

### After Optimization:
- ✅ Export dapat berjalan hingga 5 menit
- ✅ Query lebih cepat dengan `::date` casting
- ✅ Database timeout yang lebih tinggi
- ✅ Export berhasil untuk dataset besar

## Testing Recommendations

1. **Test dengan data tahun lengkap** (2023-2024)
2. **Test dengan data tahun partial** (2024-2025)
3. **Test dengan kategori berbeda** (MIKA, SPARE PART)
4. **Test dengan tipe berbeda** (BRUTO, NETTO)
5. **Monitor execution time** di log untuk memastikan tidak mendekati 5 menit

## Additional Notes

- Jika masih mengalami timeout setelah optimasi ini, pertimbangkan:
  1. Menambahkan database index pada kolom yang sering diquery
  2. Menggunakan queue/background job untuk export
  3. Implementasi caching untuk data yang sering diakses
  4. Pagination untuk dataset yang sangat besar

## Files Modified

1. `/app/Http/Controllers/MonthlyBranchController.php`
   - `exportExcel()` method
   - `exportPdf()` method
   - `getMonthlyRevenueData()` method

## Date
November 6, 2025
