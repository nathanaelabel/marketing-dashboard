# Sync Progress Tracking System

## Overview

Sistem tracking untuk memantau progress sinkronisasi data dari 17 cabang. Sistem ini mencatat setiap tabel yang di-sync, status, jumlah records, dan error yang terjadi. Sangat berguna saat terjadi gangguan listrik atau masalah lain yang menghentikan proses sync.

## Features

-   ✅ **Progress Tracking** - Mencatat status setiap tabel (pending, in_progress, completed, failed, skipped)
-   ✅ **Batch Management** - Setiap run sync-all mendapat batch ID unik
-   ✅ **Resume Capability** - Bisa melanjutkan sync dari batch yang terinterupsi
-   ✅ **Detailed Logging** - Waktu mulai, selesai, durasi, jumlah records, error message
-   ✅ **Skip Completed** - Saat resume, otomatis skip tabel yang sudah selesai
-   ✅ **Status Monitoring** - Command untuk melihat status real-time

## Database Tables

### `sync_batches`

Menyimpan informasi setiap batch sync:

-   `batch_id` - Unique ID (format: sync_YmdHis_random)
-   `status` - running, completed, failed, interrupted
-   `total_tables` - Total tabel yang akan di-sync
-   `completed_tables` - Jumlah tabel yang sudah selesai
-   `failed_tables` - Jumlah tabel yang gagal
-   `command_options` - Options yang digunakan (JSON)
-   `started_at`, `completed_at`, `duration_seconds`

### `sync_progress`

Menyimpan progress setiap tabel dalam batch:

-   `batch_id` - Reference ke sync_batches
-   `connection_name` - Nama koneksi (pgsql_lmp, pgsql_sby, dll)
-   `table_name` - Nama tabel
-   `model_name` - Nama model
-   `status` - pending, in_progress, completed, failed, skipped
-   `records_processed` - Jumlah records yang berhasil
-   `records_skipped` - Jumlah records yang di-skip
-   `error_message` - Pesan error jika gagal
-   `started_at`, `completed_at`, `duration_seconds`
-   `retry_count` - Jumlah retry yang sudah dilakukan

## Installation

1. **Run Migration**

```bash
php artisan migrate
```

2. **Verify Tables Created**

```bash
php artisan db:show
```

## Usage

### 1. Running Sync (Normal)

```bash
# Sync semua tabel dari semua koneksi
php artisan app:sync-all

# Sync dari koneksi tertentu
php artisan app:sync-all --connection=pgsql_lmp

# Sync tabel tertentu saja
php artisan app:sync-all --tables=MProduct,CInvoice
```

Setiap run akan membuat batch ID baru, contoh:

```
Created new batch: sync_20251108_143052_aB3dEf9h
```

**Simpan batch ID ini!** Anda akan membutuhkannya jika sync terinterupsi.

### 2. Monitoring Progress

#### Lihat Status Batch Terbaru

```bash
php artisan app:sync-status --latest
```

Output:

```
====================================================================
Sync Batch Details: sync_20251108_143052_aB3dEf9h
====================================================================

Batch Summary:
  Status: running
  Started: 2025-11-08 14:30:52
  Progress: 45/120 tables (37.5%)
  Failed: 2 tables

Table Progress:
┌─────────────┬──────────────────┬────────────┬──────────┬──────────┬────────┐
│ Connection  │ Table            │ Status     │ Records  │ Duration │ Error  │
├─────────────┼──────────────────┼────────────┼──────────┼──────────┼────────┤
│ pgsql_lmp   │ ad_org           │ completed  │ 17       │ 00:00:05 │ -      │
│ pgsql_sby   │ m_product        │ completed  │ 15,234   │ 00:02:15 │ -      │
│ pgsql_bdg   │ c_invoice        │ in_progress│ N/A      │ Running..│ -      │
│ pgsql_jkt   │ c_order          │ failed     │ N/A      │ 00:01:30 │ Connec │
└─────────────┴──────────────────┴────────────┴──────────┴──────────┴────────┘
```

#### Lihat Status Batch Tertentu

```bash
php artisan app:sync-status sync_20251108_143052_aB3dEf9h
```

#### Lihat Hanya Tabel yang Gagal

```bash
php artisan app:sync-status sync_20251108_143052_aB3dEf9h --failed
```

#### Lihat Semua Batch (20 terakhir)

```bash
php artisan app:sync-status --all
```

Output:

```
====================================================================
All Sync Batches
====================================================================

┌──────────────────────────────┬───────────┬─────────────────────┬──────────┬─────────────┬────────┐
│ Batch ID                     │ Status    │ Started             │ Duration │ Progress    │ Failed │
├──────────────────────────────┼───────────┼─────────────────────┼──────────┼─────────────┼────────┤
│ sync_20251108_143052_aB3dEf9h│ running   │ 2025-11-08 14:30:52 │ Running..│ 45/120 (38%)│ 2      │
│ sync_20251108_100000_xY7zKl2m│ completed │ 2025-11-08 10:00:00 │ 02:15:30 │ 120/120(100%)│ 0      │
│ sync_20251107_230000_pQ4wRt5n│ interrupted│ 2025-11-07 23:00:00│ 01:45:20 │ 85/120 (71%)│ 5      │
└──────────────────────────────┴───────────┴─────────────────────┴──────────┴─────────────┴────────┘

Use: php artisan app:sync-status {batch_id} to see details
Use: php artisan app:sync-all --resume={batch_id} to resume a batch
```

### 3. Resume Sync yang Terinterupsi

Jika sync berhenti karena mati listrik atau error:

```bash
# Resume dari batch terakhir
php artisan app:sync-status --latest
# Lihat batch_id yang terinterupsi

# Resume batch tersebut
php artisan app:sync-all --resume=sync_20251108_143052_aB3dEf9h
```

**Apa yang terjadi saat resume:**

-   ✅ Tabel yang sudah `completed` akan di-skip
-   ✅ Tabel yang `failed` akan di-retry
-   ✅ Tabel yang `pending` atau `in_progress` akan dijalankan
-   ✅ Progress counter akan dilanjutkan dari terakhir

Output saat resume:

```
Resuming batch: sync_20251108_143052_aB3dEf9h
Progress: 45/120 tables completed

Processing connection: [pgsql_jkt]
⏭ Skipping MProduct from pgsql_jkt (already completed)
⏭ Skipping CInvoice from pgsql_jkt (already completed)
Starting sync for table: c_order from connection: pgsql_jkt
...
```

## Troubleshooting

### Skenario 1: Mati Listrik Saat Sync

**Problem:** Server mati saat sedang sync, tidak tau sudah sampai mana.

**Solution:**

```bash
# 1. Cek status batch terakhir
php artisan app:sync-status --latest

# 2. Lihat tabel mana yang sudah selesai dan mana yang gagal
# Output akan menunjukkan detail progress

# 3. Resume dari batch tersebut
php artisan app:sync-all --resume={batch_id}
```

### Skenario 2: Connection Timeout di Beberapa Tabel

**Problem:** Beberapa tabel gagal karena timeout, tapi tidak mau sync ulang semua.

**Solution:**

```bash
# 1. Lihat tabel yang gagal
php artisan app:sync-status {batch_id} --failed

# 2. Resume batch (hanya akan retry yang failed)
php artisan app:sync-all --resume={batch_id}
```

### Skenario 3: Ingin Tau Berapa Lama Sync Biasanya

**Problem:** Perlu estimasi waktu untuk planning.

**Solution:**

```bash
# Lihat history batch
php artisan app:sync-status --all

# Lihat detail batch yang completed
php artisan app:sync-status {batch_id}
# Akan menunjukkan duration per tabel
```

### Skenario 4: Sync Stuck di Satu Tabel

**Problem:** Sync tidak maju-maju, stuck di satu tabel.

**Solution:**

```bash
# 1. Cek status real-time
php artisan app:sync-status --latest

# 2. Jika tabel masih "in_progress" terlalu lama:
# - Stop sync (Ctrl+C)
# - Batch akan otomatis marked as "interrupted"

# 3. Cek tabel yang bermasalah
php artisan app:sync-status {batch_id}

# 4. Resume (akan retry tabel yang stuck)
php artisan app:sync-all --resume={batch_id}
```

## Log Files

Selain database tracking, semua sync juga tercatat di log files:

### Laravel Log

```bash
tail -f storage/logs/laravel.log
```

Log entries:

-   `SyncAll: Starting for {table} from {connection}`
-   `SyncAll: Completed for {table} from {connection}`
-   `SyncAll: Connection timeout retry`
-   `SyncAll: Final retry failed - Manual sync required`

### Sync Log (jika ada)

```bash
tail -f storage/logs/sync.log
```

## Best Practices

### 1. Selalu Catat Batch ID

Saat menjalankan sync, simpan batch ID yang muncul:

```bash
php artisan app:sync-all | tee sync-$(date +%Y%m%d-%H%M%S).log
```

### 2. Monitor Progress Secara Berkala

Buka terminal kedua untuk monitoring:

```bash
# Terminal 1: Running sync
php artisan app:sync-all

# Terminal 2: Monitoring
watch -n 30 'php artisan app:sync-status --latest'
```

### 3. Cleanup Old Batches

Setelah beberapa waktu, bersihkan batch lama:

```sql
-- Hapus batch yang sudah > 30 hari
DELETE FROM sync_progress WHERE batch_id IN (
    SELECT batch_id FROM sync_batches
    WHERE started_at < NOW() - INTERVAL '30 days'
);

DELETE FROM sync_batches
WHERE started_at < NOW() - INTERVAL '30 days';
```

### 4. Set Cron untuk Auto-Resume

Jika sering mati listrik, buat cron untuk auto-resume:

```bash
# /etc/crontab
# Setiap jam, cek apakah ada batch interrupted dan resume
0 * * * * cd /path/to/project && php artisan app:sync-resume-interrupted
```

## Command Reference

### app:sync-all

```bash
php artisan app:sync-all [options]

Options:
  --connection=     Sync dari koneksi tertentu (comma-separated)
  --skip-step1      Skip Step 1 (single-source tables)
  --tables=         Sync tabel tertentu saja (comma-separated)
  --resume=         Resume dari batch ID tertentu
```

### app:sync-status

```bash
php artisan app:sync-status [batch_id] [options]

Arguments:
  batch_id          Batch ID yang ingin dicek (optional)

Options:
  --latest          Tampilkan status batch terakhir
  --all             Tampilkan semua batch (20 terakhir)
  --failed          Tampilkan hanya tabel yang gagal
```

### app:sync-table

```bash
php artisan app:sync-table {model} [options]

Arguments:
  model             Nama model yang akan di-sync

Options:
  --connection=     Koneksi database yang digunakan
  --batch-id=       Batch ID untuk tracking (otomatis dari sync-all)
```

## Database Queries

### Cek Progress Batch Tertentu

```sql
SELECT
    sp.connection_name,
    sp.table_name,
    sp.status,
    sp.records_processed,
    sp.duration_seconds,
    sp.error_message
FROM sync_progress sp
WHERE sp.batch_id = 'sync_20251108_143052_aB3dEf9h'
ORDER BY sp.started_at DESC;
```

### Cek Tabel yang Paling Sering Gagal

```sql
SELECT
    table_name,
    COUNT(*) as fail_count,
    AVG(retry_count) as avg_retries
FROM sync_progress
WHERE status = 'failed'
GROUP BY table_name
ORDER BY fail_count DESC
LIMIT 10;
```

### Cek Rata-rata Durasi per Tabel

```sql
SELECT
    table_name,
    AVG(duration_seconds) as avg_duration,
    AVG(records_processed) as avg_records
FROM sync_progress
WHERE status = 'completed'
GROUP BY table_name
ORDER BY avg_duration DESC;
```

## Notes

-   Batch ID format: `sync_YmdHis_random8chars`
-   Status colors di terminal: green (completed), yellow (running/in_progress), red (failed), magenta (interrupted), gray (pending), cyan (skipped)
-   Progress percentage dihitung dari: `(completed_tables / total_tables) * 100`
-   Duration dalam format: `HH:MM:SS`
-   Records count diformat dengan thousand separator untuk readability

## Future Enhancements

Potential improvements:

-   [ ] Email notification saat batch completed/failed
-   [ ] Slack/Discord webhook untuk monitoring
-   [ ] Web dashboard untuk visualisasi progress
-   [ ] Auto-retry failed tables dengan exponential backoff
-   [ ] Export progress report ke PDF/Excel
-   [ ] Grafik durasi dan success rate per tabel
