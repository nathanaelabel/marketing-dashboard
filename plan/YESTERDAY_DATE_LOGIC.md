# Yesterday Date Logic (H-1) Implementation

## Problem
Dashboard menampilkan data hingga hari ini (contoh: 6 November 2025), padahal data hari ini belum tersedia karena dashboard di-update secara daily setiap malam.

## Root Cause
Sistem menggunakan `date('Y-m-d')` (hari ini) sebagai end date untuk tahun berjalan, padahal data hari ini belum di-update.

## Solution
Menggunakan **yesterday (H-1)** sebagai end date untuk memastikan data yang ditampilkan adalah data yang sudah ter-update.

## Implementation

### Formula
```php
// Before
$today = date('Y-m-d');

// After
$yesterday = date('Y-m-d', strtotime('-1 day'));
```

### Applied To

#### NationalYearlyController.php
1. ✅ `getData()` method - Line ~29
2. ✅ `exportExcel()` method - Line ~376
3. ✅ `exportPdf()` method - Line ~563

#### MonthlyBranchController.php
1. ✅ `getData()` method - Line ~55, ~61
2. ✅ `exportExcel()` method - Line ~393
3. ✅ `exportPdf()` method - Line ~797

## Examples

### Scenario: Today is November 6, 2025

**Before (Using Today):**
```
Periode: 1 Januari - 6 November 2024 VS 1 Januari - 6 November 2025
```
❌ Data 6 November 2025 belum tersedia (akan di-update malam ini)

**After (Using Yesterday):**
```
Periode: 1 Januari - 5 November 2024 VS 1 Januari - 5 November 2025
```
✅ Data 5 November 2025 sudah tersedia dan akurat

## Benefits

1. **Data Accuracy**: Menampilkan data yang sudah ter-update
2. **User Clarity**: User tidak bingung kenapa data hari ini kosong/tidak lengkap
3. **Consistency**: Semua method menggunakan logika yang sama
4. **Automatic**: Otomatis menyesuaikan setiap hari tanpa perlu manual update

## Business Logic

### Dashboard Update Schedule
- **Update Time**: Setiap malam (daily batch process)
- **Data Available**: Data hingga kemarin (H-1)
- **Current Day Data**: Belum tersedia hingga update malam

### Date Range Logic
```
If (selected year == current year):
    end_date = yesterday (H-1)
Else:
    end_date = December 31 of selected year
```

## Testing Checklist

- [ ] Test getData() dengan tahun berjalan (2025)
- [ ] Test getData() dengan tahun sebelumnya (2024)
- [ ] Test exportExcel() dengan tahun berjalan
- [ ] Test exportExcel() dengan tahun sebelumnya
- [ ] Test exportPdf() dengan tahun berjalan
- [ ] Test exportPdf() dengan tahun sebelumnya
- [ ] Verify date range text shows correct dates
- [ ] Verify data matches the displayed date range

## Files Modified

1. `/app/Http/Controllers/NationalYearlyController.php`
   - `getData()` method
   - `exportExcel()` method
   - `exportPdf()` method

2. `/app/Http/Controllers/MonthlyBranchController.php`
   - `getData()` method
   - `exportExcel()` method
   - `exportPdf()` method

## Notes

- Logika ini hanya berlaku untuk **tahun berjalan** (current year)
- Untuk tahun sebelumnya, tetap menggunakan full year (1 Jan - 31 Des)
- Tanggal otomatis menyesuaikan setiap hari tanpa perlu konfigurasi manual

## Date
November 6, 2025
