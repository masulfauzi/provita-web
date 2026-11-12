# API Internet Banking Nasabah

API **hanya baca** untuk frontend `provita-ib` (Vue 3) di `ib.<domain>`. Nasabah **mengaktivasi akun sendiri** (NISN/No. nasabah + nomor rekening, lalu mengisi data pribadi, No. HP, dan password), kemudian **login dengan No. HP** untuk melihat saldo, mutasi, dan mengunduh rekening koran PDF.

> **Frontend, mulai dari [Perubahan untuk frontend](#perubahan-untuk-frontend-v2) dan [Panduan layar](#panduan-layar-frontend).**

- **Swagger / OpenAPI:** halaman publik **`/docs/api`** (Swagger UI, bisa *Try it out*), spesifikasi di [`docs/openapi/nasabah.yaml`](openapi/nasabah.yaml) (juga di `/docs/api/openapi.yaml`). Test `tests/Feature/OpenApiTest.php` menjaga spesifikasi tetap sinkron dengan route & respons.
- **Base URL:** `https://api.<domain>/api/v1`
- **Autentikasi:** Laravel Sanctum mode SPA (cookie sesi + CSRF), guard `nasabah`, terpisah dari login petugas.
- **Nominal:** selalu string desimal 2 digit (`"1250000.00"`), tidak pernah float.
- **ID:** UUID.
- **Waktu:** ISO 8601 dengan zona `+07:00`.

## Konfigurasi `.env`

| Variabel | Contoh produksi | Lokal | Keterangan |
| --- | --- | --- | --- |
| `IB_URL` | `https://ib.bankprovita.sch.id` | `http://localhost:5173` | Origin frontend yang diizinkan CORS |
| `SANCTUM_STATEFUL_DOMAINS` | `ib.bankprovita.sch.id` | `localhost:5173` | Origin yang mendapat sesi cookie |
| `IB_SESSION_DOMAIN` | `.bankprovita.sch.id` | (kosong) | Domain induk agar cookie `XSRF-TOKEN` terbaca dari `ib.` |
| `IB_SESSION_COOKIE` | `provita_ib_session` | sama | Nama cookie sesi nasabah, berbeda dengan sesi panel |
| `IB_SESSION_LIFETIME` | `15` | `15` | Menit; frontend auto-logout setelah 10 menit diam |
| `SESSION_SECURE_COOKIE` | `true` | `false` | Cookie hanya lewat HTTPS |

Agar *Try it out* di `/docs/api` bisa aktivasi/login, host API juga dicantumkan di `SANCTUM_STATEFUL_DOMAINS` (mis. `ib.<domain>,api.<domain>`). Permintaan dari origin yang tidak terdaftar tidak mendapat sesi: aktivasi & login dijawab `419`, endpoint lain `401`.

Sesi nasabah memakai cookie sendiri (`GunakanSesiNasabah`), sehingga:
- logout Internet Banking tidak mengeluarkan petugas dari panel;
- sesi panel tetap 120 menit.

Cookie bersifat `HttpOnly` dan `SameSite=Lax`.

**Frontend `provita-ib`:**

```
VITE_API_URL=https://api.<domain>/api/v1   # lokal: http://localhost:8000/api/v1
VITE_USE_MOCK=false
```

> Frontend memanggil semua path relatif terhadap `VITE_API_URL`, termasuk `/sanctum/csrf-cookie`. Karena itu `VITE_API_URL` **harus berakhiran `/api/v1`**. CSRF cookie tersedia di root (`/sanctum/csrf-cookie`) maupun di `/api/v1/sanctum/csrf-cookie`.

**Header dari frontend:**
- `Accept: application/json`
- `X-Requested-With: XMLHttpRequest`
- `X-XSRF-TOKEN` (otomatis dari cookie `XSRF-TOKEN`; axios `withCredentials` + `withXSRFToken`)

## Perubahan untuk frontend (v2)

Versi ini **mengganti** alur login lama. Yang perlu diubah di `provita-ib`:

| Lama | Baru |
| --- | --- |
| Akun dibuat teller, password awal di slip | Nasabah **aktivasi sendiri**: `POST /nasabah/aktivasi/cek` → `POST /nasabah/aktivasi` |
| `POST /nasabah/login` `{ nis, password }` | `POST /nasabah/login` **`{ no_hp, password }`**; error ada di `errors.no_hp` |
| Onboarding: 403 `WAJIB_GANTI_PASSWORD` / `WAJIB_LENGKAPI_DATA` | **Dihapus.** Setelah login semua endpoint langsung bisa dipakai |
| `me.wajib_ganti_password`, `me.profil_lengkap` | **Dihapus.** `me.no_hp` ditambahkan |
| `PUT /nasabah/profil` menerima `no_hp` & wajib `persetujuan` | `no_hp` **diabaikan**, `persetujuan` tidak dikirim lagi |
| — | **Baru:** `PUT /nasabah/no-hp` untuk ganti No. HP login |
| Halaman "Ganti password wajib" & "Lengkapi data wajib" | Diganti halaman **Aktivasi** (2 langkah). Ganti password tetap ada di menu profil |

## Panduan layar frontend

```
Login ──"Belum punya akun? Aktivasi"──▶ Aktivasi 1 ──▶ Aktivasi 2 ──▶ Login (No. HP terisi) ──▶ Beranda
  ▲                                     (cek)          (simpan)
  └──────────── 409 SUDAH_AKTIVASI ──────┘
```

Sebelum memanggil endpoint `POST` pertama, panggil `GET /sanctum/csrf-cookie`. Bila menerima `419`, panggil ulang lalu ulangi permintaan.

### 1. Halaman Login
- Field: **No. HP** (`inputmode="tel"`, placeholder `08xxxxxxxxxx`) dan **Password**.
- Kirim `POST /nasabah/login` `{ no_hp, password }`. No. HP boleh `08…`, `62…`, atau `+62 …`.
- `200` → simpan `data` (Me), ke beranda.
- `422` → tampilkan `errors.no_hp[0]` ("No. HP atau password salah.") dan `sisa_percobaan`.
- `429` → hitung mundur `retry_after` detik, tombol nonaktif.
- Tautan **"Belum punya akun? Aktivasi di sini"** ke halaman Aktivasi, dan teks kecil **"Lupa password atau ganti nomor? Hubungi teller Bank Mini."**

### 2. Aktivasi langkah 1 — Verifikasi
- Field: **NISN / No. Nasabah** dan **Nomor Rekening**. Bantuan: *"Siswa: NISN. Guru/karyawan/lembaga: No. nasabah dari teller (mis. NSB-000123)."*
- `POST /nasabah/aktivasi/cek` `{ identitas, no_rekening }`.
- `200` → tampilkan konfirmasi **"Apakah ini Anda? {nama} – {kelas}"**, simpan `wajib_wali` & `berlaku_sampai`, lanjut ke langkah 2.
- `409 SUDAH_AKTIVASI` → tampilkan `message`, tombol ke halaman Login.
- `422` → `errors.identitas[0]` + `sisa_percobaan`. `429` → hitung mundur.

### 3. Aktivasi langkah 2 — Data pribadi, No. HP, password
- **Kotak info di atas form:** *"No. HP yang Anda isi akan menjadi **ID login** Internet Banking. Pastikan nomor aktif dan milik Anda sendiri."*
- Tampilkan sisa waktu dari `berlaku_sampai` (15 menit).
- Field data pribadi = tabel [Profil](#profil); data wali **wajib bila `wajib_wali = true`**.
- Field tambahan: `no_hp`, `no_hp_confirmation` (ulangi), `password`, `password_confirmation`, checkbox `persetujuan`.
- Tampilkan aturan password: min. 8 karakter, huruf dan angka, bukan NISN/NIS/No. HP/nomor rekening.
- `POST /nasabah/aktivasi`.
- `201` → tampilkan `message`, arahkan ke Login dengan `no_hp = data.no_hp` terisi.
- `422` → tampilkan error per field (`errors.<field>[0]`).
- `403 VERIFIKASI_DIPERLUKAN` → sesi aktivasi habis; kembali ke langkah 1 dengan pesan `message`.
- `409 SUDAH_AKTIVASI` → ke Login.

### 4. Menu profil
- **Data pribadi:** `GET`/`PUT /nasabah/profil`. No. HP tampil **hanya baca** dengan tombol "Ganti No. HP".
- **Ganti No. HP:** form `password` (saat ini), `no_hp`, `no_hp_confirmation` → `PUT /nasabah/no-hp`. `200` → perbarui `me.no_hp`, tampilkan `message`.
- **Ganti password:** `PUT /nasabah/password` (tidak wajib lagi).

## Endpoint

| # | Method & path | Kegunaan | Akses |
| --- | --- | --- | --- |
| 1 | `GET /sanctum/csrf-cookie` | Ambil cookie CSRF | publik |
| 2 | `POST /nasabah/aktivasi/cek` | Aktivasi 1: NISN/No. nasabah + rekening | publik |
| 3 | `POST /nasabah/aktivasi` | Aktivasi 2: data pribadi + No. HP + password | publik, setelah #2 |
| 4 | `POST /nasabah/login` | Login No. HP + password | publik |
| 5 | `POST /nasabah/logout` | Keluar (`204`) | login |
| 6 | `GET /nasabah/me` | Data nasabah yang login | login |
| 7 | `PUT /nasabah/password` | Ganti password | login |
| 8 | `PUT /nasabah/no-hp` | Ganti No. HP login | login |
| 9 | `GET /nasabah/profil` | Data pribadi + 10 riwayat login | login |
| 10 | `PUT /nasabah/profil` | Ubah data pribadi | login |
| 11 | `GET /nasabah/rekening` | Daftar rekening + saldo | login |
| 12 | `GET /nasabah/rekening/{id}` | Detail rekening | login |
| 13 | `GET /nasabah/rekening/{id}/mutasi` | Mutasi + ringkasan periode | login |
| 14 | `GET /nasabah/rekening/{id}/mutasi.pdf` | PDF rekening koran | login |

### Aktivasi

**Langkah 1** — `POST /nasabah/aktivasi/cek`

```json
{ "identitas": "0090025039", "no_rekening": "25039" }
```

- `identitas`: **NISN** untuk siswa; **No. nasabah** untuk guru/karyawan/lembaga (`NSB-000123`, awalan `NSB-` boleh tidak ditulis, huruf besar/kecil bebas).
- `no_rekening`: salah satu rekening milik nasabah yang belum ditutup.
- **Berhasil `200`:**

```json
{ "data": { "nama": "Ayu Lestari", "kelas": "X AKL 1", "jenis_nasabah": "siswa", "wajib_wali": true,
            "berlaku_sampai": "2026-11-12T09:15:00+07:00" } }
```

- **Gagal `422`** (pesan selalu sama untuk identitas salah, rekening milik orang lain/ditutup, atau nasabah nonaktif):

```json
{ "message": "NISN/No. nasabah atau nomor rekening tidak cocok.",
  "errors": { "identitas": ["NISN/No. nasabah atau nomor rekening tidak cocok."] }, "sisa_percobaan": 4 }
```

- **`409`:** `{ "message": "Akun sudah diaktivasi. …", "kode": "SUDAH_AKTIVASI" }`.
- **Batas:** 5 gagal per menit per identitas + IP → `429` + `Retry-After`.

**Langkah 2** — `POST /nasabah/aktivasi` (dalam sesi/cookie yang sama, maks. 15 menit setelah langkah 1)

```json
{
  "tempat_lahir": "Semarang", "tanggal_lahir": "2008-03-14", "jenis_kelamin": "P",
  "alamat_jalan": "Jl. Sidodadi Barat No. 8", "rt": "003", "rw": "005", "kelurahan": "Karangkidul",
  "kecamatan": "Semarang Tengah", "kota": "Kota Semarang", "kode_pos": "50136", "email": null,
  "wali_nama": "Slamet Riyadi", "wali_hubungan": "ayah", "wali_hp": "081298765432",
  "no_hp": "081311112222", "no_hp_confirmation": "081311112222",
  "password": "Ayu2026ok", "password_confirmation": "Ayu2026ok", "persetujuan": true
}
```

- Field data pribadi: lihat tabel [Profil](#profil).
- `no_hp`: dinormalisasi ke `08…`, harus `08` + 8–11 digit, **unik** ("No. HP sudah dipakai akun lain.").
- `no_hp_confirmation`: harus sama ("Konfirmasi No. HP tidak sama.").
- `password`: aturan sama dengan [Ganti password](#ganti-password) (tanpa password lama).
- `persetujuan`: wajib `true`.
- **Berhasil `201`:** `{ "message": "Aktivasi berhasil. Silakan masuk menggunakan No. HP dan password Anda.", "data": { "no_hp": "081311112222" } }`. **Tidak otomatis login.**
- **`403`:** `{ "message": "Sesi aktivasi tidak ditemukan atau sudah kedaluwarsa. …", "kode": "VERIFIKASI_DIPERLUKAN" }` → ulangi langkah 1.
- **`409 SUDAH_AKTIVASI`**, **`422`** validasi, **`429`** (10 per menit).

### Login

`POST /nasabah/login` dengan `{ "no_hp": "081234567890", "password": "Provita2026" }`.

- `no_hp` boleh `08…`, `62…`, atau `+62 …`. NIS/NISN/nomor rekening **tidak** diterima.
- **Berhasil:** `200 { "data": <Me> }`.
- **Gagal:** No. HP tidak terdaftar, password salah, atau akun/nasabah nonaktif. Pesannya selalu sama:

```json
{ "message": "No. HP atau password salah.", "errors": { "no_hp": ["No. HP atau password salah."] }, "sisa_percobaan": 3 }
```

- **Batas percobaan:** 5 gagal per menit per No. HP + IP. Setelah itu `429` dengan header `Retry-After: <detik>` dan body `{ "message": "...", "retry_after": 45 }`.

### Me

```json
{ "data": {
  "id": "uuid-nasabah", "nis": "25041", "nama": "Rizky Ramadhan", "nama_panggilan": "Rizky",
  "kelas": "XII TJKT 3", "jenis_nasabah": "siswa", "nomor_rekening": ["25041"],
  "no_hp": "081234567890", "login_terakhir": "2026-11-09T11:41:00+07:00"
} }
```

- `nis`: berisi nomor nasabah untuk guru/lembaga yang tidak punya NIS.
- `no_hp`: No. HP login.
- `login_terakhir`: login berhasil **sebelum** sesi ini, atau `null`.

### Ganti password

`PUT /nasabah/password` dengan `{ password_lama, password, password_confirmation }`. Aturan (`422` per field):

- **`password_lama`:** harus cocok, selain itu "Password saat ini salah.".
- **`password`:**
  - minimal 8 karakter;
  - mengandung huruf dan angka;
  - tidak sama dengan password lama;
  - tidak sama dengan NIS, NISN, nomor nasabah, nomor rekening, maupun No. HP ("Password tidak boleh sama dengan NIS/NISN, No. HP, atau nomor rekening.").
- **`password_confirmation`:** harus sama, selain itu "Konfirmasi password tidak sama.".

Berhasil: `200 { "message": "Password berhasil diganti." }`. **Sesi di perangkat lain dikeluarkan** (`401`), sedangkan sesi ini tetap. Batas 10 permintaan per menit.

### Ganti No. HP

`PUT /nasabah/no-hp` dengan `{ password, no_hp, no_hp_confirmation }`.

- `password`: password saat ini ("Password saat ini salah.").
- `no_hp`: format sama dengan aktivasi, unik, dan berbeda dari nomor sekarang ("No. HP baru sama dengan No. HP sekarang.").
- Berhasil: `200 { "message": "No. HP berhasil diganti. Gunakan No. HP baru untuk masuk berikutnya.", "data": { "no_hp": "085700001111" } }`. Sesi tetap berjalan; No. HP di data pribadi ikut berubah. Batas 10 per menit.

### Profil

`GET` mengembalikan field di bawah ditambah `no_hp` (No. HP login, hanya baca) dan `riwayat_login` (`[{ waktu, perangkat, ip }]`, maksimal 10).

`PUT` hanya menerima field berikut; `nama`, `nis`, `kelas`, `jenis`, dan `no_hp` diabaikan bila dikirim. Tabel yang sama berlaku untuk data pribadi di aktivasi langkah 2.

| Field | Aturan |
| --- | --- |
| `tempat_lahir`, `alamat_jalan`, `kelurahan`, `kecamatan`, `kota` | wajib, maks. 100–255 karakter |
| `tanggal_lahir` | wajib, `Y-m-d`, sebelum hari ini |
| `jenis_kelamin` | wajib, `L` / `P` |
| `rt`, `rw` | wajib, 1–3 digit |
| `kode_pos` | wajib, 5 digit |
| `email` | opsional, email |
| `wali_nama`, `wali_hubungan` (`ayah`/`ibu`/`wali`), `wali_hp` | wajib untuk siswa, opsional untuk lainnya |

Setelah berhasil:
- kolom `alamat` lama di tabel nasabah diisi gabungan alamat terstruktur;
- `nama_orang_tua` diisi dengan `wali_nama`.

### Rekening

```json
{ "data": [{
  "id": "uuid", "no_rekening": "25041", "produk": "reguler", "nama_produk": "Tabungan Pelajar",
  "saldo": "455286.00", "status": "aktif", "tanggal_buka": "2026-03-11", "suku_bunga": null,
  "jatuh_tempo": null, "jangka_bulan": null, "setoran_rutin": null
}], "meta": { "saldo_per": "2026-11-11T18:41:00+07:00" } }
```

- **`produk`:** dipetakan dari kode jenis rekening di `config/ib.php` (`produk`). Saat ini semua jenis berproduk `reguler`.
- **`status`:** `aktif` atau `ditutup`. `jatuh_tempo` belum dipakai karena belum ada tabungan berjangka.
- **`suku_bunga`:** dari pengaturan *suku bunga tabungan per tahun* bila > 0, selain itu `null`.
- **Kepemilikan:** rekening yang tidak ada **atau** milik nasabah lain → `404` (bukan 403).

### Mutasi

Query:
- `dari`, `sampai` (`Y-m-d`; default 3 bulan terakhir s.d. hari ini);
- `jenis` (`masuk`/`keluar`);
- `page`;
- `per_page` (default 25, maks. 100).

Validasi tanggal (`422` di `dari`):
- "Rentang tanggal maksimal 3 bulan.";
- "Tanggal awal harus sebelum tanggal akhir.";
- "Format tanggal tidak valid.".

```json
{
  "data": [{
    "id": "uuid", "no_referensi": "0F3A9C21", "waktu": "2026-11-11T12:56:00+07:00",
    "jenis": "masuk", "kategori": "setoran_tunai", "keterangan": "Setoran tunai",
    "nominal": "200000.00", "saldo_setelah": "2562770.00", "petugas": "Dewi Lestari", "loket": null
  }],
  "meta": { "current_page": 1, "per_page": 25, "total": 23, "last_page": 1, "dari": "2026-08-11", "sampai": "2026-11-11" },
  "ringkasan": { "saldo_awal": "1842085.00", "total_masuk": "1396685.00", "total_keluar": "676000.00",
                 "saldo_akhir": "2562770.00", "jumlah_masuk": 12, "jumlah_keluar": 11 }
}
```

- Urutan terbaru dulu. Transaksi kas (tanpa rekening) tidak pernah ikut.
- **`kategori`:** dari jenis transaksi (`config/ib.php` → `kategori`):

  | Jenis transaksi | Kategori |
  | --- | --- |
  | setor | `setoran_tunai` |
  | tarik | `penarikan_tunai` |
  | biaya | `biaya_admin` |
  | autodebet | `pindah_buku` |
  | penutupan | `penutupan` |
  | koreksi | `koreksi` |
- **`petugas`:** `"Sistem"` untuk transaksi tanpa petugas (scheduler/console).
- **`ringkasan`:**
  - dihitung di database untuk seluruh periode, tidak terpengaruh `jenis` maupun paginasi;
  - `saldo_awal` = saldo setelah transaksi terakhir sebelum `dari`, atau saldo awal rekening (termasuk saldo migrasi sistem lama).

### PDF rekening koran

`GET /nasabah/rekening/{id}/mutasi.pdf?dari=&sampai=` mengikuti aturan tanggal yang sama, tanpa filter `jenis` dan tanpa paginasi.

- **Header:**
  - `Content-Type: application/pdf`;
  - `Content-Disposition: attachment; filename="rekening-koran-{nomor}-{dari}-{sampai}.pdf"`;
  - `Cache-Control: no-store`.
- **Batas:** 10 per menit.
- **Error:** tetap berformat JSON.

## Kode status

| Status | Arti |
| --- | --- |
| `401` | Belum login, sesi habis, akun/nasabah dinonaktifkan, akun direset teller, atau password diganti dari perangkat lain → ke halaman Login |
| `403` `VERIFIKASI_DIPERLUKAN` | Aktivasi langkah 2 tanpa/lewat 15 menit dari langkah 1 → kembali ke langkah 1 |
| `404` | Data tidak ada / bukan milik nasabah |
| `409` `SUDAH_AKTIVASI` | Nasabah sudah punya akun → ke halaman Login |
| `419` | CSRF/sesi tidak tersedia atau kedaluwarsa: ambil ulang `csrf-cookie` |
| `422` | Validasi, format Laravel `{ message, errors: { field: [pesan] } }` |
| `429` | Rate limit, header `Retry-After` (detik) |

## Keamanan

- **Middleware:** `aktivasi/cek`, `aktivasi`, dan `login` melewati `ib.sesi` (wajib sesi, selain itu `419`); semua route `api/v1/nasabah/*` lain melewati `auth.nasabah` dan `throttle:ib` (60 per menit per akun). Ini dijaga oleh test *route inventory*.
- **Aktivasi:** pesan gagal sama untuk semua penyebab, dibatasi 5 per menit; respons cek hanya berisi nama/kelas. Siapa pun yang tahu NISN **dan** nomor rekening bisa mengaktivasi akun yang belum aktif, jadi sosialisasikan agar nasabah segera aktivasi. Bila terlanjur diaktivasi orang lain, teller mereset akun.
- **Header respons API:** `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, dan HSTS bila HTTPS.
- **Activity log `nasabah`:** aktivasi, login, logout, ganti password, ganti No. HP, simpan profil, reset akun oleh petugas, dan unduh PDF. Password tidak pernah dicatat.
- **Data pribadi:** API hanya mengumpulkan data di tabel Profil. NIK maupun foto dokumen tidak diminta.

## Akun nasabah

- **Aktivasi oleh nasabah sendiri;** teller tidak membuat akun maupun password. Halaman detail nasabah di panel menampilkan status IB: *Belum aktivasi* (dengan identitas yang dipakai aktivasi: NISN atau No. nasabah) atau *Aktif* (No. HP login, tanggal aktivasi, login terakhir).
- **Reset akun IB** (permission `nasabah.reset_password_ib`, teller & bendahara) untuk nasabah yang lupa password atau ganti nomor: akun dihapus, semua sesinya langsung berakhir, lalu nasabah aktivasi ulang dan membuat No. HP & password baru.
- **Riwayat login:** `php artisan ib:bersihkan-login` menyimpan 50 entri terakhir per akun. Dijadwalkan setiap Minggu 03:00.

## Menjalankan bersama `provita-ib` secara lokal

1. **Laravel** (`.env`):
   - `SANCTUM_STATEFUL_DOMAINS=localhost:5173`
   - `IB_URL=http://localhost:5173`
   - `IB_SESSION_DOMAIN=` (kosong)
   - `SESSION_SECURE_COOKIE=false`
   
   Lalu `php artisan migrate --seed` dan `php artisan serve` (port 8000). Dengan `APP_ENV=local`, seeder membuat 3 nasabah contoh:
   - **belum aktivasi:** NISN `0090025039` + rekening `25039` (untuk mencoba alur aktivasi);
   - **aktif:** login `081234567891` / `Provita2026`;
   - **aktif, data lengkap:** login `081234567890` / `Provita2026`.
2. **Frontend** (`provita-ib`): `VITE_USE_MOCK=false VITE_API_URL=http://localhost:8000/api/v1 npm run dev`.
