# Data Sync - Deployment Guide

## Overview

Sistem sync ini dirancang untuk melakukan sinkronisasi data dari 17 cabang database Adempiere ke database Marketing Dashboard dengan mekanisme **insert/update** yang robust. Sistem ini menggantikan mekanisme development yang hanya insert-only.

## Fitur Utama

### 1. **Full Sync (Daily at 08:30 WIB)**
- Berjalan setiap hari pukul 08:30 WIB (Timezone Asia/Jakarta)
- Melakukan **upsert** (insert baru atau update existing) untuk semua tabel
- Memastikan data 1:1 dengan 17 database cabang
- Menggunakan retry logic untuk connection timeout
- Logging lengkap untuk monitoring

### 2. **Incremental Sync (Every 30 Minutes)**
- Berjalan setiap 30 menit
- Hanya memproses record yang baru dibuat atau diupdate sejak sync terakhir
- Lebih cepat dan efisien untuk update real-time
- Menggunakan timestamp (created_at/updated_at) untuk tracking

## Struktur File

### Commands
```
app/Console/Commands/Production/
├── SyncTableCommand.php                # Core sync command untuk single table
├── SyncAllCommand.php                  # Orchestrator untuk full sync
├── IncrementalSyncTableCommand.php     # Incremental sync untuk single table
└── IncrementalSyncAllCommand.php       # Orchestrator untuk incremental sync
```

### Scheduling
- File: `app/Console/Kernel.php`
- Full Sync: `08:30 WIB` daily
- Incremental Sync: Every `30 minutes`

## Table Configuration

### Single Source Tables
Tables yang hanya sync dari 1 cabang tertentu:
- **pgsql_lmp**: AdOrg
- **pgsql_sby**: MProductCategory, MProductsubcat, MProduct

### Full Sync Tables
Tables yang sync semua record dari 17 cabang:
- MLocator
- MStorage
- MPricelistVersion
- CBpartner
- CBpartnerLocation

### Date Filtered Tables
Tables dengan filter tanggal (2021-01-01 s/d today):
- CInvoice (dateinvoiced)
- COrder (dateordered)
- CAllocationhdr (datetrx)
- MInout (movementdate)
- MMatchinv (datetrx)

### Relationship Filtered Tables
Tables dengan filter berdasarkan foreign key:
- MProductprice (m_product_id)
- CInvoiceline (c_invoice_id)
- COrderline (c_order_id)
- CAllocationline (c_allocationhdr_id)
- MInoutline (m_inout_id)

## Deployment ke Ubuntu Server

### 1. Prerequisites
```bash
# Pastikan PHP 8.1+ dan Composer terinstall
php -v
composer -v

# Pastikan PostgreSQL client terinstall
psql --version
```

### 2. Clone/Update Repository
```bash
cd /path/to/marketing-dashboard
git pull origin main
```

### 3. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

### 4. Environment Configuration
Pastikan file `.env` sudah dikonfigurasi dengan benar untuk 17 koneksi database:

```env
# Database Marketing Dashboard (Local)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marketing_dashboard
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Adempiere Branch Connections (17 cabang)
# Format: DB_PGSQL_XXX_HOST, DB_PGSQL_XXX_PORT, dll
# Contoh untuk cabang LMP:
DB_PGSQL_LMP_HOST=192.168.1.100
DB_PGSQL_LMP_PORT=5432
DB_PGSQL_LMP_DATABASE=adempiere_lmp
DB_PGSQL_LMP_USERNAME=adempiere
DB_PGSQL_LMP_PASSWORD=password

# ... (ulangi untuk 16 cabang lainnya)
```

### 5. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 6. Setup Cron Job
Edit crontab untuk menjalankan Laravel scheduler:

```bash
crontab -e
```

Tambahkan baris berikut:
```cron
* * * * * cd /path/to/marketing-dashboard && php artisan schedule:run >> /dev/null 2>&1
```

### 7. Setup Supervisor (Recommended)
Untuk memastikan scheduler selalu berjalan, gunakan Supervisor:

```bash
sudo apt-get install supervisor
```

Buat file konfigurasi:
```bash
sudo nano /etc/supervisor/conf.d/marketing-dashboard-scheduler.conf
```

Isi dengan:
```ini
[program:marketing-dashboard-scheduler]
process_name=%(program_name)s
command=php /path/to/marketing-dashboard/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/marketing-dashboard/storage/logs/scheduler.log
```

Reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start marketing-dashboard-scheduler
```

### 8. Setup Log Rotation
Buat file konfigurasi logrotate:

```bash
sudo nano /etc/logrotate.d/marketing-dashboard
```

Isi dengan:
```
/path/to/marketing-dashboard/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 9. Permissions
```bash
cd /path/to/marketing-dashboard
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Manual Testing

### Test Single Table Sync
```bash
# Test sync untuk 1 tabel dari 1 cabang
php artisan app:sync-table MProduct --connection=pgsql_sby
```

### Test Full Sync
```bash
# Test full sync untuk semua tabel
php artisan app:sync-all
```

### Test Incremental Sync
```bash
# Test incremental sync untuk 1 tabel
php artisan app:incremental-sync-table CInvoice

# Test incremental sync untuk semua tabel
php artisan app:incremental-sync-all
```

### Test Specific Connection
```bash
# Sync hanya dari cabang tertentu
php artisan app:sync-all --connection=pgsql_lmp
```

### Test Specific Tables
```bash
# Sync hanya tabel tertentu
php artisan app:sync-all --tables=MProduct,CInvoice
```

## Monitoring

### Log Files
```bash
# Full sync logs
tail -f storage/logs/sync-full.log

# Incremental sync logs
tail -f storage/logs/sync-incremental.log

# Laravel logs
tail -f storage/logs/laravel.log

# Sync channel logs (detailed)
tail -f storage/logs/sync.log
```

### Check Scheduler Status
```bash
# Lihat scheduled tasks
php artisan schedule:list

# Test run scheduler
php artisan schedule:run
```

### Database Monitoring
```sql
-- Check record counts per table
SELECT 
    schemaname,
    tablename,
    n_live_tup as row_count
FROM pg_stat_user_tables
WHERE schemaname = 'public'
ORDER BY n_live_tup DESC;

-- Check last sync timestamps
SELECT * FROM information_schema.tables 
WHERE table_schema = 'public' 
ORDER BY table_name;
```

## Troubleshooting

### Connection Timeout
Jika terjadi connection timeout, sistem akan:
1. Retry otomatis 3x dengan delay 10 detik
2. Log error ke file log
3. Skip ke connection berikutnya
4. Retry di akhir proses

### Memory Issues
```bash
# Increase PHP memory limit di .env atau php.ini
memory_limit = -1

# Atau via command
php -d memory_limit=-1 artisan app:sync-all
```

### Disk Space
```bash
# Check disk space
df -h

# Clean old logs
find storage/logs -name "*.log" -mtime +30 -delete
```

### Failed Sync Recovery
Jika sync gagal di tengah jalan:
```bash
# Check timestamp files
ls -la storage/app/sync-timestamp-*.txt

# Manual retry untuk tabel tertentu
php artisan app:sync-table [ModelName] --connection=[connection]
```

## Performance Optimization

### Database Indexes
Pastikan index sudah ada untuk kolom yang sering diquery:
- Primary keys
- Foreign keys
- created_at, updated_at (untuk incremental sync)
- Date columns (dateinvoiced, dateordered, dll)

### Connection Pooling
Gunakan PgBouncer untuk connection pooling ke 17 database cabang.

### Chunk Size Tuning
Edit chunk size di command jika perlu:
```php
// Default: 500
$model->upsert($chunk->toArray(), $keyColumns);
```

## Rollback Plan

Jika perlu rollback ke sistem lama:
```bash
# 1. Update Kernel.php ke scheduling lama
# 2. Restart scheduler
sudo supervisorctl restart marketing-dashboard-scheduler

# 3. Clear cache
php artisan config:clear
php artisan cache:clear
```

## Support & Contact

Untuk issue atau pertanyaan:
- Check logs: `storage/logs/`
- Review error messages
- Contact: [Your Contact Info]

## Changelog

### Version 1.0.0 (Production Release)
- ✅ Full sync dengan upsert mechanism
- ✅ Incremental sync setiap 30 menit
- ✅ Retry logic untuk connection timeout
- ✅ Comprehensive logging
- ✅ Timezone Asia/Jakarta support
- ✅ 17 branch database support
- ✅ Foreign key dependency validation
- ✅ Date filtering untuk transaction tables
- ✅ Relationship filtering untuk detail tables

---

**Last Updated**: November 2024
**Status**: Production Ready ✅
