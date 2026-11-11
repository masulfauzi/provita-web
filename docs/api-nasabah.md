# API Internet Banking Nasabah

API **hanya baca** untuk frontend `provita-ib` (Vue 3) di `ib.<domain>`. Nasabah login, wajib mengganti password dan melengkapi data pribadi, lalu bisa melihat saldo, mutasi, dan mengunduh rekening koran PDF.

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

Agar *Try it out* di `/docs/api` bisa login, host API juga dicantumkan di `SANCTUM_STATEFUL_DOMAINS` (mis. `ib.<domain>,api.<domain>`). Permintaan dari origin yang tidak terdaftar tidak mendapat sesi: login dijawab `419`, endpoint lain `401`.

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

## Endpoint

| # | Method & path | Kegunaan | Saat onboarding |
| --- | --- | --- | --- |
| 1 | `GET /sanctum/csrf-cookie` | Ambil cookie CSRF | — |
| 2 | `POST /nasabah/login` | Login | publik |
| 3 | `POST /nasabah/logout` | Keluar (`204`) | boleh |
| 4 | `GET /nasabah/me` | Data nasabah + status onboarding | boleh |
| 5 | `PUT /nasabah/password` | Ganti password | boleh |
| 6 | `GET /nasabah/profil` | Data pribadi + 10 riwayat login | setelah ganti password |
| 7 | `PUT /nasabah/profil` | Simpan data pribadi | setelah ganti password |
| 8 | `GET /nasabah/rekening` | Daftar rekening + saldo | tidak |
| 9 | `GET /nasabah/rekening/{id}` | Detail rekening | tidak |
| 10 | `GET /nasabah/rekening/{id}/mutasi` | Mutasi + ringkasan periode | tidak |
| 11 | `GET /nasabah/rekening/{id}/mutasi.pdf` | PDF rekening koran | tidak |

### Login

`POST /nasabah/login` dengan `{ "nis": "25041", "password": "Provita2026" }`.

- Kolom `nis` menerima NIS, nomor nasabah (`NSB-…`), atau nomor rekening.
- **Berhasil:** `200 { "data": <Me> }`.
- **Gagal:** identitas tidak ada, password salah, atau akun/nasabah nonaktif. Pesannya selalu sama:

```json
{ "message": "NIS atau password salah.", "errors": { "nis": ["NIS atau password salah."] }, "sisa_percobaan": 3 }
```

- **Batas percobaan:** 5 gagal per menit per NIS + IP. Setelah itu `429` dengan header `Retry-After: <detik>` dan body `{ "message": "...", "retry_after": 45 }`.

### Me

```json
{ "data": {
  "id": "uuid-nasabah", "nis": "25041", "nama": "Rizky Ramadhan", "nama_panggilan": "Rizky",
  "kelas": "XII TJKT 3", "jenis_nasabah": "siswa", "nomor_rekening": ["25041"],
  "wajib_ganti_password": false, "profil_lengkap": true, "login_terakhir": "2026-11-09T11:41:00+07:00"
} }
```

- `nis`: berisi nomor nasabah untuk guru/lembaga yang tidak punya NIS.
- `login_terakhir`: login berhasil **sebelum** sesi ini, atau `null`.

### Ganti password

`PUT /nasabah/password` dengan `{ password_lama, password, password_confirmation }`. Aturan (`422` per field):

- **`password_lama`:** harus cocok, selain itu "Password saat ini salah.".
- **`password`:**
  - minimal 8 karakter;
  - mengandung huruf dan angka;
  - tidak sama dengan password lama;
  - tidak sama dengan NIS, nomor nasabah, maupun nomor rekening milik nasabah.
- **`password_confirmation`:** harus sama, selain itu "Konfirmasi password tidak sama.".

Berhasil: `200 { "message": "Password berhasil diganti." }`. `wajib_ganti_password` menjadi `false`, dan **sesi di perangkat lain dikeluarkan** (`401`), sedangkan sesi ini tetap. Batas 10 permintaan per menit.

### Profil

`GET` mengembalikan field di bawah ditambah `riwayat_login` (`[{ waktu, perangkat, ip }]`, maksimal 10).

`PUT` hanya menerima field berikut; `nama`, `nis`, `kelas`, dan `jenis` diabaikan bila dikirim.

| Field | Aturan |
| --- | --- |
| `tempat_lahir`, `alamat_jalan`, `kelurahan`, `kecamatan`, `kota` | wajib, maks. 100–255 karakter |
| `tanggal_lahir` | wajib, `Y-m-d`, sebelum hari ini |
| `jenis_kelamin` | wajib, `L` / `P` |
| `rt`, `rw` | wajib, 1–3 digit |
| `kode_pos` | wajib, 5 digit |
| `no_hp` | wajib, `08` + 8–11 digit |
| `email` | opsional, email |
| `wali_nama`, `wali_hubungan` (`ayah`/`ibu`/`wali`), `wali_hp` | wajib untuk siswa, opsional untuk lainnya |
| `persetujuan` | wajib `true` |

Setelah berhasil:
- kolom `alamat` lama di tabel nasabah diisi gabungan alamat terstruktur;
- `nama_orang_tua` diisi dengan `wali_nama`;
- profil dianggap lengkap, sehingga endpoint rekening terbuka.

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
| `401` | Belum login, sesi habis, akun/nasabah dinonaktifkan, atau password diganti/direset dari tempat lain |
| `403` + `kode` | `WAJIB_GANTI_PASSWORD` (diperiksa lebih dulu) atau `WAJIB_LENGKAPI_DATA` |
| `404` | Data tidak ada / bukan milik nasabah |
| `419` | CSRF kedaluwarsa: ambil ulang `csrf-cookie` |
| `422` | Validasi, format Laravel `{ message, errors: { field: [pesan] } }` |
| `429` | Rate limit, header `Retry-After` (detik) |

## Keamanan

- **Middleware:** semua route `api/v1/nasabah/*` selain `login` melewati `auth.nasabah`, `ib.onboarding`, dan `throttle:ib` (60 per menit per akun). Ini dijaga oleh test *route inventory*.
- **Header respons API:** `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, dan HSTS bila HTTPS.
- **Activity log `nasabah`:** login, logout, ganti password, simpan profil, reset password oleh petugas, dan unduh PDF. Password tidak pernah dicatat.
- **Data pribadi:** API hanya mengumpulkan data di tabel Profil. NIK maupun foto dokumen tidak diminta.

## Akun nasabah

- **Akun massal:** `php artisan ib:buat-akun --semua` (atau `--nasabah=NSB-000001 --nasabah=25041`) membuat akun untuk nasabah aktif yang belum punya. Daftar password awal disimpan di `storage/app/private/ib-akun-<waktu>.xlsx`. **Bagikan, lalu hapus file tersebut.** Di database password hanya tersimpan sebagai hash.
- **Per nasabah:** di panel, halaman detail nasabah → **Reset password IB** (permission `nasabah.reset_password_ib`, teller & bendahara). Fitur ini:
  - membuat akun bila belum ada;
  - menampilkan password sementara **sekali** (bisa dicetak sebagai slip);
  - mewajibkan ganti password;
  - mengeluarkan semua sesi nasabah itu.
- **Riwayat login:** `php artisan ib:bersihkan-login` menyimpan 50 entri terakhir per akun. Dijadwalkan setiap Minggu 03:00.

## Menjalankan bersama `provita-ib` secara lokal

1. **Laravel** (`.env`):
   - `SANCTUM_STATEFUL_DOMAINS=localhost:5173`
   - `IB_URL=http://localhost:5173`
   - `IB_SESSION_DOMAIN=` (kosong)
   - `SESSION_SECURE_COOKIE=false`
   
   Lalu `php artisan migrate --seed` dan `php artisan serve` (port 8000). Dengan `APP_ENV=local`, seeder membuat 3 akun contoh yang sama dengan mock:
   - `25039` / `rahasia123`: wajib ganti password;
   - `25040` / `Provita2026`: data pribadi belum lengkap;
   - `25041` / `Provita2026`: lengkap.
2. **Frontend** (`provita-ib`): `VITE_USE_MOCK=false VITE_API_URL=http://localhost:8000/api/v1 npm run dev`.
