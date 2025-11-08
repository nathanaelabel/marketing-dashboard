# Sync Progress Tracking - Quick Guide

## 🚀 Quick Start

### 1. Setup (Sekali Saja)
```bash
php artisan migrate
```

### 2. Run Sync
```bash
php artisan app:sync-all
```

**Output:**
```
Created new batch: sync_20251108_143052_aB3dEf9h  ← SIMPAN INI!
```

### 3. Monitor Progress
```bash
# Cek status terakhir
php artisan app:sync-status --latest
```

## ⚡ Common Scenarios

### Mati Listrik / Server Restart

**Masalah:** Sync terputus, tidak tau sudah sampai mana.

**Solusi:**
```bash
# 1. Cek batch terakhir
php artisan app:sync-status --latest

# 2. Resume dari batch tersebut
php artisan app:sync-all --resume=sync_20251108_143052_aB3dEf9h
```

### Cek Tabel Mana yang Gagal

```bash
# Lihat hanya yang failed
php artisan app:sync-status {batch_id} --failed
```

### Lihat History Sync

```bash
# Lihat 20 batch terakhir
php artisan app:sync-status --all
```

## 📊 Status Indicators

| Status | Arti | Warna |
|--------|------|-------|
| `pending` | Belum diproses | Gray |
| `in_progress` | Sedang berjalan | Yellow |
| `completed` | Selesai sukses | Green |
| `failed` | Gagal | Red |
| `interrupted` | Terputus | Magenta |
| `skipped` | Di-skip (sudah selesai) | Cyan |

## 🔧 Useful Commands

```bash
# Sync normal
php artisan app:sync-all

# Sync koneksi tertentu
php artisan app:sync-all --connection=pgsql_lmp

# Sync tabel tertentu
php artisan app:sync-all --tables=MProduct,CInvoice

# Resume batch
php artisan app:sync-all --resume={batch_id}

# Status batch terakhir
php artisan app:sync-status --latest

# Status batch tertentu
php artisan app:sync-status {batch_id}

# Lihat yang failed saja
php artisan app:sync-status {batch_id} --failed

# Lihat semua batch
php artisan app:sync-status --all
```

## 💡 Tips

### Monitor Real-time
```bash
# Terminal 1: Run sync
php artisan app:sync-all

# Terminal 2: Monitor (refresh tiap 30 detik)
watch -n 30 'php artisan app:sync-status --latest'
```

### Save Log
```bash
php artisan app:sync-all | tee sync-$(date +%Y%m%d-%H%M%S).log
```

### Cleanup Old Batches (> 30 hari)
```sql
DELETE FROM sync_progress WHERE batch_id IN (
    SELECT batch_id FROM sync_batches 
    WHERE started_at < NOW() - INTERVAL '30 days'
);

DELETE FROM sync_batches 
WHERE started_at < NOW() - INTERVAL '30 days';
```

## 📝 What Gets Tracked

Setiap sync mencatat:
- ✅ Batch ID unik
- ✅ Status setiap tabel (pending → in_progress → completed/failed)
- ✅ Waktu mulai & selesai
- ✅ Durasi (dalam detik)
- ✅ Jumlah records processed & skipped
- ✅ Error message (jika gagal)
- ✅ Retry count

## 🆘 Troubleshooting

### Sync Stuck?
```bash
# 1. Stop dengan Ctrl+C
# 2. Cek status
php artisan app:sync-status --latest
# 3. Resume
php artisan app:sync-all --resume={batch_id}
```

### Banyak yang Failed?
```bash
# Lihat yang failed
php artisan app:sync-status {batch_id} --failed

# Resume akan auto-retry failed tables
php artisan app:sync-all --resume={batch_id}
```

### Lupa Batch ID?
```bash
# Lihat semua batch
php artisan app:sync-status --all

# Atau langsung lihat yang terakhir
php artisan app:sync-status --latest
```

## 📂 Database Tables

- `sync_batches` - Info setiap run sync
- `sync_progress` - Detail progress per tabel

Query manual jika perlu:
```sql
-- Cek progress batch tertentu
SELECT * FROM sync_progress 
WHERE batch_id = 'sync_20251108_143052_aB3dEf9h'
ORDER BY started_at DESC;

-- Cek tabel yang sering gagal
SELECT table_name, COUNT(*) as fail_count
FROM sync_progress
WHERE status = 'failed'
GROUP BY table_name
ORDER BY fail_count DESC;
```

---

**Dokumentasi lengkap:** Lihat `SYNC_PROGRESS_TRACKING.md`
