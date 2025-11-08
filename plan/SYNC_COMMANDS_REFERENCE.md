# Adempiere Data Sync - Commands Reference

## 🆕 Progress Tracking (NEW)

Sistem tracking untuk monitoring dan resume sync yang terinterupsi.

### Check Sync Status
```bash
# Lihat status batch terakhir
php artisan app:sync-status --latest

# Lihat status batch tertentu
php artisan app:sync-status sync_20251108_143052_aB3dEf9h

# Lihat hanya tabel yang failed
php artisan app:sync-status sync_20251108_143052_aB3dEf9h --failed

# Lihat semua batch (20 terakhir)
php artisan app:sync-status --all
```

### Resume Interrupted Sync
```bash
# Resume dari batch yang terinterupsi
php artisan app:sync-all --resume=sync_20251108_143052_aB3dEf9h
```

**Fitur Resume:**
- ✅ Skip tabel yang sudah completed
- ✅ Retry tabel yang failed
- ✅ Lanjutkan dari progress terakhir
- ✅ Preserve batch statistics

**Dokumentasi lengkap:** Lihat `SYNC_PROGRESS_TRACKING.md` dan `SYNC_QUICK_GUIDE.md`

---

## Production Commands (Recommended)

### Full Sync Commands

#### Sync All Tables from All Branches
```bash
php artisan app:sync-all
```
**Kapan digunakan:**
- Daily sync pukul 08:30 WIB (otomatis via scheduler)
- Initial sync saat deployment
- Recovery setelah downtime
- Memastikan data 1:1 dengan semua cabang

**Fitur:**
- ✅ Insert new records
- ✅ Update existing records (upsert)
- ✅ Retry logic untuk connection timeout
- ✅ Comprehensive logging
- ✅ Foreign key validation

#### Sync Specific Connection
```bash
php artisan app:sync-all --connection=pgsql_lmp
```
**Kapan digunakan:**
- Sync hanya dari 1 cabang tertentu
- Testing koneksi cabang baru
- Recovery untuk cabang spesifik

#### Sync Specific Tables
```bash
php artisan app:sync-all --tables=MProduct,CInvoice,COrder
```
**Kapan digunakan:**
- Update tabel tertentu saja
- Testing perubahan model
- Quick sync untuk tabel prioritas

#### Sync Single Table from Single Branch
```bash
php artisan app:sync-table MProduct --connection=pgsql_sby
```
**Kapan digunakan:**
- Testing sync untuk 1 tabel
- Manual update tabel tertentu
- Debugging sync issues

### Incremental Sync Commands

#### Incremental Sync All Tables
```bash
php artisan app:incremental-sync-all
```
**Kapan digunakan:**
- Setiap 30 menit (otomatis via scheduler)
- Quick update untuk data terbaru
- Real-time sync untuk transaction tables

**Fitur:**
- ✅ Hanya sync record yang berubah sejak sync terakhir
- ✅ Menggunakan timestamp (created_at/updated_at)
- ✅ Lebih cepat dari full sync
- ✅ Minimal resource usage

#### Incremental Sync Single Table
```bash
php artisan app:incremental-sync-table CInvoice
```
**Kapan digunakan:**
- Update cepat untuk 1 tabel
- Testing incremental sync
- Manual refresh data terbaru

---

## Development Commands (Legacy)

### Fast Sync (Development Only)
```bash
php artisan app:fast-sync-adempiere-table MProduct --connection=pgsql_sby
```
**⚠️ Warning:** Insert-only, tidak ada update mechanism

### Sync All Development
```bash
php artisan app:sync-all-adempiere-data
```
**⚠️ Warning:** Development version, gunakan production version untuk production

---

## Deprecated Commands (Old Production)

### ❌ app:sync-adempiere-table
```bash
# DEPRECATED - Gunakan app:sync-table
php artisan app:sync-adempiere-table MProduct --connection=pgsql_sby
```

### ❌ app:sync-incremental-adempiere-table
```bash
# DEPRECATED - Gunakan app:incremental-sync-table
php artisan app:sync-incremental-adempiere-table CInvoice
```

### ❌ app:sync-prune-adempiere-table
```bash
# DEPRECATED - Pruning tidak diperlukan dengan upsert mechanism
php artisan app:sync-prune-adempiere-table MProduct
```

---

## Scheduler Commands

### List Scheduled Tasks
```bash
php artisan schedule:list
```

### Run Scheduler Manually
```bash
php artisan schedule:run
```

### Test Scheduler (Keep Running)
```bash
php artisan schedule:work
```

---

## Utility Commands

### Clear All Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Check Command List
```bash
php artisan list | grep sync
php artisan list | grep production
```

### View Command Help
```bash
php artisan app:sync-all --help
```

---

## Common Use Cases

### 1. Initial Deployment
```bash
# Step 1: Clear cache
php artisan config:clear && php artisan cache:clear

# Step 2: Run full sync
php artisan app:sync-all

# Step 3: Verify scheduler
php artisan schedule:list
```

### 2. Daily Operations (Automated)
```bash
# Scheduler akan otomatis menjalankan:
# - 08:30 WIB: Full sync
# - Setiap 30 menit: Incremental sync
```

### 3. Emergency Recovery
```bash
# Sync semua data dari semua cabang
php artisan app:sync-all

# Atau sync cabang tertentu yang bermasalah
php artisan app:sync-all --connection=pgsql_lmp
```

### 4. Testing New Branch Connection
```bash
# Test koneksi
php artisan app:sync-table AdOrg --connection=pgsql_new_branch

# Jika sukses, sync semua tabel
php artisan app:sync-all --connection=pgsql_new_branch
```

### 5. Quick Update Specific Tables
```bash
# Update hanya tabel invoice dan order
php artisan app:sync-all --tables=CInvoice,COrder,CInvoiceline,COrderline
```

### 6. Manual Incremental Sync
```bash
# Sync incremental untuk semua tabel
php artisan app:incremental-sync-all

# Atau untuk tabel tertentu
php artisan app:incremental-sync-table CInvoice
```

---

## Monitoring Commands

### Check Logs
```bash
# Full sync logs
tail -f storage/logs/sync-full.log

# Incremental sync logs
tail -f storage/logs/sync-incremental.log

# Laravel logs
tail -f storage/logs/laravel.log

# Detailed sync logs
tail -f storage/logs/sync.log
```

### Check Sync Timestamps
```bash
# List all timestamp files
ls -la storage/app/sync-timestamp-*.txt

# View specific timestamp
cat storage/app/sync-timestamp-c_invoice.txt
```

### Check Process Status
```bash
# Check if scheduler is running
ps aux | grep "schedule:work"

# Check supervisor status (if using supervisor)
sudo supervisorctl status marketing-dashboard-scheduler
```

---

## Troubleshooting Commands

### Connection Test
```bash
# Test database connection
php artisan tinker
>>> DB::connection('pgsql_lmp')->getPdo();
>>> DB::connection('pgsql_lmp')->table('ad_org')->count();
```

### Memory Issues
```bash
# Run with unlimited memory
php -d memory_limit=-1 artisan app:sync-all
```

### Timeout Issues
```bash
# Check connection timeout settings in config/database.php
# Retry logic akan otomatis handle timeout (3x retry dengan 10s delay)
```

### Clear Sync Timestamps (Force Full Resync)
```bash
# Remove all timestamp files
rm storage/app/sync-timestamp-*.txt

# Next incremental sync akan mulai dari 2021-01-01
```

---

## Performance Tips

### 1. Sync Priority Tables First
```bash
# Sync master data dulu
php artisan app:sync-all --tables=AdOrg,MProduct,CBpartner

# Kemudian transaction data
php artisan app:sync-all --tables=CInvoice,COrder
```

### 2. Parallel Sync (Advanced)
```bash
# Sync beberapa cabang secara parallel (gunakan screen atau tmux)
screen -S sync_lmp
php artisan app:sync-all --connection=pgsql_lmp

# Ctrl+A+D untuk detach, kemudian
screen -S sync_sby
php artisan app:sync-all --connection=pgsql_sby
```

### 3. Off-Peak Hours
```bash
# Untuk full sync manual, jalankan di luar jam kerja
# Scheduler sudah diset 08:30 WIB (sebelum jam kerja)
```

---

## Migration from Old Commands

### Before (Old)
```bash
php artisan app:sync-all-adempiere-data --type=full
php artisan app:sync-incremental-adempiere-table CInvoice
```

### After (New)
```bash
php artisan app:sync-all
php artisan app:incremental-sync-table CInvoice
```

### Key Differences
- ✅ Upsert mechanism (insert + update)
- ✅ Better retry logic
- ✅ Improved logging
- ✅ Timezone support (Asia/Jakarta)
- ✅ Better error handling
- ✅ Comprehensive foreign key validation

---

**Last Updated**: November 2024
**Version**: 1.0.0 Production
