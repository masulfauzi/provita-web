# Konvensi modul

Setiap domain bisnis Bank Provita adalah satu **modul** di `app/Http/Modules/{Modul}`. Satu modul berisi backend dan halaman Vue-nya sendiri, dan dimuat otomatis oleh `App\Providers\ModuleServiceProvider`. Tidak ada route, migration, atau menu yang perlu didaftarkan manual.

## Membuat modul baru

```bash
php artisan make:module Nasabah --menu            # CRUD + muncul di sidebar
php artisan make:module Nasabah --menu --api      # + route & controller api/v1
php artisan make:module Kelas --menu --group="Data Master"
php artisan migrate
```

Generator membaca stub di `stubs/module/` (dan `stubs/module-api/` untuk `--api`), lalu menjalankan `permission:sync-modules`. Hasilnya langsung bisa dibuka di `/nasabah`, muncul di sidebar bendahara, dan test bawaannya lulus. Setelah itu sesuaikan kolom di migration, model, request, form Vue, dan test.

## Struktur

```
app/Http/Modules/Nasabah/
├── Controllers/NasabahController.php     # tipis: otorisasi, panggil Service, render Inertia
├── Controllers/Api/NasabahController.php # (opsional) API untuk aplikasi nasabah
├── Requests/StoreNasabahRequest.php
├── Requests/UpdateNasabahRequest.php
├── Models/Nasabah.php
├── Services/NasabahService.php           # logika bisnis
├── Policies/NasabahPolicy.php            # ditemukan otomatis dari nama model
├── Console/*.php                         # (opsional) artisan command, didaftarkan otomatis
├── Database/Migrations/
├── Database/Seeders/NasabahSeeder.php    # dipanggil otomatis oleh DatabaseSeeder
├── Database/Factories/
├── Pages/Index.vue, Create.vue, ...      # Inertia::render('Nasabah::Index')
├── Components/                           # komponen Vue khusus modul
├── routes/web.php                        # middleware web + auth
├── routes/api.php                        # prefix api/v1, middleware auth:sanctum
├── Tests/Feature/
└── module.json
```

Namespace PHP mengikuti folder: `App\Http\Modules\Nasabah\Controllers\NasabahController`.

## module.json

```json
{
    "name": "Nasabah",
    "label": "Nasabah",
    "icon": "pi pi-users",
    "order": 100,
    "menu": [
        { "label": "Nasabah", "route": "nasabah.index", "permission": "nasabah.lihat", "group": "Data Master" }
    ],
    "permissions": {
        "nasabah.lihat": { "label": "Lihat nasabah", "roles": ["teller"] },
        "nasabah.tambah": { "label": "Tambah nasabah", "roles": [] }
    },
    "policies": {}
}
```

| Kunci | Arti |
| --- | --- |
| `order` | Urutan modul & menu di sidebar (kecil = atas). |
| `menu` | Item sidebar. Item hanya tampil bila route-nya ada dan user punya `permission`. `group` menjadi judul kelompok menu. |
| `permissions` | Permission modul (`modul.aksi`) beserta peran yang otomatis mendapatkannya. Peran `bendahara` selalu mendapat **semua** permission (`config/permissions.php` → `all_permissions_roles`), jadi tidak perlu ditulis. |
| `policies` | Pemetaan model di luar modul ke policy di modul, mis. `"App\\Models\\User": "App\\Http\\Modules\\Pengguna\\Policies\\PenggunaPolicy"`. Model di dalam modul tidak perlu didaftarkan. |

Setelah mengubah `permissions`, jalankan `php artisan permission:sync-modules` (tambahkan `--prune` untuk menghapus permission yang sudah tidak dideklarasikan).

## Penamaan

| Hal | Aturan | Contoh (`LogAktivitas`) |
| --- | --- | --- |
| Folder & class | StudlyCase, Bahasa Indonesia, tunggal | `LogAktivitas`, `LogAktivitasController` |
| URL & nama route | kebab-case | `/log-aktivitas`, `log-aktivitas.index` |
| Permission | `snake_case.aksi` | `log_aktivitas.lihat` |
| Tabel | snake_case tunggal | `log_aktivitas`, `rekening`, `transaksi` |
| Halaman Inertia | `Modul::Halaman` | `LogAktivitas::Index` |
| Import Vue antar file modul | alias `@modules` | `@modules/Pengguna/Components/Form.vue` |

Aksi permission yang umum: `lihat`, `tambah`, `ubah`, `hapus`, plus aksi domain seperti `transaksi.setor`, `pinjaman.akad`, `laporan.neraca`.

## Kapan memakai Service

- Controller hanya untuk otorisasi, validasi (lewat FormRequest), memanggil Service, lalu mengembalikan respons Inertia/redirect/JSON.
- Semua logika bisnis ada di `Services/`: perhitungan, aturan saldo, query yang dipakai ulang, dan apa pun yang juga dibutuhkan API atau job.
- Service boleh dipanggil dari controller web, controller API, command, dan job, jadi jangan bergantung pada `request()` di dalam Service.

## Dependensi antar modul

- Modul lain dipanggil **lewat Service-nya** (mis. `PengaturanService::tanggalTransaksi()`, `TransaksiService::setor()`), atau lewat event bila tidak perlu menunggu hasilnya.
- **Jangan** memanggil controller modul lain, dan jangan menulis langsung ke tabel milik modul lain.
- Relasi Eloquent ke model modul lain boleh dipakai untuk membaca (mis. `Rekening::transaksi()`).
- Kode inti di luar `app/Http/Modules` sebaiknya tidak bergantung pada modul. Kalau terpaksa (mis. `HandleInertiaRequests` membaca tanggal transaksi), periksa dengan `class_exists()` supaya aplikasi tetap jalan bila modul dihapus.

## Aturan uang & transaksi

- Kolom nominal `DECIMAL(15,2)` dan cast `decimal:2`; nilai di PHP berupa string.
- Hitung lewat `App\Support\Money` (bcmath). **Jangan** memakai float, termasuk di JavaScript: `MoneyInput` mengirim angka ke server, tetapi perhitungan selalu di server.
- Perubahan saldo hanya lewat `TransaksiService`, yang memakai `App\Support\Finance\FinancialTransaction::lock()` (`DB::transaction` + `lockForUpdate`).
- Model transaksi keuangan memakai trait `App\Support\Finance\Immutable`: tidak bisa di-update atau di-delete. Koreksi dilakukan dengan transaksi pembalik (`TransaksiService::balik()`).
- Kolom `rekening.saldo` hanyalah cache. Sumber kebenarannya adalah `saldo_awal + Σ mutasi`, yang bisa dicek dengan `php artisan saldo:rekonsiliasi [--fix]`.
- Model data master & transaksi memakai `LogsActivity` (spatie/laravel-activitylog) supaya setiap perubahan tercatat: siapa, kapan, nilai lama, dan nilai baru.

## Primary key

Semua tabel domain dan `users` memakai **UUID** (`HasUuids`, `$table->uuid('id')->primary()`), supaya ID aman ditampilkan di API aplikasi nasabah. Tabel internal paket (roles, permissions, activity_log, jobs) tetap memakai auto-increment.

## Test

Test modul ada di `app/Http/Modules/{Modul}/Tests` dan otomatis memakai `Tests\TestCase` + `RefreshDatabase` (lihat `tests/Pest.php`). Helper yang tersedia:

- `userWithPermissions('nasabah.lihat', ...)`: user dengan permission tertentu.
- `userWithRole('teller')`: user dengan peran, setelah permission disinkronkan.

```bash
vendor/bin/pest app/Http/Modules/Nasabah
```

## Menghapus modul

Hapus foldernya, lalu jalankan `php artisan permission:sync-modules --prune`. Route, menu, halaman, command, dan migration modul tersebut ikut hilang. Bila tabelnya juga perlu dihapus, rollback migration-nya **sebelum** foldernya dihapus.
