# Data Sync - Supervisor Setup Guide

## Overview

Dokumen ini menjelaskan cara setup **Supervisor** untuk menjalankan Laravel Scheduler secara kontinyu. Supervisor adalah alternatif yang lebih reliable daripada cron job untuk production environment.

**Catatan Penting:**
- Jika menggunakan Supervisor dengan `schedule:work`, **TIDAK PERLU** setup cron job
- Supervisor lebih reliable karena tidak bergantung pada cron service
- Supervisor otomatis restart jika process crash

## Prerequisites

Pastikan Anda sudah menyelesaikan setup dasar dari `PRODUCTION_SYNC_DEPLOYMENT.md`:
- ✅ PHP 8.1+ terinstall
- ✅ Composer terinstall
- ✅ Repository sudah di-clone
- ✅ Dependencies sudah di-install
- ✅ Environment configuration sudah selesai

## Setup Supervisor

### 1. Install Supervisor

```bash
# Install Supervisor
sudo apt update
sudo apt install -y supervisor

# Verifikasi instalasi
sudo supervisorctl --version
```

### 2. Buat Konfigurasi Supervisor

Buat file konfigurasi untuk Laravel Scheduler:

```bash
sudo nano /etc/supervisor/conf.d/marketing-dashboard-scheduler.conf
```

Isi dengan konfigurasi berikut (sesuaikan path dengan struktur server Anda):

```ini
[program:marketing-dashboard-scheduler]
process_name=%(program_name)s
command=php /var/www/html/web/marketing-dashboard/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/web/marketing-dashboard/storage/logs/scheduler.log
stopwaitsecs=3600
```

**Penjelasan Konfigurasi:**
- `command`: Command yang dijalankan (`schedule:work` menjalankan scheduler secara kontinyu)
- `autostart=true`: Otomatis start saat Supervisor start
- `autorestart=true`: Otomatis restart jika process crash
- `user=www-data`: User yang menjalankan process (sesuaikan dengan user aplikasi)
- `stdout_logfile`: Lokasi log file
- `stopwaitsecs=3600`: Waktu tunggu sebelum force kill (3600 detik = 1 jam)

### 3. Aktifkan dan Start Supervisor

```bash
# Reload konfigurasi supervisor
sudo supervisorctl reread

# Update supervisor dengan konfigurasi baru
sudo supervisorctl update

# Start program
sudo supervisorctl start marketing-dashboard-scheduler

# Check status
sudo supervisorctl status marketing-dashboard-scheduler
```

**Output yang diharapkan:**
```
marketing-dashboard-scheduler    RUNNING   pid 12345, uptime 0:00:05
```

### 4. Verifikasi Scheduler Berjalan

```bash
# Check status
sudo supervisorctl status marketing-dashboard-scheduler

# Monitor log real-time
tail -f /var/www/html/web/marketing-dashboard/storage/logs/scheduler.log

# Test manual scheduler (harus return SUCCESS)
cd /var/www/html/web/marketing-dashboard
php artisan schedule:list
```

## Supervisor Commands Reference

### Management Commands

```bash
# Start service
sudo supervisorctl start marketing-dashboard-scheduler

# Stop service
sudo supervisorctl stop marketing-dashboard-scheduler

# Restart service
sudo supervisorctl restart marketing-dashboard-scheduler

# Reload konfigurasi (setelah edit config file)
sudo supervisorctl reread
sudo supervisorctl update

# Lihat semua program yang dikelola Supervisor
sudo supervisorctl status

# Lihat log real-time
sudo supervisorctl tail -f marketing-dashboard-scheduler
```

### Monitoring Commands

```bash
# Check status detail
sudo supervisorctl status marketing-dashboard-scheduler

# Lihat log stdout
sudo supervisorctl tail marketing-dashboard-scheduler stdout

# Lihat log stderr
sudo supervisorctl tail marketing-dashboard-scheduler stderr

# Clear log
sudo supervisorctl clear marketing-dashboard-scheduler
```

## Setup Log Rotation untuk Supervisor

### 1. Buat Konfigurasi Logrotate

```bash
sudo nano /etc/logrotate.d/marketing-dashboard-supervisor
```

Isi dengan:

```
/var/www/html/web/marketing-dashboard/storage/logs/scheduler.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        supervisorctl restart marketing-dashboard-scheduler > /dev/null 2>&1 || true
    endscript
}
```

### 2. Test Logrotate

```bash
# Test konfigurasi
sudo logrotate -d /etc/logrotate.d/marketing-dashboard-supervisor

# Force run (untuk testing)
sudo logrotate -f /etc/logrotate.d/marketing-dashboard-supervisor
```

## Troubleshooting

### Supervisor Tidak Start

**Gejala**: Service tidak bisa start atau langsung crash

**Solusi**:
```bash
# 1. Check konfigurasi syntax
sudo supervisorctl reread

# 2. Check log supervisor
sudo tail -f /var/log/supervisor/supervisord.log

# 3. Check log aplikasi
tail -f /var/www/html/web/marketing-dashboard/storage/logs/scheduler.log

# 4. Check apakah user www-data ada
id www-data

# 5. Pastikan path dan permission benar
ls -la /var/www/html/web/marketing-dashboard/artisan
sudo chmod +x /var/www/html/web/marketing-dashboard/artisan

# 6. Test command manual
cd /var/www/html/web/marketing-dashboard
php artisan schedule:work
```

### Supervisor Service Tidak Berjalan

**Gejala**: Supervisor daemon tidak running

**Solusi**:
```bash
# 1. Check status supervisor service
sudo systemctl status supervisor

# 2. Start supervisor service
sudo systemctl start supervisor
sudo systemctl enable supervisor

# 3. Check log
sudo tail -f /var/log/supervisor/supervisord.log
```

### Permission Denied

**Gejala**: Error permission saat Supervisor mencoba start

**Solusi**:
```bash
# 1. Set ownership untuk storage dan artisan
cd /var/www/html/web/marketing-dashboard
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod +x artisan

# 2. Pastikan user di config benar
# Check: user=www-data di /etc/supervisor/conf.d/marketing-dashboard-scheduler.conf
```

### Scheduler Tidak Dieksekusi

**Gejala**: Supervisor running tapi scheduled tasks tidak jalan

**Solusi**:
```bash
# 1. Check apakah schedule:work berjalan
sudo supervisorctl status marketing-dashboard-scheduler

# 2. Check log untuk error
tail -f /var/www/html/web/marketing-dashboard/storage/logs/scheduler.log

# 3. Test manual
cd /var/www/html/web/marketing-dashboard
php artisan schedule:run

# 4. Check scheduled tasks
php artisan schedule:list

# 5. Pastikan timezone benar
php artisan tinker
>>> config('app.timezone')
# Harus return: "Asia/Jakarta"
```

### Process Restart Terus Menerus

**Gejala**: Process start lalu langsung crash dan restart

**Solusi**:
```bash
# 1. Check log untuk error
tail -f /var/www/html/web/marketing-dashboard/storage/logs/scheduler.log

# 2. Check Laravel log
tail -f /var/www/html/web/marketing-dashboard/storage/logs/laravel.log

# 3. Test command manual untuk melihat error
cd /var/www/html/web/marketing-dashboard
php artisan schedule:work

# 4. Check PHP error log
sudo tail -f /var/log/php8.1-fpm.log
# atau
sudo tail -f /var/log/php/error.log
```

## Monitoring & Maintenance

### Daily Monitoring

```bash
# Check status
sudo supervisorctl status marketing-dashboard-scheduler

# Monitor log
tail -f /var/www/html/web/marketing-dashboard/storage/logs/scheduler.log

# Check scheduled tasks
cd /var/www/html/web/marketing-dashboard
php artisan schedule:list
```

### Weekly Maintenance

```bash
# Check disk space untuk log files
du -sh /var/www/html/web/marketing-dashboard/storage/logs/

# Check log rotation
sudo logrotate -d /etc/logrotate.d/marketing-dashboard-supervisor

# Restart jika perlu (setelah update code)
sudo supervisorctl restart marketing-dashboard-scheduler
```

### After Code Update

```bash
# 1. Pull latest code
cd /var/www/html/web/marketing-dashboard
git pull origin main

# 2. Install dependencies (jika ada)
composer install --optimize-autoloader --no-dev

# 3. Clear cache
php artisan config:clear
php artisan cache:clear

# 4. Restart supervisor
sudo supervisorctl restart marketing-dashboard-scheduler

# 5. Verify
sudo supervisorctl status marketing-dashboard-scheduler
php artisan schedule:list
```

## Perbandingan: Supervisor vs Cron Job

| Aspek | Supervisor | Cron Job |
|-------|-----------|----------|
| **Reliability** | ✅ Sangat tinggi (auto-restart) | ⚠️ Bergantung pada cron service |
| **Monitoring** | ✅ Built-in monitoring | ⚠️ Perlu setup terpisah |
| **Logging** | ✅ Terpusat di satu file | ⚠️ Tersebar di multiple files |
| **Resource Usage** | ⚠️ Process berjalan kontinyu | ✅ Hanya saat dieksekusi |
| **Setup Complexity** | ⚠️ Lebih kompleks | ✅ Lebih sederhana |
| **Recommended For** | Production (high availability) | Development / Simple setup |

## Best Practices

1. **Selalu monitor log**: Check log secara berkala untuk error
2. **Setup log rotation**: Mencegah log files memenuhi disk
3. **Test setelah update**: Restart dan verify setelah update code
4. **Backup konfigurasi**: Simpan backup konfigurasi Supervisor
5. **Documentation**: Catat perubahan konfigurasi untuk referensi

## Support & Contact

Untuk issue atau pertanyaan:
- Check logs: `/var/www/html/web/marketing-dashboard/storage/logs/`
- Supervisor log: `/var/log/supervisor/supervisord.log`
- Review error messages
- Contact: [Your Contact Info]

---

**Last Updated**: November 2024  
**Status**: Production Ready ✅

