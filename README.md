# Bank Provita

Aplikasi Bank Mini SMK Negeri 2 Semarang, pengganti workbook Excel `Aplikasi_Bank_Mini_SMKN_2_SEMARANG.xlsb`.

| Lapisan | Teknologi |
| --- | --- |
| Backend | Laravel 12, modular di `app/Http/Modules` |
| Frontend panel petugas | Inertia.js v3 + Vue 3 (`<script setup>`, JavaScript) |
| Komponen UI | PrimeVue 4 (tema Aura) + Tailwind CSS v4 |
| Database | MySQL 8 / MariaDB 10.4+ |
| Auth & hak akses | Laravel Sanctum + spatie/laravel-permission |
| Audit & backup | spatie/laravel-activitylog, spatie/laravel-backup |
| Testing & gaya kode | Pest, Laravel Pint, ESLint, Prettier |

## Kebutuhan sistem

- PHP 8.2+ dengan ekstensi `bcmath`, `intl`, `pdo_mysql`, `zip`
- Composer 2
- Node.js 20+ dan npm
- MySQL 8 atau MariaDB 10.4+ (mis. XAMPP), serta `mysqldump` untuk backup

## Setup lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Lalu edit `.env`:

- `DB_*`: koneksi database (buat dulu database `bank_provita` dengan utf8mb4).
- `SEED_BENDAHARA_PASSWORD` dan `SEED_TELLER_PASSWORD`: kata sandi akun awal (wajib, tidak ada default di kode).
- `DB_DUMP_BINARY_PATH`: folder berisi `mysqldump`, mis. `/Applications/XAMPP/xamppfiles/bin`.

```bash
php artisan migrate --seed
composer run dev          # server Laravel + queue + log + Vite
```

Buka http://localhost:8000, lalu login dengan username `bendahara` atau `teller` (atau sesuai `SEED_*_USERNAME`).

## Peran

| Peran | Akses |
| --- | --- |
| `bendahara` | Semua menu. Otomatis mendapat setiap permission baru. |
| `teller` | Setoran, penarikan, penutupan, buku tabungan, laporan harian kas (halamannya menyusul di issue modul); pengaturan hanya tanggal transaksi & uang modal. |
| `admin` | Kelola pengguna, jenis rekening, dan pengaturan umum/teknis. |

Peran & permission dideklarasikan di `config/permissions.php` dan `module.json` tiap modul, lalu disinkronkan dengan `php artisan permission:sync-modules`.

## Perintah penting

| Perintah | Fungsi |
| --- | --- |
| `composer run dev` | Server pengembangan (serve + queue + pail + vite) |
| `vendor/bin/pest` | Semua test (SQLite in-memory) |
| `TEST_MYSQL_DATABASE=bank_provita_test vendor/bin/pest --group=mysql` | Test row lock dua penarikan bersamaan di MySQL (database akan dikosongkan) |
| `vendor/bin/pint` | Merapikan kode PHP |
| `npm run lint` / `npm run format` | ESLint / Prettier untuk Vue & JS |
| `npm run build` | Build aset produksi |
| `php artisan make:module Nama --menu [--api]` | Membuat modul baru |
| `php artisan permission:sync-modules [--prune]` | Sinkronisasi role & permission |
| `php artisan saldo:rekonsiliasi [--fix]` | Cek/perbaiki saldo rekening dari saldo awal + mutasi |
| `php artisan backup:run --only-db` | Backup database manual |
| `php artisan schedule:list` | Daftar jadwal |

## Struktur folder

```
app/
├── Console/Commands/          # make:module, permission:sync-modules
├── Http/
│   ├── Controllers/           # auth, dashboard, profil (non-modul)
│   ├── Middleware/HandleInertiaRequests.php
│   └── Modules/               # satu folder per modul (lihat docs/modules.md)
│       ├── Pengaturan/        # modul contoh lengkap (key-value, setara sheet M)
│       ├── Pengguna/
│       ├── LogAktivitas/
│       ├── Nasabah/
│       ├── Rekening/              # jenis rekening, buka/tutup rekening, buku tabungan
│       ├── Transaksi/             # setor, tarik, koreksi (pembalik)
│       └── Pinjaman/  Laporan/     # kerangka, diisi di issue berikutnya
├── Jobs/                      # HitungBungaBulanan, CekJatuhTempoDeposito
├── Providers/ModuleServiceProvider.php
└── Support/
    ├── Money.php              # aritmetika uang (bcmath)
    ├── Finance/               # FinancialTransaction (lock), Immutable
    └── Modules/               # ModuleRegistry
resources/js/
├── Components/                # MoneyInput, MoneyText, DateText, PageHeader, ConfirmDelete
├── Layouts/                   # AppLayout, GuestLayout
├── Pages/                     # halaman non-modul
├── composables/usePermission.js
├── locale/id.js               # locale PrimeVue Bahasa Indonesia
└── utils/format.js            # rupiah(), tanggal(), terbilang()
stubs/module/                  # stub generator make:module
docs/modules.md                # konvensi modul
```

Cara membuat modul baru, aturan penamaan, dan aturan uang & transaksi ada di **[docs/modules.md](docs/modules.md)**.

## Deploy di VPS

1. `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
2. `php artisan migrate --force && php artisan permission:sync-modules`
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`

**Scheduler** (backup harian 01:30, retensi 14 hari, cek jatuh tempo, bunga bulanan), satu baris cron:

```cron
* * * * * cd /var/www/bank-provita && php artisan schedule:run >> /dev/null 2>&1
```

**Queue** (driver `database`) dengan Supervisor, `/etc/supervisor/conf.d/bank-provita-worker.conf`:

```ini
[program:bank-provita-worker]
command=php /var/www/bank-provita/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/bank-provita/storage/logs/worker.log
stopwaitsecs=3600
```

Setelah deploy ulang, jalankan `php artisan queue:restart`.

**Backup** disimpan di `storage/app/private/bank-provita`. Bila backup gagal, notifikasi dikirim ke `BACKUP_NOTIFY_EMAIL` (atur juga `MAIL_*`). Untuk enkripsi arsip, isi `BACKUP_ARCHIVE_PASSWORD`.
