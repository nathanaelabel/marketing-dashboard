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

## Deployment ke Ubuntu Server Produksi

### 1. Prerequisites & Initial Setup

#### 1.1. Update System Packages
```bash
# Login ke server via SSH
ssh user@your-server-ip

# Update package list
sudo apt update
sudo apt upgrade -y
```

#### 1.2. Install PHP 8.1+ dan Extensions
```bash
# Install PHP dan extensions yang diperlukan
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-common php8.1-mysql php8.1-pgsql \
    php8.1-zip php8.1-gd php8.1-mbstring php8.1-curl php8.1-xml php8.1-bcmath

# Verifikasi instalasi
php -v
php -m | grep -E "pgsql|pdo"
```

#### 1.3. Install Composer
```bash
# Download dan install Composer
cd ~
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verifikasi instalasi
composer --version
```

#### 1.4. Install PostgreSQL Client (jika belum ada)
```bash
sudo apt install -y postgresql-client
psql --version
```

### 2. Clone/Setup Repository
```bash
# Navigate ke directory web server (sesuaikan dengan setup Anda)
cd /var/www  # atau /home/user/www, atau sesuai struktur server Anda

# Clone repository (jika belum ada)
git clone <repository-url> marketing-dashboard
cd marketing-dashboard

# Atau jika sudah ada, update
git pull origin main
```

### 3. Install Dependencies
```bash
# Install dependencies production
composer install --optimize-autoloader --no-dev

# Jika ada error permission, set ownership
sudo chown -R $USER:$USER /path/to/marketing-dashboard
```

### 4. Environment Configuration
Pastikan file `.env` sudah dikonfigurasi dengan benar untuk 17 koneksi database:

```bash
# Copy file .env.example jika belum ada
cp .env.example .env

# Edit file .env
nano .env
```

Konfigurasi minimal yang diperlukan:

```env
APP_NAME="Marketing Dashboard"
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Marketing Dashboard (Local)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marketing_dashboard
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Timezone untuk scheduler
APP_TIMEZONE=Asia/Jakarta

# Adempiere Branch Connections (17 cabang)
# Format: DB_PGSQL_XXX_HOST, DB_PGSQL_XXX_PORT, dll
# Contoh untuk cabang LMP:
DB_PGSQL_LMP_HOST=192.168.1.100
DB_PGSQL_LMP_PORT=5432
DB_PGSQL_LMP_DATABASE=adempiere_lmp
DB_PGSQL_LMP_USERNAME=adempiere
DB_PGSQL_LMP_PASSWORD=password

# ... (ulangi untuk 16 cabang lainnya: SBY, JKT, BDG, dll)
```

**Generate APP_KEY jika belum ada:**
```bash
php artisan key:generate
```

### 5. Database Setup
```bash
# Run migrations
php artisan migrate --force

# Verifikasi koneksi database
php artisan db:show
```

### 6. Clear Cache & Optimize
```bash
# Clear semua cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize untuk production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Setup Laravel Scheduler (Cron Job) - **WAJIB**

Laravel Scheduler memerlukan cron job yang berjalan setiap menit untuk memanggil `schedule:run`. Tanpa ini, scheduler tidak akan berjalan otomatis.

**Catatan**: Jika Anda ingin menggunakan **Supervisor** sebagai alternatif yang lebih reliable, lihat dokumentasi di `PRODUCTION_SYNC_SUPERVISOR.md`. Supervisor menggunakan `schedule:work` yang berjalan kontinyu dan tidak memerlukan cron job.

#### 7.1. Edit Crontab
```bash
# Edit crontab untuk user yang menjalankan aplikasi (biasanya www-data atau user aplikasi)
sudo crontab -e -u www-data

# Atau jika menggunakan user biasa
crontab -e
```

#### 7.2. Tambahkan Entry Cron Job
Tambahkan baris berikut di crontab (ganti `/path/to/marketing-dashboard` dengan path aktual):

```cron
# Laravel Scheduler - Jalankan setiap menit
* * * * * cd /var/www/html/web/marketing-dashboard && php artisan schedule:run >> /dev/null 2>&1
```

**Penjelasan:**
- `* * * * *` = Setiap menit
- `cd /var/www/html/web/marketing-dashboard` = Navigate ke directory aplikasi
- `php artisan schedule:run` = Jalankan Laravel scheduler
- `>> /dev/null 2>&1` = Redirect output (opsional, bisa diubah ke log file)

**Alternatif dengan logging:**
```cron
* * * * * cd /var/www/html/web/marketing-dashboard && php artisan schedule:run >> /var/www/html/web/marketing-dashboard/storage/logs/scheduler-cron.log 2>&1
```

#### 7.3. Verifikasi Cron Job
```bash
# Lihat crontab yang sudah di-set
sudo crontab -l -u www-data

# Test manual apakah scheduler berjalan
cd /var/www/html/web/marketing-dashboard
php artisan schedule:run

# Lihat scheduled tasks
php artisan schedule:list
```

#### 7.4. Test Scheduler Berjalan
```bash
# Monitor log untuk melihat apakah scheduler dipanggil
tail -f storage/logs/scheduler-cron.log

# Atau check Laravel log
tail -f storage/logs/laravel.log
```

### 8. Setup Log Rotation
Mencegah log files menjadi terlalu besar dan memenuhi disk space.

#### 8.1. Buat Konfigurasi Logrotate
```bash
sudo nano /etc/logrotate.d/marketing-dashboard
```

Isi dengan:
```
/var/www/html/web/marketing-dashboard/storage/logs/*.log {
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

#### 8.2. Test Logrotate
```bash
# Test konfigurasi logrotate
sudo logrotate -d /etc/logrotate.d/marketing-dashboard

# Force run logrotate (untuk testing)
sudo logrotate -f /etc/logrotate.d/marketing-dashboard
```

### 9. Set Permissions
```bash
# Set ownership untuk storage dan cache
cd /var/www/html/web/marketing-dashboard
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Set permission untuk artisan
sudo chmod +x artisan
```

### 10. Verifikasi Setup Lengkap

> **⚠️ PENTING**: Setelah setup cron job selesai, jangan langsung meninggalkan server! 
> Lihat **`FIRST_RUN_CHECKLIST.md`** untuk panduan verifikasi lengkap sebelum server berjalan otomatis 24/7.

#### 10.1. Test Manual Commands
```bash
# Test sync command
php artisan app:sync-all --tables=MProduct --connection=pgsql_sby

# Test scheduler
php artisan schedule:list

# Test run scheduler manual
php artisan schedule:run
```

#### 10.2. Monitor Scheduler
```bash
# Check cron job
sudo crontab -l -u www-data

# Monitor log files
tail -f storage/logs/scheduler-cron.log
tail -f storage/logs/sync-full.log
tail -f storage/logs/sync-incremental.log
tail -f storage/logs/laravel.log
```

#### 10.3. Check Scheduled Tasks
```bash
# Lihat semua scheduled tasks
php artisan schedule:list

# Output yang diharapkan:
# 0 8 * * *  app:sync-all ................... Next Due: 2024-11-XX 08:30:00
# */30 * * * *  app:incremental-sync-all ... Next Due: 2024-11-XX XX:XX:00
```


## Logika Sync: First Sync vs Subsequent Sync

### First Sync (Eksekusi Pertama)
- **Deteksi**: Sistem otomatis mendeteksi jika tabel masih kosong (tidak ada data)
- **Aksi**: Menggunakan **bulk INSERT** untuk memasukkan data baru
- **Metode**: `insertOrIgnore()` untuk menghindari duplikasi dari multiple connections
- **Performance**: Lebih cepat karena tidak perlu check existing records

### Subsequent Sync (Eksekusi Berikutnya)
- **Deteksi**: Sistem mendeteksi tabel sudah berisi data
- **Aksi**: Menggunakan **UPSERT** (INSERT new + UPDATE existing)
- **Metode**: `upsert()` yang otomatis:
  - **INSERT** record baru jika belum ada (berdasarkan primary key)
  - **UPDATE** record yang sudah ada jika ada perubahan
- **Performance**: Efisien karena hanya memproses record yang berubah

### Cara Kerja
1. **Pertama kali menjalankan `app:sync-all`**:
   - Sistem check apakah tabel kosong
   - Jika kosong → Gunakan bulk INSERT (faster)
   - Jika tidak kosong → Gunakan UPSERT

2. **Eksekusi berikutnya**:
   - Sistem check tabel sudah berisi data
   - Otomatis menggunakan UPSERT
   - Hanya update record yang berubah, insert record baru

### Monitoring
```bash
# Check log untuk melihat apakah first sync atau subsequent
tail -f storage/logs/sync-full.log | grep -E "First sync|Subsequent sync"

# Output contoh:
# First sync detected for m_product. Using bulk INSERT (new data only)...
# Subsequent sync detected for c_invoice. Using UPSERT (INSERT new + UPDATE changed records)...
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

### Scheduler Tidak Berjalan
**Gejala**: Scheduled tasks tidak dieksekusi otomatis

**Solusi**:
```bash
# 1. Check apakah cron job sudah di-set
sudo crontab -l -u www-data

# 2. Test manual scheduler
cd /var/www/html/web/marketing-dashboard
php artisan schedule:run

# 3. Check log untuk error
tail -f storage/logs/scheduler-cron.log
tail -f storage/logs/laravel.log

# 4. Pastikan timezone sudah benar
php artisan tinker
>>> config('app.timezone')
# Harus return: "Asia/Jakarta"
```

### Cron Job Tidak Berjalan
**Gejala**: Cron job tidak dieksekusi

**Solusi**:
```bash
# 1. Check cron service
sudo systemctl status cron

# 2. Start cron service jika tidak running
sudo systemctl start cron
sudo systemctl enable cron

# 3. Check log cron
sudo tail -f /var/log/syslog | grep CRON

# 4. Pastikan path di crontab benar (gunakan absolute path)
which php
# Gunakan full path: /usr/bin/php
```

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
# 2. Clear cache
php artisan config:clear
php artisan cache:clear

# 3. Cron job akan otomatis menggunakan konfigurasi baru setelah cache clear
```

## Support & Contact

Untuk issue atau pertanyaan:
- Check logs: `storage/logs/`
- Review error messages
- Contact: [Your Contact Info]

## Changelog

### Version 1.1.0 (Current)
- ✅ **Optimized first sync**: Deteksi otomatis first sync dan gunakan bulk INSERT
- ✅ **Subsequent sync optimization**: UPSERT otomatis untuk INSERT new + UPDATE changed
- ✅ **Comprehensive production setup guide**: Panduan lengkap setup cron job
- ✅ **Enhanced monitoring**: Logging untuk first sync vs subsequent sync
- ✅ **Separated documentation**: Supervisor setup dipisah ke file terpisah

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
