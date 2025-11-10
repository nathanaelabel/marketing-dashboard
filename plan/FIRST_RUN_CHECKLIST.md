# First Run Checklist - Setelah Setup Cron Job

## Overview

Dokumen ini menjelaskan langkah-langkah yang harus dilakukan setelah setup cron job selesai, sebelum meninggalkan server dan membiarkan sistem berjalan otomatis 24/7.

## ⚠️ PENTING: Jangan Langsung Tutup Server!

Sebelum meninggalkan server, pastikan semua hal berikut sudah diverifikasi dan berjalan dengan baik.

## Step-by-Step Verification

### 1. Verifikasi Cron Job Aktif

```bash
# Check apakah cron job sudah terdaftar
sudo crontab -l -u www-data

# Pastikan output menunjukkan:
# * * * * * cd /var/www/html/web/marketing-dashboard && php artisan schedule:run >> /dev/null 2>&1

# Check apakah cron service berjalan
sudo systemctl status cron

# Output harus menunjukkan: Active: active (running)
```

### 2. Test Manual Scheduler (WAJIB)

```bash
cd /var/www/html/web/marketing-dashboard

# Test run scheduler manual sekali
php artisan schedule:run

# Check output - harus tidak ada error
# Jika ada error, fix dulu sebelum lanjut!
```

### 3. Verifikasi Scheduled Tasks Terdaftar

```bash
# ⚠️ PENTING: Clear cache dulu sebelum check schedule:list
php artisan config:clear
php artisan cache:clear

# Lihat semua scheduled tasks
php artisan schedule:list

# Output yang diharapkan:
# */30 * * * *  app:incremental-sync-all ... Next Due: [waktu 30 menit berikutnya]
# 0 8 * * *     app:sync-all ................ Next Due: [besok jam 08:30 WIB]

# Jika masih kosong, check apakah command terdaftar:
php artisan list | grep sync

# Harus muncul:
# app:incremental-sync-all
# app:sync-all
```

### 4. Test Koneksi Database (Opsional tapi Recommended)

```bash
# Test koneksi ke database utama
php artisan db:show

# Test koneksi ke salah satu branch (contoh: pgsql_sby)
php artisan tinker
>>> DB::connection('pgsql_sby')->getPdo();
# Harus return: PDO object tanpa error
>>> exit
```

### 5. Monitor Scheduler Berjalan (WAJIB - Minimal 5 Menit)

```bash
# Monitor log scheduler (jika menggunakan logging)
tail -f storage/logs/scheduler-cron.log

# Atau monitor Laravel log
tail -f storage/logs/laravel.log

# Biarkan berjalan minimal 5 menit untuk memastikan:
# - Cron job benar-benar memanggil schedule:run setiap menit
# - Tidak ada error yang muncul
# - Incremental sync akan jalan otomatis setiap 30 menit
```

**Yang harus Anda lihat:**
- Setiap menit ada entry baru di log (jika menggunakan logging)
- Tidak ada error message
- Setelah 30 menit, incremental sync akan jalan otomatis

### 6. Pilihan: Run Full Sync Manual Pertama Kali

**Pertanyaan:** Apakah perlu run `app:sync-all` manual sekarang?

**Jawaban:** 
- ✅ **YA, jika Anda ingin data langsung terisi sekarang** (tidak perlu tunggu sampai besok jam 08:30)
- ❌ **TIDAK, jika Anda tidak terburu-buru** (akan jalan otomatis besok jam 08:30 WIB)

**Jika memilih YA:**

```bash
# Run full sync manual pertama kali
php artisan app:sync-all

# Proses ini akan memakan waktu cukup lama (tergantung jumlah data)
# Monitor progress dan pastikan tidak ada error fatal

# Setelah selesai, check log
tail -f storage/logs/sync-full.log
```

**Catatan Penting:**
- Full sync pertama kali akan menggunakan **bulk INSERT** (lebih cepat)
- Proses bisa memakan waktu 1-3 jam tergantung jumlah data dari 17 cabang
- Jangan tutup terminal/SSH selama proses berjalan (atau gunakan `screen`/`tmux`)

**Jika memilih TIDAK:**
- Full sync akan jalan otomatis **besok jam 08:30 WIB**
- Incremental sync akan jalan otomatis **setiap 30 menit** mulai dari sekarang
- Data akan terisi secara bertahap melalui incremental sync

### 7. Setup Screen/Tmux (Recommended untuk Long-Running Process)

Jika Anda memilih run full sync manual, gunakan `screen` atau `tmux` agar proses tetap berjalan meskipun SSH disconnect:

```bash
# Install screen (jika belum ada)
sudo apt install -y screen

# Start screen session
screen -S sync-all

# Run full sync
cd /var/www/html/web/marketing-dashboard
php artisan app:sync-all

# Detach dari screen: Tekan Ctrl+A kemudian D
# Reattach ke screen: screen -r sync-all
```

### 8. Final Verification Checklist

Sebelum meninggalkan server, pastikan semua checklist ini ✅:

- [ ] Cron job sudah terdaftar dan cron service running
- [ ] `php artisan schedule:run` berjalan tanpa error
- [ ] `php artisan schedule:list` menunjukkan scheduled tasks dengan benar
- [ ] Monitor log minimal 5 menit dan tidak ada error
- [ ] (Opsional) Full sync manual sudah dijalankan jika diperlukan
- [ ] Log files bisa diakses dan writable
- [ ] Disk space cukup (check dengan `df -h`)
- [ ] Timezone sudah benar (Asia/Jakarta)

### 9. Monitoring Setelah Meninggalkan Server

Setelah meninggalkan server, lakukan monitoring berkala:

#### Daily Check (Recommended)

```bash
# SSH ke server
ssh user@your-server-ip

# Check apakah cron masih running
sudo systemctl status cron

# Check scheduled tasks
cd /var/www/html/web/marketing-dashboard
php artisan schedule:list

# Check log untuk error
tail -n 100 storage/logs/laravel.log | grep -i error
tail -n 100 storage/logs/sync-full.log
tail -n 100 storage/logs/sync-incremental.log
```

#### Weekly Check

```bash
# Check disk space
df -h

# Check log file sizes
du -sh storage/logs/*.log

# Check sync batch status (jika ada)
php artisan app:sync-status
```

## Troubleshooting Jika Ada Masalah

### Cron Job Tidak Berjalan

```bash
# 1. Check cron service
sudo systemctl status cron
sudo systemctl start cron
sudo systemctl enable cron

# 2. Check cron log
sudo tail -f /var/log/syslog | grep CRON

# 3. Test manual
cd /var/www/html/web/marketing-dashboard
php artisan schedule:run
```

### Scheduler Tidak Dieksekusi

```bash
# 1. Check timezone
php artisan tinker
>>> config('app.timezone')
# Harus return: "Asia/Jakarta"

# 2. Check scheduled tasks
php artisan schedule:list

# 3. Test manual
php artisan schedule:run -v
```

### Full Sync Gagal

```bash
# 1. Check log untuk error detail
tail -f storage/logs/sync-full.log
tail -f storage/logs/laravel.log

# 2. Check koneksi database
php artisan db:show

# 3. Retry manual untuk tabel tertentu
php artisan app:sync-all --tables=MProduct --connection=pgsql_sby
```

## Kesimpulan

**Jawaban Singkat:**

1. **TIDAK perlu langsung run `php artisan app:sync-all`** - Akan jalan otomatis besok jam 08:30 WIB
2. **TAPI, WAJIB verifikasi dulu:**
   - Cron job berjalan ✅
   - Scheduler bisa dijalankan tanpa error ✅
   - Monitor minimal 5 menit untuk memastikan tidak ada error ✅
3. **Bisa tutup server SETELAH verifikasi selesai** - Cron akan tetap berjalan 24/7
4. **OPSIONAL: Run full sync manual sekarang** jika ingin data langsung terisi (tidak perlu tunggu besok)

**Timeline Otomatis:**
- **Sekarang**: Incremental sync akan jalan otomatis setiap 30 menit
- **Besok 08:30 WIB**: Full sync akan jalan otomatis
- **Setiap hari 08:30 WIB**: Full sync akan jalan otomatis
- **Setiap 30 menit**: Incremental sync akan jalan otomatis

---

**Last Updated**: November 2024

