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
- `SISWA_API_URL` dan `SISWA_API_CARI_URL`: API data siswa sekolah (cek NISN saat tambah nasabah, dan pencarian nama di menu **Daftar siswa**); default sudah mengarah ke apps.smkn2semarang.sch.id.

```bash
php artisan migrate --seed
composer run dev          # server Laravel + queue + log + Vite
```

**Seeder wajib dijalankan**: migration hanya berisi struktur tabel, sedangkan data awal dibuat seeder (aman dijalankan ulang). `AkuntansiSeeder` membuat bagan akun bawaan (akun Kas, Simpanan, Pendapatan administrasi, dll. dipakai jurnal otomatis), `PengaturanSeeder` membuat pengaturan, dan `RekeningSeeder` membuat jenis rekening: **Tabungan Pelajar** (rekening utama, nomor = NISN/NIS), **Tabungan Wisata** (prefix 41), **Tabungan Wisuda** (prefix 42), dan **Tabungan Foto Katalog** (prefix 43). Untuk database yang sudah berjalan: `php artisan db:seed --class="App\Http\Modules\Rekening\Database\Seeders\RekeningSeeder" --force`.

Buka http://localhost:8000, lalu login dengan username `bendahara` atau `teller` (atau sesuai `SEED_*_USERNAME`).

## Peran

| Peran | Akses |
| --- | --- |
| `bendahara` | Semua menu. Otomatis mendapat setiap permission baru. |
| `teller` | Buka/tutup sesi kas, setoran, penarikan, penutupan, buku tabungan, penerimaan & pengeluaran kas, laporan kas harian, melihat & mengunduh laporan keuangan; pengaturan hanya tanggal transaksi. |
| `admin` | Kelola pengguna, jenis rekening, pengaturan umum/teknis, dan mencari data siswa (Daftar siswa). |

Peran & permission dideklarasikan di `config/permissions.php` dan `module.json` tiap modul, lalu disinkronkan dengan `php artisan permission:sync-modules`.

### Komputer terdaftar (teller)

Akun **teller** hanya bisa login dan bertransaksi dari komputer yang didaftarkan bendahara; bendahara dan admin bebas login dari mana saja. Cara mendaftarkan: bendahara login **di komputer teller**, buka **Sistem → Perangkat**, isi nama (mis. "PC Teller 1"), lalu klik **Daftarkan komputer ini**.

- Browser komputer tersebut menyimpan token acak di cookie `provita_perangkat` (terenkripsi, httpOnly, ±5 tahun); database hanya menyimpan hash SHA-256-nya.
- Diperiksa saat login **dan setiap request**: bila perangkat dinonaktifkan/dihapus atau cookie hilang, teller langsung dikeluarkan. Percobaan dari komputer tak terdaftar tercatat di Log Aktivitas (log `perangkat`).
- Menghapus data browser, mengganti browser, atau mode penyamaran (incognito) = harus didaftarkan ulang.
- Pembatasan bisa dimatikan untuk pengembangan dengan `PERANGKAT_WAJIB=false` (test memakai nilai ini; test modul Perangkat menyalakannya sendiri). **Jangan matikan di production.**

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
| `php artisan akuntansi:jurnal-transaksi` | Membuat jurnal untuk transaksi tabungan yang belum dijurnal (aman diulang) |
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
│       ├── Akuntansi/             # bagan akun, jurnal umum, jurnal otomatis dari transaksi
│       ├── Kas/                   # penerimaan & pengeluaran kas lain-lain, bukti kas
│       ├── Laporan/               # neraca, laba rugi, arus kas, neraca saldo, buku besar (+ Excel & cetak)
│       └── Pinjaman/              # pinjaman guru/karyawan, jadwal angsuran
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

Cara membuat modul baru, aturan penamaan, dan aturan uang & transaksi ada di **[docs/modules.md](docs/modules.md)**. Skenario uji manual (QA) untuk tester ada di **[docs/QA.md](docs/QA.md)**.

## Pembukuan & laporan

Setiap transaksi tabungan otomatis dijurnal (double-entry) di DB transaction yang sama:

| Transaksi | Debit | Kredit |
| --- | --- | --- |
| Setoran | Kas | Simpanan tabungan nasabah |
| Penarikan, penutupan | Simpanan tabungan nasabah | Kas |
| Biaya administrasi | Simpanan tabungan nasabah | Pendapatan administrasi |
| Koreksi | kebalikan jurnal transaksi asal | |

Penerimaan & pengeluaran kas tidak punya tabel sendiri: dicatat di tabel `transaksi` tanpa rekening (jenis `kas_masuk`/`kas_keluar`) dan memakai kas yang sama dengan uang tabungan nasabah, tanpa mengubah saldo rekening. Teller/bendahara mencatatnya lewat menu **Penerimaan kas** (mis. penjualan buku tabungan, harga default di Pengaturan `harga_buku_tabungan`; D Kas / K akun pendapatan) dan **Pengeluaran kas** (mis. belanja ATK; D akun beban / K Kas, ditolak bila saldo kas kurang). Kategori diambil dari akun Pendapatan/Beban di Bagan akun; pembatalan oleh bendahara membuat transaksi pembalik beserta jurnalnya. Transaksi lain (setoran modal, pemindahan kas ke bank) dicatat bendahara lewat **Jurnal umum**. Laporan di `/laporan` dihitung dari jurnal. Modal bank dicatat dengan jurnal umum (D Kas / K Modal).

**Sesi kas harian** (menu Kas → Sesi kas, tabel `sesi_kas`): satu sesi per tanggal transaksi, dipakai bersama semua petugas. Teller (atau bendahara) membuka sesi dengan **modal awal** (uang tunai di laci, bukan modal pembukuan; tidak dijurnal) dan **teller piket** hari itu: satu atau lebih siswa yang dipilih dari nasabah siswa aktif (tabel `sesi_kas_petugas`). Jam input modal dicatat sebagai jam hadir (presensi) siswa piket dan tampil di halaman Sesi kas, riwayat, serta laporan kas harian. Selama sesi tanggal transaksi belum dibuka atau sudah ditutup, setoran, penarikan, buka/tutup rekening, koreksi, serta penerimaan/pengeluaran kas dan pembatalannya ditolak untuk semua petugas, termasuk bendahara (jurnal umum tidak terpengaruh). Bila uang laci kurang di tengah hari (mis. penarikan melebihi kas), teller atau bendahara mencatat **Tambahan modal** (nominal, keterangan, jam, petugas; tabel `sesi_kas_tambahan`, tidak dijurnal) selama sesi terbuka; modal awal tidak diubah dan tambahan yang salah catat bisa dihapus sebelum sesi ditutup. Saat tutup, teller memasukkan uang fisik; sistem menyimpan saldo seharusnya (modal awal + tambahan modal + pemasukan − pengeluaran) dan selisihnya, yang wajib diberi keterangan bila tidak nol. Sesi baru hanya bisa dibuka bila sesi sebelumnya sudah ditutup. **Laporan kas harian** (`/laporan/harian`, permission `laporan.harian`) merinci modal awal, pemasukan (setoran, penerimaan kas), pengeluaran (penarikan, penutupan, pengeluaran kas), koreksi/pembatalan, saldo akhir, uang fisik & selisih, serta daftar transaksi; bisa dicetak dan diunduh Excel. Biaya administrasi tidak dihitung karena tidak ada uang fisik yang berpindah.

**Jenis nasabah**: siswa, guru, karyawan, dan lembaga (konstanta `Nasabah::JENIS`). Nomor rekening = prefix jenis rekening + NISN/NIS (siswa), NIP (guru/karyawan), atau angka nomor nasabah untuk **lembaga** (mis. NSB-000123 → `000123`); lembaga tidak memakai jenis kelamin, NIS/NIK, maupun data lahir.

**Cetak buku tabungan** (tombol **Cetak buku** di halaman rekening, permission `rekening.cetak`): mencetak baris `No | Tanggal | Sandi | Debit | Kredit | Saldo | Paraf` langsung ke halaman buku tabungan fisik. Data dipilih dari mutasi yang belum dicetak, rentang nomor, atau rentang tanggal, lalu bisa dicentang manual; posisi mulai (halaman, baris) default melanjutkan cetakan terakhir dan otomatis pindah halaman. Sandi: 01 setoran, 02 penarikan, 03 koreksi, 04 biaya administrasi, 05 tutup rekening, 06 autodebet keluar, 07 autodebet masuk; paraf = inisial petugas. Setelah dicetak, petugas mengonfirmasi agar baris ditandai (tabel `cetak_buku_tabungan`; cetak ulang tercatat sebagai riwayat). Posisi cetak (baris per halaman, jarak atas/kiri, tinggi baris, lebar kolom dalam mm) diatur di Pengaturan → Umum.

**Autodebet** (menu Tabungan → Autodebet, permission `autodebet.lihat`/`autodebet.kelola`): pindah buku terjadwal dari Tabungan Pelajar (rekening utama) ke rekening lain milik nasabah yang sama (Wisata, Wisuda, Foto Katalog, dll.). Nominal ditentukan nasabah lewat teller; periode mingguan (hari Senin–Minggu) atau bulanan (tanggal 1–28); target opsional. Autodebet diproses saat **sesi kas dibuka** untuk semua jadwal yang jatuh tempo s.d. tanggal transaksi (termasuk yang terlewat). Saldo kurang dicatat gagal lalu lanjut ke jadwal berikutnya; bila target tercapai, debet terakhir sebesar sisa target lalu status menjadi selesai. Transaksinya berjenis `pindah_keluar`/`pindah_masuk` (tidak memengaruhi kas fisik maupun laporan kas harian, jurnal D/K Simpanan) dan tidak bisa dikoreksi per baris; hentikan atau ubah autodebetnya.

**Pinjaman** (menu Keuangan → Pinjaman, permission `pinjaman.lihat`/`pinjaman.akad`/`pinjaman.angsuran`/`pinjaman.batal`, teller & bendahara): pinjaman tunai untuk nasabah **guru/karyawan**, satu pinjaman berjalan per orang, tanpa denda. Saat akad, petugas mengisi pokok dan jangka waktu (bulan) lalu jadwal angsuran langsung dibuat: pokok per bulan = pokok ÷ tenor dibulatkan ke bawah (sisa pembagian di angsuran terakhir), jasa flat = pokok × persen jasa (dibulatkan ke rupiah), jatuh tempo = tanggal akad + n bulan. Plafon (`plafon_pinjaman`, 0 = tanpa batas), persen jasa per bulan (`jasa_pinjaman_persen`), dan tenor maksimal (`tenor_maksimal_pinjaman`) diatur di Pengaturan → Keuangan; persen jasa dibekukan per akad. Pencairan dan angsuran adalah uang fisik di tabel `transaksi` tanpa rekening (jenis `pinjaman_cair` nomor `PJ-`, `angsuran` nomor `AP-`), sehingga wajib sesi kas terbuka, masuk laporan kas harian, dan ditolak bila saldo kas kurang. Jurnal: pencairan D 1-201 Piutang pinjaman / K Kas; angsuran D Kas / K Piutang pinjaman (pokok) / K 4-102 Pendapatan jasa pinjaman (jasa). Angsuran dibayar berurutan (boleh beberapa sekaligus). Salah input diperbaiki dengan **batalkan pembayaran terakhir** atau **batalkan pinjaman** (hanya bila belum ada angsuran dibayar); keduanya membuat transaksi pembalik dan jurnal balik.

**Impor data sistem lama** (Data Master → Nasabah → **Impor dari Excel**, permission `nasabah.impor`, khusus bendahara; dipakai sekali saat migrasi): file .xlsx berkolom `kelas, nipd, nisn, nama_nasabah, saldo, jenis_nasabah, jenis_rekening`, satu baris = satu rekening. Siswa dikelompokkan per NISN (nama, kelas, dan NIS diambil dari baris Tabungan Pelajar); guru/karyawan/lembaga satu baris satu nasabah; jenis kelamin dikosongkan. Nomor rekening = `nipd` (nomor lama), atau prefix jenis + NISN bila kosong. **Periksa** menampilkan ringkasan, error, dan peringatan per nomor baris tanpa menyimpan; **Impor sekarang** hanya aktif bila tidak ada error dan berjalan dalam satu DB transaction. Saldo masuk sebagai `saldo_awal` rekening (tanpa baris transaksi, tidak muncul di sesi kas/buku tabungan), lalu satu jurnal D Kas / K Simpanan tabungan nasabah per tanggal saldo awal (default 30-10-2026). NISN atau nomor rekening yang sudah ada ditolak, sehingga impor tidak bisa terjadi dua kali. File asli `docs/data_nasabah.xlsx` berisi data pribadi dan diabaikan git.

## Deploy di VPS

1. `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
2. `php artisan migrate --force && php artisan permission:sync-modules --prune`
   - Bila ada pengaturan atau akun bawaan baru (mis. fitur Pinjaman), jalankan juga seeder-nya: `php artisan db:seed --class="App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder" --force` (hanya menambah kunci baru, nilai lama tidak ditimpa) dan `php artisan db:seed --class="App\Http\Modules\Akuntansi\Database\Seeders\AkuntansiSeeder" --force` (mengembalikan nama, kategori, peran, dan status aktif akun bawaan sesuai kode; akun tambahan buatan bendahara tidak tersentuh)
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`

Migration masih tahap pengembangan: **satu file per tabel**, hanya struktur akhir (tanpa pengubahan data); data awal ada di `{Modul}Seeder`. Bila file migration disederhanakan ulang, database lama dibuat ulang dengan `php artisan migrate:fresh --seed --force` (semua data terhapus).

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
