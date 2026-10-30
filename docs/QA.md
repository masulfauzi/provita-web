# Skenario Uji (QA) Bank Provita

Dokumen ini berisi daftar uji manual untuk aplikasi Bank Mini SMK Negeri 2 Semarang (Bank Provita). Skenario disusun **berurutan**: data yang dibuat di satu bagian dipakai lagi di bagian berikutnya, dan angka saldo di bagian Laporan bergantung pada semua transaksi sebelumnya. Kerjakan dari atas ke bawah tanpa melompat, kecuali skenario yang ditandai *(opsional)*.

## Daftar isi

0. [Persiapan](#0-persiapan)
1. [Login & kata sandi](#1-login--kata-sandi)
2. [Pengaturan](#2-pengaturan)
3. [Pengguna](#3-pengguna)
4. [Jenis rekening](#4-jenis-rekening)
5. [Nasabah](#5-nasabah)
6. [Sesi kas (buka)](#6-sesi-kas-buka)
7. [Buka rekening](#7-buka-rekening)
8. [Setor & tarik](#8-setor--tarik)
9. [Koreksi transaksi](#9-koreksi-transaksi)
10. [Tutup rekening](#10-tutup-rekening)
11. [Penerimaan kas](#11-penerimaan-kas)
12. [Pengeluaran kas](#12-pengeluaran-kas)
13. [Pembatalan bukti kas](#13-pembatalan-bukti-kas)
14. [Bagan akun](#14-bagan-akun)
15. [Jurnal umum](#15-jurnal-umum)
16. [Laporan keuangan](#16-laporan-keuangan)
17. [Dashboard](#17-dashboard)
18. [Tutup sesi kas & laporan kas harian](#18-tutup-sesi-kas--laporan-kas-harian)
19. [Log aktivitas](#19-log-aktivitas)
20. [Hak akses per peran](#20-hak-akses-per-peran)
21. [Uji umum & ketahanan](#21-uji-umum--ketahanan)
22. [Ringkasan hasil](#22-ringkasan-hasil)

---

## 0. Persiapan

### 0.1 Yang disiapkan developer

1. Database **baru dan kosong**, dibuat dengan `php artisan migrate:fresh --seed` lalu `php artisan permission:sync-modules --prune`.
2. Alamat aplikasi (URL) yang bisa dibuka tester.
3. Kata sandi akun bawaan **bendahara** dan **teller**.
4. *(Opsional)* Satu NISN siswa yang **benar-benar ada** di data siswa sekolah, untuk menguji pengisian otomatis dari API sekolah.

Tester sebaiknya memakai **dua browser berbeda** (misalnya Chrome dan Firefox, atau satu jendela biasa dan satu jendela penyamaran) supaya bisa login sebagai dua pengguna sekaligus.

### 0.2 Akun uji

| Username | Peran | Kata sandi | Keterangan |
| --- | --- | --- | --- |
| `bendahara` | bendahara | dari developer | Akun bawaan, semua menu |
| `teller` | teller | dari developer | Akun bawaan |
| `admin1` | admin | `Rahasia123` | Dibuat di skenario PGN-02 |
| `teller2` | teller | `Rahasia123` → `Baru12345` | Dibuat di PGN-03; kata sandi direset di PGN-07 |

### 0.3 Data uji yang akan dibuat

Semua transaksi dilakukan pada **satu tanggal transaksi**, yaitu tanggal hari pengujian (disebut **T**).

**Nasabah**

| No. nasabah | Nama | Jenis | NISN / NIP | Kelas | Dibuat di |
| --- | --- | --- | --- | --- | --- |
| NSB-000001 | Budi Santoso | Siswa | NISN `9999999901` | X TKJ 1 | NSB-03 |
| NSB-000002 | Citra Lestari | Siswa | NISN `9999999902` | XI AK 2 | NSB-05 |
| NSB-000003 | Dedi Pratama | Guru | NIP `198001012005011001` | – | NSB-08 |
| NSB-000004 | Eko Saputra | Karyawan | (kosong) | – | NSB-09 |
| NSB-000005 | Fajar Nugroho | Siswa | NISN `9999999905` | X TKJ 1 | NSB-10 (nanti dihapus) |
| NSB-000006 | (nama dari API) | Siswa | NISN asli dari sekolah | dari API | NSB-12 *(opsional)* |

> Bila NISN `99999999xx` ternyata ditemukan di data sekolah, ganti dengan angka lain berawalan `99999999` dan catat nomor penggantinya. Nomor rekening di bawah ikut berubah.

**Rekening dan saldo akhir yang diharapkan**

| Kode | Nomor rekening | Pemilik | Jenis | Setoran awal | Saldo akhir | Status akhir |
| --- | --- | --- | --- | --- | --- | --- |
| R1 | `9999999901` | Budi | Tabungan | Rp 100.000 | **Rp 275.000** | Aktif |
| R2 | `29999999901` | Budi | Tabungan Pendidikan | Rp 50.000 | **Rp 50.000** | Aktif |
| R3 | `99999999011` | Budi | Tabungan | Rp 20.000 | Rp 0 | Ditutup |
| R4 | `9999999902` | Citra | Tabungan | Rp 30.000 | Rp 0 | Ditutup |
| R5 | `198001012005011001` | Dedi | Tabungan | Rp 200.000 | **Rp 50.000** | Aktif |

**Pengaturan yang dipakai**

| Pengaturan | Nilai |
| --- | --- |
| Tanggal transaksi aktif | T (hari ini) |
| Modal awal sesi kas (diisi di SES-03) | Rp 1.000.000 |
| Setoran awal minimal | Rp 10.000 |
| Biaya administrasi penutupan | Rp 5.000 |
| Harga buku tabungan | Rp 15.000 |

### 0.4 Cara mengisi dokumen

- Kolom **Hasil**: isi ✅ bila sesuai harapan, ❌ bila tidak sesuai, ⏭️ bila dilewati.
- Kolom **Catatan**: untuk ❌, tulis apa yang terjadi, lampirkan screenshot, dan sebutkan ID skenarionya (misalnya "TRX-04: pesan tidak muncul").
- Teks dalam tanda kutip pada kolom harapan adalah **pesan yang harus muncul persis** (huruf besar-kecil dan tanda baca boleh diabaikan).
- Pesan sukses muncul sebagai kotak notifikasi hijau di pojok layar. Pesan gagal muncul merah, atau sebagai tulisan merah di bawah kolom isian.

### 0.5 Perilaku yang memang begitu (bukan bug)

- Belum ada fitur **cetak struk setor/tarik** dan **cetak buku tabungan**. "Buku tabungan" adalah halaman daftar mutasi rekening.
- Menu **Pinjaman** masih bertuliskan segera hadir.
- Belum ada saldo minimal mengendap: rekening boleh bersaldo Rp 0.
- Tidak ada akun admin bawaan. Akun admin dibuat sendiri di bagian 3.
- Setoran, penarikan, dan bukti kas tidak bisa diubah atau dihapus. Kesalahan diperbaiki dengan **koreksi** atau **pembatalan**, yang membuat transaksi pembalik.
- "Saldo kas hari ini" di Dashboard dan "saldo kas seharusnya" di Sesi kas berbeda dengan "Saldo kas" di Laporan keuangan. Dashboard dan Sesi kas memakai *modal awal sesi kas* (uang tunai di laci teller), sedangkan Laporan keuangan menghitung dari jurnal pembukuan.
- Semua petugas (teller maupun bendahara) hanya bisa setor, tarik, koreksi, buka/tutup rekening, serta mencatat dan membatalkan penerimaan/pengeluaran kas selama **sesi kas** tanggal transaksi terbuka. Jurnal umum tidak terpengaruh.

---

## 1. Login & kata sandi

Gunakan akun `teller`.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| AUTH-01 | Buka alamat aplikasi tanpa login. | Diarahkan ke halaman login. Ada tulisan "Bank Mini SMK Negeri 2 Semarang", kolom **Nama pengguna**, **Kata sandi**, kotak **Ingat saya**, dan tombol **Masuk**. | | |
| AUTH-02 | Klik **Masuk** dengan kedua kolom kosong. | Muncul pesan bahwa nama pengguna dan kata sandi wajib diisi. Tetap di halaman login. | | |
| AUTH-03 | Isi username `teller` dan kata sandi `salah123`, lalu klik **Masuk**. | "Nama pengguna atau kata sandi salah." Kolom kata sandi dikosongkan lagi. | | |
| AUTH-04 | Isi username `tidakada` dengan kata sandi apa saja. | "Nama pengguna atau kata sandi salah." (pesan sama, tidak membocorkan apakah username ada). | | |
| AUTH-05 | Klik ikon mata di kolom kata sandi. | Kata sandi tampil sebagai teks, lalu tersembunyi lagi bila diklik kembali. | | |
| AUTH-06 | Batas percobaan: dengan username `teller`, masukkan kata sandi salah **5 kali** berturut-turut, lalu coba ke-6 kali dengan kata sandi **benar**. | Percobaan ke-6 ditolak: "Terlalu banyak percobaan masuk. Silakan coba lagi dalam … detik." | | |
| AUTH-07 | Tunggu ±60 detik, lalu login dengan kata sandi benar. | Berhasil masuk ke **Dashboard**. | | |
| AUTH-08 | Perhatikan bagian atas aplikasi. | Nama pengguna, peran **teller**, tanggal transaksi aktif, dan tanda **Sesi kas belum dibuka** tampil. Menu di samping sesuai peran teller (lihat HAK-01). | | |
| AUTH-09 | Klik avatar/nama pengguna, lalu **Keluar**. | Kembali ke halaman login. | | |
| AUTH-10 | Setelah keluar, tekan tombol **Back** di browser, atau ketik langsung alamat `/dashboard`. | Tidak bisa melihat dashboard; diarahkan ke halaman login. | | |
| AUTH-11 | Login sebagai `teller` lalu buka alamat `/login`. | Langsung diarahkan ke Dashboard (sudah login). | | |
| AUTH-12 | Ganti kata sandi: menu avatar → **Ganti kata sandi**. Isi kata sandi saat ini salah, lalu simpan. | "Kata sandi salah." | | |
| AUTH-13 | Isi kata sandi saat ini benar, kata sandi baru `Teller2026`, konfirmasi `Teller2027`. | "Konfirmasi kata sandi baru tidak cocok." | | |
| AUTH-14 | Isi kata sandi baru `abc12` (kurang dari 8 karakter) dengan konfirmasi yang sama. | Pesan kata sandi baru minimal 8 karakter. | | |
| AUTH-15 | Isi kata sandi saat ini benar, kata sandi baru `Teller2026` dan konfirmasi `Teller2026`. | "Kata sandi berhasil diganti." | | |
| AUTH-16 | Keluar, login dengan kata sandi **lama**. Lalu login dengan `Teller2026`. | Kata sandi lama ditolak; kata sandi baru berhasil. **Mulai sekarang gunakan `Teller2026` untuk teller.** | | |
| AUTH-17 | Klik tombol mode gelap/terang di bagian atas. | Tampilan berganti gelap/terang dan tetap terbaca. Setelah halaman dimuat ulang, pilihan tetap. | | |
| AUTH-18 | **Ingat saya:** keluar, lalu login dengan mencentang **Ingat saya**. Tutup browser sepenuhnya, lalu buka lagi alamat aplikasi. | Langsung masuk tanpa diminta login. | | |

---

## 2. Pengaturan

Login sebagai `bendahara`. Menu **Sistem → Pengaturan**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| SET-01 | Buka halaman Pengaturan. | Ada 3 kartu: **Operasional harian**, **Keuangan**, **Umum**, masing-masing dengan tombol **Simpan** sendiri. | | |
| SET-02 | Kartu Umum: isi Nama bank `Bank Provita`, Nama sekolah `SMK Negeri 2 Semarang`, Alamat sekolah `Jl. Dr. Cipto 121A, Semarang`, Nama kepala sekolah `Drs. Contoh Kepala, M.Pd.`. Klik Simpan. | "Pengaturan berhasil disimpan." Nilai tetap setelah halaman dimuat ulang. | | |
| SET-03 | Kartu Keuangan: isi Suku bunga tabungan per tahun `150`, lalu Simpan. | Muncul pesan error di bawah kolom suku bunga (maksimal 100). Tidak tersimpan. | | |
| SET-04 | Kartu Keuangan: isi Biaya administrasi penutupan `-5000`, lalu Simpan. | Ditolak (tidak boleh negatif); bila kolom tidak bisa diisi minus, tandai ✅. | | |
| SET-05 | Kartu Keuangan: isi Suku bunga `0`, Setoran awal minimal `10.000`, Biaya administrasi penutupan `5.000`, Harga buku tabungan `15.000`, Bunga minimal `0`. Simpan. | "Pengaturan berhasil disimpan." | | |
| SET-06 | Kartu Operasional harian: isi Tanggal transaksi aktif = **T (hari ini)**. Simpan. | "Pengaturan berhasil disimpan." Tanggal transaksi di bagian atas aplikasi menampilkan tanggal T. Tidak ada lagi isian Uang modal (modal diisi saat membuka sesi kas). | | |
| SET-07 | Kosongkan Tanggal transaksi aktif, lalu Simpan. | Ditolak: tanggal transaksi wajib diisi. Isi lagi dengan T. | | |

---

## 3. Pengguna

Login sebagai `bendahara`. Menu **Sistem → Pengguna**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| PGN-01 | Buka halaman Pengguna. | Tampil `bendahara` dan `teller` dengan kolom Username, Nama, Peran, Status, Login terakhir. Login terakhir terisi. | | |
| PGN-02 | Klik **Tambah pengguna**. Isi Username `admin1`, Nama lengkap `Admin Satu`, Email `admin1@contoh.sch.id`, Peran **admin**, Kata sandi dan Ulangi kata sandi `Rahasia123`. Simpan. | "Pengguna berhasil ditambahkan." `admin1` muncul di daftar dengan peran admin, status aktif. | | |
| PGN-03 | Tambah pengguna `teller2`, nama `Teller Dua`, email kosong, peran **teller**, kata sandi `Rahasia123`. | "Pengguna berhasil ditambahkan." | | |
| PGN-04 | Validasi: coba tambah pengguna dengan masing-masing isian berikut (satu per satu):<br>a. Username `ab` (2 huruf)<br>b. Username `budi santoso` (ada spasi)<br>c. Username `teller2` (sudah ada)<br>d. Email `admin1@contoh.sch.id` (sudah dipakai)<br>e. Peran tidak dipilih<br>f. Ulangi kata sandi berbeda<br>g. Kata sandi `abc` | Setiap percobaan ditolak dengan pesan merah di kolom yang salah:<br>a. minimal 3 karakter<br>b. hanya huruf, angka, strip, garis bawah<br>c. nama pengguna sudah digunakan<br>d. email sudah digunakan<br>e. peran wajib diisi<br>f. konfirmasi kata sandi tidak cocok<br>g. minimal 8 karakter | | |
| PGN-05 | Ketik `dua` di kotak cari; lalu kosongkan dan pilih filter peran **admin**. | Pencarian hanya menampilkan `teller2`. Filter admin hanya menampilkan `admin1`. | | |
| PGN-06 | Buka **Ubah** pada `teller2`. Ubah nama menjadi `Teller Kedua`, simpan. | "Pengguna berhasil diperbarui." Kolom username tidak bisa diubah. | | |
| PGN-07 | Di halaman ubah `teller2`, klik **Reset kata sandi**, isi `Baru12345` dua kali, simpan. | "Kata sandi teller2 berhasil direset." | | |
| PGN-08 | Di browser kedua, login sebagai `teller2` dengan `Rahasia123`, lalu dengan `Baru12345`. | Kata sandi lama ditolak; kata sandi baru berhasil. | | |
| PGN-09 | **Nonaktifkan pengguna:** sebagai bendahara, buka ubah `teller2`, klik **Nonaktifkan**. | Muncul konfirmasi "teller2 tidak akan bisa login sampai diaktifkan kembali. Lanjutkan?". Setelah dikonfirmasi: "Pengguna teller2 dinonaktifkan." Status di daftar berubah nonaktif. | | |
| PGN-10 | Di browser kedua (yang tadi login sebagai `teller2`), klik menu apa saja. | Sesi berakhir; diarahkan ke halaman login. | | |
| PGN-11 | Login sebagai `teller2` dengan `Baru12345`. | "Akun Anda dinonaktifkan. Hubungi bendahara." | | |
| PGN-12 | Sebagai bendahara, klik **Aktifkan** pada `teller2`. Login lagi sebagai `teller2`. | "Pengguna teller2 diaktifkan." Login berhasil. | | |
| PGN-13 | **Uji potensi bug "Ingat saya":** di browser kedua, keluar lalu login sebagai `teller2` dengan mencentang **Ingat saya**. Sebagai bendahara, nonaktifkan `teller2`. Di browser kedua, tutup browser sepenuhnya lalu buka lagi alamat aplikasi. Setelah itu, aktifkan kembali `teller2`. | Harus diminta login lagi, dan login ditolak karena akun nonaktif. **Bila `teller2` ternyata langsung masuk, catat sebagai bug.** | | |
| PGN-14 | Sebagai bendahara, buka ubah akun **bendahara sendiri**. | Tombol Nonaktifkan **tidak ada** untuk akun sendiri. | | |
| PGN-15 | Ubah peran `teller2` menjadi teller **dan** admin, simpan. Login ulang sebagai `teller2`. Lalu kembalikan perannya menjadi teller saja. | Selama berperan ganda, menu `teller2` = gabungan menu teller dan admin (ada menu Pengguna). Setelah dikembalikan, menu Pengguna hilang. | | |

---

## 4. Jenis rekening

Login sebagai `bendahara`. Menu **Data Master → Jenis Rekening**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| JRK-01 | Buka halaman Jenis Rekening. | Ada jenis bawaan **Tabungan**, kode `tabungan`, prefix kosong dengan tanda **Rekening utama**, status aktif. | | |
| JRK-02 | Klik **Tambah jenis**. Isi Nama jenis `Tabungan Pendidikan`, Kode `tabungan_pendidikan`, Prefix `2`, Keterangan `Tabungan biaya sekolah`. Simpan. | "Jenis rekening Tabungan Pendidikan ditambahkan." Kolom Contoh nomor diawali angka 2. | | |
| JRK-03 | Validasi kode: tambah jenis dengan Kode `Tabungan Qurban` (huruf besar dan spasi). | "Kode hanya boleh huruf kecil, angka, dan garis bawah, diawali huruf." | | |
| JRK-04 | Validasi prefix: coba Prefix `A1`, lalu Prefix `123456`. | Keduanya ditolak: "Prefix hanya boleh angka (1–5 digit), mis. 2." | | |
| JRK-05 | Tambah jenis `Tabungan Qurban`, kode `tabungan_qurban`, **prefix dikosongkan**. | "Rekening utama (tanpa prefix) sudah ada: Tabungan. Isi prefix angka." | | |
| JRK-06 | Duplikat: tambah jenis dengan nama `Tabungan Pendidikan`, lalu dengan kode `tabungan_pendidikan`, lalu dengan prefix `2`. | Ketiganya ditolak dengan pesan "sudah digunakan" pada kolom yang sama. | | |
| JRK-07 | Tambah jenis `Tabungan Qurban`, kode `tabungan_qurban`, prefix `3`. | "Jenis rekening Tabungan Qurban ditambahkan." | | |
| JRK-08 | Klik ubah pada `Tabungan Qurban`. Ubah keterangan menjadi `Untuk qurban Iduladha`, simpan. | Kolom Kode tidak bisa diubah (abu-abu). Perubahan tersimpan: "…diperbarui." | | |
| JRK-09 | Klik **Nonaktifkan** pada `Tabungan Qurban`. | "…dinonaktifkan; tidak bisa dipilih saat membuka rekening." Status nonaktif. (Pemeriksaan di form buka rekening dilakukan di REK-02.) | | |
| JRK-10 | Klik **Hapus** pada `Tabungan Qurban` dan konfirmasi. | Jenis terhapus dari daftar. | | |

---

## 5. Nasabah

Login sebagai `teller` (kata sandi `Teller2026`). Menu **Data Master → Nasabah**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| NSB-01 | Klik **Tambah nasabah**, pilih jenis **Siswa**. | Hanya tampil kolom NISN dan tombol **Periksa**, dengan petunjuk untuk memasukkan NISN lalu klik Periksa. Kolom lain belum tampil dan tombol simpan tidak aktif. | | |
| NSB-02 | Ketik NISN `12345` lalu klik **Periksa**. | "NISN harus 10 digit angka." | | |
| NSB-03 | **NISN tidak ditemukan:** ketik `9999999901`, tekan **Enter**. | "NISN tidak ditemukan di data siswa sekolah. Isi data secara manual." Kolom lain kini tampil dan kosong. | | |
| NSB-04 | Isi Nama lengkap `Budi Santoso`, NIS `24001`, Jenis kelamin Laki-laki, Kelas `x  tkj 1` (huruf kecil, dua spasi), Tempat lahir `Semarang`, Tanggal lahir `15/05/2009`, Nomor HP `0812-3456-7890`, Email `Budi@Contoh.com`, Nama orang tua `Sutrisno`, Alamat `Jl. Pandanaran 1`. Simpan. | "Nasabah Budi Santoso terdaftar dengan nomor NSB-000001." Halaman detail tampil dengan Kelas `X TKJ 1` dan email `budi@contoh.com` (huruf kecil). | | |
| NSB-05 | Tambah siswa lagi: NISN `9999999902` → Periksa (tidak ditemukan), Nama `Citra Lestari`, Perempuan, Kelas `XI AK 2`, NIK `3374010101090002`. Simpan. | "Nasabah Citra Lestari terdaftar dengan nomor NSB-000002." | | |
| NSB-06 | **NISN sudah terdaftar:** tambah siswa, NISN `9999999901` → Periksa. | "NISN ini sudah terdaftar sebagai nasabah Budi Santoso (NSB-000001)." dengan tautan; klik tautan membuka detail Budi. Form tidak bisa disimpan. | | |
| NSB-07 | **Validasi isian:** tambah siswa NISN `9999999903` → Periksa, lalu coba simpan dengan (satu per satu):<br>a. Nama kosong<br>b. NIK `12345`<br>c. NIK `3374010101090002` (milik Citra)<br>d. Nomor HP `08abc`<br>e. Tanggal lahir besok (atau ketik manual)<br>f. Email `budi@` | Setiap percobaan ditolak dengan pesan merah:<br>a. nama wajib diisi<br>b. "NIK harus 16 digit angka."<br>c. NIK sudah digunakan<br>d. format nomor HP tidak valid<br>e. tanggal lahir harus sebelum hari ini (atau tanggal masa depan tidak bisa dipilih)<br>f. email tidak valid<br>Batalkan form ini (jangan disimpan). | | |
| NSB-08 | Tambah **Guru**: pilih jenis Guru. | Kolom NISN, Kelas, dan Nama orang tua **tidak tampil**. Kolom NIS berlabel **NIP / No. pegawai**. Form langsung bisa diisi tanpa periksa NISN. | | |
| NSB-08a | Isi Nama `Dedi Pratama`, NIP `198001012005011001`, Laki-laki. Simpan. | "Nasabah Dedi Pratama terdaftar dengan nomor NSB-000003." | | |
| NSB-09 | Tambah **Karyawan**: Nama `Eko Saputra`, Laki-laki, **NIP dikosongkan**. Simpan. | "Nasabah Eko Saputra terdaftar dengan nomor NSB-000004." | | |
| NSB-10 | Tambah siswa: NISN `9999999905` → Periksa, Nama `Fajar Nugroho`, Laki-laki, Kelas `X TKJ 1`. Simpan. | "…terdaftar dengan nomor NSB-000005." | | |
| NSB-11 | Ganti NISN setelah diperiksa: tambah siswa, NISN `9999999906` → Periksa, isi Nama `Coba`, lalu ubah kolom NISN menjadi `9999999907`. | Kolom lain tersembunyi lagi sampai NISN diperiksa ulang. Batalkan form ini. | | |
| NSB-12 | *(Opsional, perlu NISN asli dan internet)* **NISN ditemukan:** tambah siswa, ketik NISN asli dari sekolah → Periksa. | "Data siswa ditemukan dan sudah diisikan. Lengkapi data lain yang masih kosong." Nama, NIS, kelas, dll. terisi otomatis dari data sekolah. Simpan → nomor NSB-000006. | | |
| NSB-13 | *(Opsional, dibantu developer)* **API sekolah tidak bisa dihubungi:** developer mematikan akses ke API, lalu periksa NISN `9999999908`. | Pesan peringatan berisi "API data siswa tidak dapat dihubungi. Isi data secara manual." Form tetap bisa diisi manual. Batalkan form. | | |
| NSB-14 | **Ubah nasabah:** buka Budi → **Ubah**, ganti Nomor HP menjadi `081299990000`. Simpan. | "Data nasabah berhasil diperbarui." Pada form ubah, NISN tidak wajib diperiksa ulang. | | |
| NSB-15 | **Cari & filter** di daftar nasabah: <br>a. Cari `budi` <br>b. Cari `NSB-000003` <br>c. Cari `9999999902` <br>d. Filter jenis **Guru** <br>e. Filter kelas **X TKJ 1** | a. Budi <br>b. Dedi <br>c. Citra <br>d. Dedi saja <br>e. Budi dan Fajar | | |
| NSB-16 | Sebagai teller, buka detail Fajar. | Tombol **Nonaktifkan** dan **Hapus** **tidak tampil** untuk teller. | | |
| NSB-17 | Login sebagai `bendahara`. Buka Fajar → **Nonaktifkan**. | Konfirmasi "Nasabah Fajar Nugroho tidak akan bisa bertransaksi sampai diaktifkan kembali. Lanjutkan?" → "Nasabah Fajar Nugroho dinonaktifkan." Tombol **Buka rekening** tidak tampil di detail Fajar. | | |
| NSB-18 | Filter status **nonaktif** di daftar nasabah. | Hanya Fajar. | | |

---

## 6. Sesi kas (buka)

Login sebagai `teller`. Menu **Kas → Sesi kas**. Sesi kas harus dibuka sebelum teller bisa membuka rekening dan bertransaksi.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| SES-01 | Buka Sesi kas. | Tanggal T dengan tanda **Belum dibuka**, isian **Modal awal**, tombol **Buka sesi**. Riwayat sesi: "Belum ada sesi kas." Di bagian atas tampil tanda **Sesi kas belum dibuka**. | | |
| SES-02 | **Transaksi tanpa sesi:** buka detail Budi → **Buka rekening**, jenis Tabungan, setoran awal `100.000`, simpan. Lalu buka **Kas → Penerimaan kas**, kategori Pendapatan penjualan buku tabungan, simpan. Ulangi buka rekening Budi sebagai `bendahara`. | Ketiganya ditolak (bendahara juga): "Sesi kas tanggal <T> belum dibuka. Buka sesi kas terlebih dahulu." Rekening tidak dibuat; penerimaan kas tidak tercatat. | | |
| SES-03 | Kembali ke Sesi kas. Isi Modal awal `1.000.000`, klik **Buka sesi**. | "Sesi kas <T> dibuka dengan modal awal Rp 1.000.000." Tanda berubah **Terbuka**. Ringkasan: Modal awal Rp 1.000.000, Pemasukan Rp 0, Pengeluaran Rp 0, **Saldo kas seharusnya Rp 1.000.000**. Bagian atas: **Sesi kas terbuka**. | | |
| SES-04 | Periksa Riwayat sesi. | Satu baris tanggal T, modal Rp 1.000.000, selisih bertanda **Terbuka**, petugas Teller. | | |
| SES-05 | Buka Dashboard. | Kartu Saldo kas hari ini Rp 1.000.000 dengan catatan "Modal sesi Rp 1.000.000 + mutasi kas Rp 0". | | |
| SES-06 | Login `bendahara`, buka Sesi kas. | Bendahara melihat sesi yang sama (satu sesi per hari untuk semua petugas) dan kini bisa bertransaksi. | | |

---

## 7. Buka rekening

Login sebagai `teller`. Menu **Tabungan → Rekening** (atau tombol **Buka rekening** di halaman nasabah).

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| REK-01 | Buka detail nasabah Budi → klik **Buka rekening**. | Form Buka rekening tampil dengan Budi sudah terpilih. Setoran awal terisi Rp 10.000 dengan petunjuk "Minimal Rp 10.000 · sepuluh ribu rupiah". | | |
| REK-02 | Buka daftar pilihan **Jenis rekening**. | Ada **Tabungan** (paling atas) dan **Tabungan Pendidikan**. Tabungan Qurban yang sudah dihapus tidak ada. | | |
| REK-03 | Pilih Tabungan, isi Setoran awal `5.000`, simpan. | "Setoran awal minimal Rp 10.000." Rekening tidak dibuat. | | |
| REK-04 | **R1:** Budi, Tabungan, Setoran awal `100.000`. Simpan. | "Rekening 9999999901 dibuka dengan setoran awal Rp 100.000." Halaman buku tabungan tampil, saldo Rp 100.000. | | |
| REK-05 | Periksa tabel mutasi R1. | Satu baris: tanggal T, jenis setor, keterangan "Setoran awal", Kredit Rp 100.000, Saldo Rp 100.000, Petugas Teller. | | |
| REK-06 | **R2 (ber-prefix):** buka rekening Budi, jenis **Tabungan Pendidikan**, setoran awal `50.000`. | "Rekening 29999999901 dibuka dengan setoran awal Rp 50.000." | | |
| REK-07 | **R3 (nomor bentrok):** buka rekening Budi lagi, jenis **Tabungan**, setoran awal `20.000`. | "Rekening 99999999011 dibuka…" (nomor NISN + akhiran 1 karena 9999999901 sudah dipakai). | | |
| REK-08 | **R4:** Citra, Tabungan, setoran awal `30.000`. | "Rekening 9999999902 dibuka dengan setoran awal Rp 30.000." | | |
| REK-09 | **R5 (guru):** Dedi, Tabungan, setoran awal `200.000`. | "Rekening 198001012005011001 dibuka…" (nomor dari NIP). | | |
| REK-10 | **Tanpa NIP:** Eko, Tabungan, setoran awal `10.000`. | "Lengkapi NIP / No. pegawai Eko Saputra terlebih dahulu; nomor rekening dibuat dari NIP." Rekening tidak dibuat. | | |
| REK-11 | **Nasabah nonaktif:** di form Buka rekening, ketik `Fajar` di kolom nasabah. | Fajar tidak muncul di pilihan. | | |
| REK-12 | Ketik di alamat browser `/rekening/create?nasabah=` diikuti ID Fajar (salin dari alamat halaman detail Fajar). | Muncul peringatan "Nasabah Fajar Nugroho tidak aktif sehingga tidak dapat dibukakan rekening." | | |
| REK-13 | Daftar rekening: cari `Budi`, lalu filter jenis **Tabungan Pendidikan**. | Pencarian: 3 rekening Budi (R1, R2, R3). Filter: hanya R2. | | |
| REK-14 | Buka halaman detail Budi. | Tabel rekening berisi R1, R2, R3 dengan saldo Rp 100.000, Rp 50.000, Rp 20.000. | | |
| REK-15 | Login `bendahara`. Buka detail Budi → **Hapus**. | Ditolak: "Nasabah sudah memiliki rekening sehingga tidak bisa dihapus. Gunakan Nonaktifkan." | | |
| REK-16 | Sebagai bendahara, buka **Jenis Rekening**. | **Tabungan** dan **Tabungan Pendidikan** tidak punya tombol Hapus (sudah dipakai rekening). Jumlah rekening: Tabungan 4, Tabungan Pendidikan 1. | | |
| REK-17 | Sebagai bendahara, buka Fajar → **Hapus** dan konfirmasi. | "Nasabah berhasil dihapus." Fajar hilang dari daftar. | | |

---

## 8. Setor & tarik

Login sebagai `teller`. Menu **Tabungan → Setor / Tarik**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| TRX-01 | Buka halaman Setor / Tarik. | Ada pilihan **Setoran** dan **Penarikan**, kolom cari rekening, nominal, keterangan. Tombol simpan tidak aktif selama rekening atau nominal kosong. | | |
| TRX-02 | Cari rekening dengan: `budi`, `24001` (NIS), `9999999901` (NISN), `NSB-000003`. | Hasil menampilkan nama, nomor rekening, kelas, dan saldo. NIS/NISN Budi memunculkan R1, R2, R3. | | |
| TRX-03 | **Setor R1:** pilih R1, Setoran, nominal `250.000`, keterangan `Tabungan bulanan`. | Di bawah nominal muncul terbilang "dua ratus lima puluh ribu rupiah". | | |
| TRX-04 | Klik **Simpan setoran**. | Muncul konfirmasi "Setoran Rp 250.000 untuk rekening 9999999901 a.n. Budi Santoso?". Klik Batal → tidak ada yang tersimpan. | | |
| TRX-05 | Ulangi simpan, lalu konfirmasi. | "Setoran Rp 250.000 berhasil. Saldo sekarang Rp 350.000." Diarahkan ke buku tabungan R1. | | |
| TRX-06 | **Tarik R1:** dari buku tabungan R1 klik **Tarik**, nominal `75.000`, simpan dan konfirmasi. | "Penarikan Rp 75.000 berhasil. Saldo sekarang Rp 275.000." | | |
| TRX-07 | **Tarik melebihi saldo:** tarik R1 `1.000.000`. | "Saldo tidak cukup. Saldo Rp 275.000, penarikan Rp 1.000.000." Saldo tetap Rp 275.000. | | |
| TRX-08 | **Nominal tidak valid:** coba nominal `0`, lalu kosong. | Tombol simpan tidak aktif atau muncul pesan nominal harus lebih dari 0. Tidak ada transaksi tercatat. | | |
| TRX-09 | **Tarik habis:** tarik R4 (Citra) `30.000`. | "Penarikan Rp 30.000 berhasil. Saldo sekarang Rp 0." | | |
| TRX-10 | Setor R4 `40.000`. | "…Saldo sekarang Rp 40.000." | | |
| TRX-11 | Tarik R4 `37.000`. | "…Saldo sekarang Rp 3.000." | | |
| TRX-12 | Setor R5 (Dedi) `100.000`, lalu tarik R5 `250.000`. | Saldo R5 menjadi Rp 300.000, lalu Rp 50.000. | | |
| TRX-13 | **Salah input (untuk dikoreksi di bagian 9):** setor R1 `10.000`, keterangan `Salah input`. | "…Saldo sekarang Rp 285.000." | | |
| TRX-14 | Buka buku tabungan R1. | Urutan mutasi (terbaru di atas): Setoran 10.000 (saldo 285.000), Tarik 75.000 (275.000), Setor 250.000 (350.000), Setoran awal 100.000 (100.000). Semua bertanggal T, petugas Teller. Kolom koreksi **tidak tampil** untuk teller. | | |

---

## 9. Koreksi transaksi

Login sebagai `bendahara`.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| KOR-01 | Buka buku tabungan R1. | Setiap baris punya tombol **Koreksi**. | | |
| KOR-02 | Klik Koreksi pada setoran Rp 10.000 "Salah input". Kosongkan alasan lalu klik **Buat pembalik**. | Ditolak: alasan koreksi wajib diisi. | | |
| KOR-03 | Isi Alasan koreksi `Nominal seharusnya tidak disetor`, klik **Buat pembalik**. | "Transaksi pembalik berhasil dicatat." Muncul baris baru jenis pembalik, Debit Rp 10.000, keterangan "Koreksi: Nominal seharusnya tidak disetor", saldo Rp 275.000. Baris setoran asal bertanda **Dikoreksi**. | | |
| KOR-04 | Periksa baris yang sudah dikoreksi dan baris pembalik. | Keduanya **tidak** punya tombol Koreksi lagi. | | |
| KOR-05 | **Koreksi ditolak karena saldo kurang:** buka buku tabungan R5 (saldo Rp 50.000). Koreksi baris **setoran Rp 100.000** dengan alasan `Uji`. | "Saldo tidak cukup. Saldo Rp 50.000, penarikan Rp 100.000." Saldo R5 tetap Rp 50.000. | | |
| KOR-06 | Login `teller`, buka buku tabungan R1. | Kolom/tombol Koreksi tidak tampil. Saldo R1 Rp 275.000. | | |

---

## 10. Tutup rekening

Login sebagai `teller`.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| TUP-01 | Buka buku tabungan R3 (`99999999011`, saldo Rp 20.000), klik **Tutup rekening**. | Konfirmasi: "Saldo Rp 20.000 dipotong biaya administrasi Rp 5.000. Uang yang diserahkan ke nasabah: Rp 15.000. Rekening tidak bisa dipakai lagi. Lanjutkan?" | | |
| TUP-02 | Konfirmasi tutup. | "Rekening 99999999011 ditutup. Uang yang diserahkan ke nasabah: Rp 15.000 (biaya administrasi Rp 5.000)." | | |
| TUP-03 | Periksa buku tabungan R3. | Ada baris "Biaya administrasi penutupan" Rp 5.000 dan "Penutupan rekening" Rp 15.000; saldo akhir Rp 0. Status **Ditutup**. Tombol Setor, Tarik, dan Tutup rekening hilang. | | |
| TUP-04 | **Saldo lebih kecil dari biaya:** tutup R4 (Citra, saldo Rp 3.000). | Konfirmasi menyebut biaya Rp 3.000 dan uang diserahkan Rp 0. Setelah dikonfirmasi: "Rekening 9999999902 ditutup. Uang yang diserahkan ke nasabah: Rp 0 (biaya administrasi Rp 3.000)." | | |
| TUP-05 | Di Setor / Tarik, cari `9999999902` dan `99999999011`. | Rekening yang ditutup tidak muncul di pencarian. | | |
| TUP-06 | Daftar rekening, filter status **Ditutup**. | R3 dan R4. | | |
| TUP-07 | Login `bendahara`, buka buku tabungan R4. | Tidak ada tombol Koreksi (rekening sudah ditutup). | | |

---

## 11. Penerimaan kas

Login sebagai `teller`. Menu **Kas → Penerimaan kas**.

Saldo kas tersedia saat masuk bagian ini harus **Rp 383.000**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| KAS-01 | Buka Penerimaan kas. | Tampil tanggal T dan **Saldo kas tersedia Rp 383.000**. Ada kolom Kategori, Nasabah (opsional), Nominal, Keterangan, dan tabel riwayat (kosong). | | |
| KAS-02 | Buka pilihan Kategori. | Hanya akun pendapatan: Pendapatan administrasi, Pendapatan bunga pinjaman, Pendapatan penjualan buku tabungan. | | |
| KAS-03 | Pilih **Pendapatan penjualan buku tabungan**. | Nominal otomatis Rp 15.000 dan keterangan otomatis "Penjualan buku tabungan". | | |
| KAS-04 | Pilih nasabah `Budi Santoso`, klik **Simpan penerimaan**, konfirmasi. | "Penerimaan kas KM-<tahun>-000001 sebesar Rp 15.000 tercatat." Muncul di tabel; saldo kas tersedia Rp 398.000. | | |
| KAS-05 | Buka buku tabungan R1 Budi. | Saldo **tetap Rp 275.000**; penjualan buku tidak muncul di mutasi rekening. | | |
| KAS-06 | **Keterangan wajib:** pilih kategori Pendapatan administrasi, nominal `10.000`, keterangan kosong. | Tombol simpan tidak aktif, atau muncul pesan keterangan wajib diisi. | | |
| KAS-07 | Isi keterangan `Biaya cetak ulang kartu`, tanpa nasabah. Simpan dan konfirmasi. | "Penerimaan kas KM-<tahun>-000002 sebesar Rp 10.000 tercatat." Saldo kas tersedia Rp 408.000. | | |
| KAS-08 | Klik baris KM-…-000001 di tabel. | Halaman **Bukti Penerimaan kas** dengan kop (nama bank, sekolah, alamat), nomor, Tanggal, Diterima dari: Budi Santoso, Untuk pembayaran: Penjualan buku tabungan, Kategori, Jumlah Rp 15.000, terbilang, dan kolom tanda tangan. | | |
| KAS-09 | Klik **Cetak bukti**. | Pratinjau cetak hanya berisi bukti (tanpa menu dan tombol), muat di satu halaman. | | |
| KAS-10 | Sebagai teller, periksa halaman bukti. | Tombol **Batalkan** tidak tampil; **Lihat jurnal** juga tidak tampil. | | |
| KAS-11 | Di tabel riwayat, cari `cetak`, lalu filter kategori Pendapatan penjualan buku tabungan. | Pencarian: KM-…-000002. Filter: KM-…-000001. | | |

---

## 12. Pengeluaran kas

Login sebagai `teller`. Menu **Kas → Pengeluaran kas**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| KEL-01 | Buka Pengeluaran kas. | Saldo kas tersedia **Rp 408.000**. Tidak ada kolom nasabah. Kategori berisi akun beban: Beban bunga tabungan, Beban operasional, Beban perlengkapan & ATK. | | |
| KEL-02 | Kategori Beban perlengkapan & ATK, nominal `45.000`, keterangan `Beli kertas & tinta`. **Simpan pengeluaran**, konfirmasi. | "Pengeluaran kas KK-<tahun>-000001 sebesar Rp 45.000 tercatat." Saldo kas tersedia Rp 363.000. | | |
| KEL-03 | Kategori Beban operasional, nominal `20.000`, keterangan `Fotokopi formulir`. Simpan. | "Pengeluaran kas KK-<tahun>-000002 sebesar Rp 20.000 tercatat." Saldo kas tersedia Rp 343.000. | | |
| KEL-04 | **Melebihi saldo kas:** Beban operasional, nominal `99.999.999`, keterangan `Uji`. | "Saldo kas tidak cukup: tersedia Rp 343.000, dibutuhkan Rp 99.999.999." Tidak tercatat. | | |
| KEL-05 | Buka bukti KK-…-000001. | Judul **Bukti Pengeluaran kas**, baris "Dibayarkan untuk: Beli kertas & tinta", tanpa "Diterima dari". Tanda tangan Penerima dan Petugas. | | |

---

## 13. Pembatalan bukti kas

Login sebagai `bendahara`.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| BTL-01 | Buka bukti KM-…-000002 (Rp 10.000). | Ada tombol **Lihat jurnal**, **Batalkan**, dan **Cetak bukti**. | | |
| BTL-02 | Klik **Batalkan**, kosongkan alasan, lalu klik **Batalkan bukti**. | Ditolak: alasan wajib diisi. | | |
| BTL-03 | Isi alasan `Salah catat`, klik **Batalkan bukti**. | "KM-<tahun>-000002 dibatalkan." Bukti bertanda **DIBATALKAN** dengan keterangan "Dibatalkan oleh Bendahara pada … Alasan: Salah catat". Tombol Batalkan hilang. | | |
| BTL-04 | Batalkan KK-…-000002 (Rp 20.000) dengan alasan `Dibayar dari dana OSIS`. | "KK-<tahun>-000002 dibatalkan." | | |
| BTL-05 | Buka Penerimaan kas. | KM-…-000002 tercoret dengan tanda **Dibatalkan**. Total tidak menghitungnya (Total = Rp 15.000). Saldo kas tersedia **Rp 353.000**. | | |
| BTL-06 | Buka Pengeluaran kas. | KK-…-000002 tercoret, Total = Rp 45.000. | | |
| BTL-07 | **Batal dua kali:** buka lagi bukti KM-…-000002 dan KK-…-000002. | Tidak ada tombol Batalkan pada bukti yang sudah dibatalkan. | | |

---

## 14. Bagan akun

Login sebagai `bendahara`. Menu **Keuangan → Bagan akun**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| AKN-01 | Buka Bagan akun. | 12 akun dikelompokkan per kategori: Aset (1-101 Kas, 1-102 Bank, 1-201 Piutang pinjaman), Kewajiban (2-101), Ekuitas (3-101, 3-201), Pendapatan (4-101, 4-102, 4-103), Beban (5-101, 5-102, 5-103). Akun sistem bertanda **Sistem**. Kolom Dipakai pada Kas lebih dari 0. | | |
| AKN-02 | Periksa tombol pada 1-101 Kas. | Tidak ada tombol Nonaktifkan dan Hapus. | | |
| AKN-03 | Ubah 1-101 Kas: coba ganti kategori. | Pilihan kategori terkunci dengan penjelasan. Nama boleh diubah (jangan disimpan). | | |
| AKN-04 | **Tambah akun** kode `5-104`, nama `Beban konsumsi rapat`, kategori Beban. Simpan. | "Akun 5-104 Beban konsumsi rapat ditambahkan." | | |
| AKN-05 | Validasi: tambah akun dengan kode `ABC`, lalu kode `5-104`, lalu nama `Kas`. | Kode `ABC`: "Kode akun diawali angka dan hanya berisi angka, titik, atau tanda "-", mis. 5-103." Kode/nama duplikat: sudah digunakan. | | |
| AKN-06 | Buka Pengeluaran kas (sebagai bendahara). | 5-104 Beban konsumsi rapat muncul di pilihan Kategori. | | |
| AKN-07 | Kembali ke Bagan akun, **Nonaktifkan** 5-104. Buka lagi Pengeluaran kas. | "Akun 5-104 Beban konsumsi rapat dinonaktifkan." Akun tidak muncul lagi di Kategori. | | |
| AKN-08 | **Hapus** akun 5-104 (belum dipakai). | "Akun berhasil dihapus." | | |
| AKN-09 | Periksa 5-103 Beban perlengkapan & ATK (sudah dipakai di KK-…-000001). | Tidak ada tombol Hapus; kategori tidak bisa diubah. | | |

---

## 15. Jurnal umum

Login sebagai `bendahara`. Menu **Keuangan → Jurnal umum**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| JRN-01 | Buka Jurnal umum. | Daftar jurnal otomatis dari setoran, penarikan, biaya, penutupan, koreksi, penerimaan dan pengeluaran kas (termasuk pembatalannya), lengkap dengan nomor JU-<tahun>-…. | | |
| JRN-02 | Filter sumber **Transaksi tabungan & kas**, lalu **Jurnal umum**. | Filter pertama: semua jurnal otomatis. Filter kedua: kosong (belum ada jurnal manual). | | |
| JRN-03 | **Cocokkan pola jurnal otomatis.** Buka jurnal untuk masing-masing transaksi berikut dan periksa akun di sisi Debit dan Kredit:<br>a. Setoran Budi Rp 250.000<br>b. Penarikan Budi Rp 75.000<br>c. Biaya administrasi penutupan R3 Rp 5.000<br>d. Penutupan R3 Rp 15.000<br>e. Pembalik setoran Rp 10.000<br>f. KM-…-000001 Rp 15.000<br>g. KK-…-000001 Rp 45.000<br>h. Pembatalan KM-…-000002 | a. D Kas / K Simpanan tabungan nasabah<br>b. D Simpanan / K Kas<br>c. D Simpanan / K Pendapatan administrasi<br>d. D Simpanan / K Kas<br>e. D Simpanan / K Kas (kebalikan setoran)<br>f. D Kas / K Pendapatan penjualan buku tabungan<br>g. D Beban perlengkapan & ATK / K Kas<br>h. D Pendapatan administrasi / K Kas, keterangan "Pembatalan KM-…-000002: Salah catat" | | |
| JRN-04 | Buka jurnal otomatis setoran Budi. | Sumber tertaut ke rekening R1. **Tidak ada** tombol Koreksi. | | |
| JRN-05 | Buka jurnal KM-…-000001. | Sumber tertaut ke bukti kas KM-…-000001. Tidak ada tombol Koreksi. | | |
| JRN-06 | Klik **Catat jurnal**. Tanggal terisi T. Isi keterangan `Setoran modal awal`, baris 1: Kas debit `500.000`; baris 2: Modal kredit `400.000`. | Tampil "Selisih Rp 100.000"; tombol **Simpan jurnal** tidak aktif. | | |
| JRN-07 | Ubah kredit Modal menjadi `500.000`. | Tampil **Seimbang**; tombol aktif. | | |
| JRN-08 | Isi debit pada baris Modal. | Kolom kredit di baris yang sama otomatis kosong (satu baris hanya debit atau kredit). Kembalikan: Modal kredit `500.000`. | | |
| JRN-09 | Klik **Tambah baris**, biarkan baris ke-3 tanpa akun dan nominal, lalu simpan. | Ditolak pada baris ke-3 (akun wajib dipilih / isi debit atau kredit). Klik **Hapus baris** pada baris ke-3. | | |
| JRN-10 | Coba hapus baris sampai tersisa 2. | Tombol Hapus baris tidak aktif saat tinggal 2 baris. | | |
| JRN-11 | Simpan jurnal setoran modal. | "Jurnal JU-<tahun>-… tersimpan." Halaman detail menampilkan D Kas Rp 500.000 / K Modal Rp 500.000, sumber Jurnal umum, dicatat oleh Bendahara. | | |
| JRN-12 | **Jurnal salah lalu dikoreksi:** catat jurnal `Pindah kas ke bank`, D Bank `100.000` / K Kas `100.000`. Simpan. Lalu klik **Koreksi**, alasan `Belum disetor ke bank`, klik **Buat jurnal pembalik**. | "Jurnal JU-… dikoreksi dengan JU-…." Jurnal baru: D Kas 100.000 / K Bank 100.000, keterangan "Koreksi JU-…: Belum disetor ke bank". Di daftar jurnal, jurnal asal bertanda **Dikoreksi**. | | |
| JRN-13 | Buka jurnal asal dan jurnal pembalik dari JRN-12. | Keduanya **tidak** punya tombol Koreksi lagi. | | |
| JRN-14 | Cari `modal` di daftar jurnal. | Muncul jurnal Setoran modal awal. | | |

---

## 16. Laporan keuangan

Login sebagai `teller`. Menu **Laporan → Laporan keuangan**. Periode bawaan: tanggal 1 bulan ini s.d. T.

**Angka yang diharapkan setelah bagian 1–15**

| Pos | Nilai |
| --- | --- |
| Kas (1-101) | Rp 853.000 |
| Bank (1-102) | Rp 0 |
| Simpanan tabungan nasabah (2-101) | Rp 375.000 |
| Modal (3-101) | Rp 500.000 |
| Pendapatan administrasi (4-101) | Rp 8.000 |
| Pendapatan penjualan buku tabungan (4-103) | Rp 15.000 |
| Beban perlengkapan & ATK (5-103) | Rp 45.000 |
| Beban operasional (5-102) | Rp 0 |
| **Rugi bersih** | **Rp 22.000** |

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| LAP-01 | Buka halaman Laporan keuangan. | Kartu: Saldo kas **Rp 853.000**, Total aset **Rp 853.000** (tanpa tanda "Neraca tidak seimbang"), Kewajiban **Rp 375.000**, Laba (rugi) periode **-Rp 22.000** (rugi). Ada tautan ke 5 laporan. | | |
| LAP-02 | **Neraca.** | Aset: Kas 853.000 (Bank 0), Total aset 853.000. Kewajiban: Simpanan tabungan nasabah 375.000. Ekuitas: Modal 500.000, Laba berjalan -22.000, Total ekuitas 478.000. Total kewajiban & ekuitas **853.000 = Total aset**. Hanya ada filter "Per tanggal". | | |
| LAP-03 | **Laba rugi.** | Pendapatan administrasi 8.000, Pendapatan penjualan buku tabungan 15.000, Total pendapatan 23.000. Beban perlengkapan & ATK 45.000, Total beban 45.000. **Rugi bersih 22.000**. | | |
| LAP-04 | **Arus kas.** | Saldo kas awal periode 0. Total penerimaan **1.345.000**, Total pengeluaran **492.000**, Kenaikan kas 853.000, Saldo kas akhir periode **853.000**. Pemindahan kas ke bank dan koreksinya (JRN-12) tidak ikut dihitung. | | |
| LAP-05 | **Neraca saldo.** | Debit: Kas 853.000, Beban perlengkapan & ATK 45.000 → total **898.000**. Kredit: Simpanan 375.000, Modal 500.000, Pendapatan administrasi 8.000, Pendapatan penjualan buku tabungan 15.000 → total **898.000**. Seimbang. | | |
| LAP-06 | **Buku besar**, akun Kas (bawaan). | Daftar semua jurnal yang menyentuh Kas berurutan, dengan saldo berjalan. Saldo akhir **853.000**. | | |
| LAP-07 | Buku besar, ganti akun ke **Simpanan tabungan nasabah**. | Saldo akhir **375.000**. | | |
| LAP-08 | Buku besar, akun **Bank**. | Debit 100.000 dan kredit 100.000 (JRN-12), saldo akhir 0. | | |
| LAP-09 | **Periode kosong:** Laba rugi dengan periode bulan lalu (tanggal 1 s.d. akhir bulan lalu). | Semua angka 0. | | |
| LAP-10 | **Tanggal terbalik:** isi Dari = T, Sampai = tanggal 1 bulan ini (lebih awal). | Laporan tetap tampil; periode otomatis ditukar dan angka sama dengan LAP-03. | | |
| LAP-11 | **Excel:** di Neraca klik **Excel**. Ulangi di Laba rugi. | Terunduh `neraca_<T>.xlsx` dan `laba_rugi_<tgl1>_<T>.xlsx`. Dibuka di Excel: ada kop (nama bank, sekolah), judul, periode, angka sama dengan layar, angka negatif berwarna merah. | | |
| LAP-12 | **Cetak:** di Neraca klik **Cetak** (coba juga saat mode gelap). | Pratinjau cetak berlatar putih, berisi kop, judul, "Per <tanggal>", tabel, dan tanda tangan "Mengetahui, Kepala Sekolah" (nama kepala sekolah dari Pengaturan) serta "Bendahara", dengan "Semarang, <tanggal>". | | |

---

## 17. Dashboard

Login sebagai `teller`. Menu **Dashboard**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| DSB-01 | Buka Dashboard. | Subjudul "Ringkasan tanggal transaksi <T>". | | |
| DSB-02 | Periksa kartu **Saldo kas hari ini**. | **Rp 1.353.000**, dengan catatan Modal sesi Rp 1.000.000 + mutasi kas Rp 353.000 (termasuk kas lain-lain -Rp 30.000). Jurnal setoran modal (JRN-11) **tidak** dihitung di sini. | | |
| DSB-03 | Kartu **Setoran hari ini**. | **Rp 800.000**, 9 transaksi. | | |
| DSB-04 | Kartu **Penarikan hari ini**. | **Rp 407.000**, 5 transaksi (termasuk penutupan R3 Rp 15.000). | | |
| DSB-05 | Kartu **Total saldo tabungan**. | **Rp 375.000**, 3 rekening aktif. | | |
| DSB-06 | Kartu **Nasabah aktif**. | **4** (Budi, Citra, Dedi, Eko), atau **5** bila NSB-12 dikerjakan. | | |
| DSB-07 | Klik kartu Saldo kas hari ini, Total saldo tabungan, lalu Nasabah aktif. | Membuka halaman Sesi kas, daftar Rekening, dan daftar Nasabah. | | |
| DSB-08 | **Ganti tanggal:** Pengaturan → Tanggal transaksi aktif = besok (T+1). Simpan, lalu buka Dashboard. | Tanggal di bagian atas berubah dan tanda menjadi **Sesi kas belum dibuka**. Setoran dan Penarikan hari ini Rp 0; Saldo kas hari ini Rp 0 dengan catatan "Sesi kas belum dibuka"; Total saldo tabungan tetap Rp 375.000. | | |
| DSB-08a | Masih T+1, buka **Kas → Sesi kas**. | Yang tampil adalah sesi tanggal **T** (masih terbuka) dengan peringatan bahwa sesi ini belum ditutup sedangkan tanggal transaksi sudah berganti. Form buka sesi baru **tidak** tampil. Jangan tutup sesi dulu. | | |
| DSB-09 | Masih T+1, sebagai teller setor R2 `5.000`. Lalu login `bendahara` dan setor R2 `5.000`. | Keduanya ditolak: "Sesi kas tanggal <T+1> belum dibuka. Buka sesi kas terlebih dahulu." Saldo R2 tetap Rp 50.000; Dashboard: Setoran hari ini Rp 0. | | |
| DSB-10 | Kembalikan Tanggal transaksi aktif ke **T**. | Tanda kembali **Sesi kas terbuka**. Dashboard kembali ke angka DSB-02 s.d. DSB-05. | | |

---

## 18. Tutup sesi kas & laporan kas harian

Login sebagai `teller`. Pastikan Tanggal transaksi aktif sudah dikembalikan ke **T** (DSB-10).

**Rekap uang fisik tanggal T yang diharapkan**

| Pos | Jumlah transaksi | Nilai |
| --- | --- | --- |
| Modal awal | | Rp 1.000.000 |
| Pemasukan: Setoran tabungan (termasuk setoran awal) | 9 | Rp 800.000 |
| Pemasukan: 4-103 Pendapatan penjualan buku tabungan | 1 | Rp 15.000 |
| Pemasukan: 4-101 Pendapatan administrasi | 1 | Rp 10.000 |
| Pemasukan: Koreksi & pembatalan (batal KK-…-000002) | 1 | Rp 20.000 |
| **Total pemasukan** | | **Rp 845.000** |
| Pengeluaran: Penarikan tabungan | 4 | Rp 392.000 |
| Pengeluaran: Penutupan rekening | 1 | Rp 15.000 |
| Pengeluaran: 5-103 Beban perlengkapan & ATK | 1 | Rp 45.000 |
| Pengeluaran: 5-102 Beban operasional | 1 | Rp 20.000 |
| Pengeluaran: Koreksi & pembatalan (koreksi setoran R1, batal KM-…-000002) | 2 | Rp 20.000 |
| **Total pengeluaran** | | **Rp 492.000** |
| **Saldo kas akhir** | | **Rp 1.353.000** (sama dengan DSB-02) |

Biaya administrasi penutupan (Rp 5.000 dan Rp 3.000) tidak dihitung karena tidak ada uang fisik yang berpindah.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| TSK-01 | Buka Kas → Sesi kas. | Sesi tanggal T, **Terbuka**. Pemasukan Rp 845.000, Pengeluaran Rp 492.000, **Saldo kas seharusnya Rp 1.353.000**. | | |
| TSK-02 | Klik **Laporan kas harian** (atau menu Laporan → Laporan kas harian). | Tanggal T, tanda **Sesi terbuka**. Isi sama dengan tabel rekap di atas. Rincian transaksi berisi semua transaksi tanggal T beserta jam, jenis, nomor rekening/bukti, nasabah, masuk/keluar, dan petugas. Tidak ada baris biaya administrasi. | | |
| TSK-03 | Kembali ke Sesi kas. Isi Uang fisik di laci `1.353.000`. | Tanda **Cocok**. | | |
| TSK-04 | Ubah uang fisik menjadi `1.350.000`. | Tanda **Kurang**, "Selisih -Rp 3.000". Tombol **Tutup sesi** tidak aktif selama keterangan kosong. | | |
| TSK-05 | Isi keterangan `Uang kembalian kurang`, klik **Tutup sesi**, konfirmasi. | Konfirmasi menyebut saldo seharusnya Rp 1.353.000 dan uang fisik Rp 1.350.000. Lalu: "Sesi kas <T> ditutup. Uang fisik kurang Rp 3.000." Tanda **Ditutup**; ringkasan menampilkan uang fisik, selisih -Rp 3.000, dan keterangan. Bagian atas: **Sesi kas ditutup**. | | |
| TSK-06 | **Transaksi setelah tutup:** coba setor R1 `1.000`, lalu catat penerimaan kas apa saja. | Keduanya ditolak: "Sesi kas tanggal <T> sudah ditutup." | | |
| TSK-07 | Buka Laporan kas harian tanggal T. | Tanda **Sesi ditutup**. Tampil "Uang fisik saat sesi ditutup Rp 1.350.000", "Selisih -Rp 3.000" (merah), dan keterangan. Dibuka/ditutup oleh Teller beserta jamnya. | | |
| TSK-08 | Klik **Cetak**, lalu **Excel**. | Cetak: kop, judul "Laporan kas harian", tanggal, tabel, rincian, dan tanda tangan Bendahara & Teller. Excel: terunduh `kas_harian_<T>.xlsx` dengan angka yang sama. | | |
| TSK-09 | Di Laporan kas harian, pilih tanggal **T+1**. | Peringatan "Sesi kas tanggal ini belum dibuka, sehingga modal awal dihitung Rp 0." Pemasukan dan pengeluaran "Tidak ada", saldo kas akhir Rp 0 (transaksi DSB-09 ditolak). | | |
| TSK-10 | **Sesi hari berikutnya:** Pengaturan → Tanggal transaksi aktif = T+1. Buka Sesi kas. | Form buka sesi tampil dengan Modal awal otomatis **Rp 1.350.000** (uang fisik saat sesi T ditutup). Klik **Buka sesi** → sesi T+1 terbuka. Riwayat sesi berisi dua baris. | | |

---

## 19. Log aktivitas

Login sebagai `bendahara`. Menu **Sistem → Log aktivitas**.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| LOG-01 | Buka Log aktivitas. | Daftar terbaru di atas, kolom Waktu, Pengguna, Kategori, Aktivitas. | | |
| LOG-02 | Filter kategori **auth**. | Ada catatan login, logout, dan ganti kata sandi (AUTH-15). | | |
| LOG-03 | Filter kategori **pengguna**. | Ada pembuatan `admin1` dan `teller2`, perubahan nama, reset kata sandi, perubahan peran, nonaktif/aktif. | | |
| LOG-04 | Klik/expand catatan perubahan nama `teller2`. | Tampil nilai lama "Teller Dua" dan nilai baru "Teller Kedua". | | |
| LOG-05 | Filter kategori **pengaturan**. | Ada perubahan tanggal transaksi, biaya, dll. dengan nilai lama dan baru. | | |
| LOG-05a | Filter kategori **sesi_kas**. | Ada pembukaan sesi (modal awal) dan penutupan sesi (saldo sistem, uang fisik, selisih, keterangan). | | |
| LOG-06 | Filter pengguna **Teller** dan kategori **nasabah**. | Pembuatan dan perubahan nasabah oleh teller. | | |
| LOG-07 | Filter tanggal Dari = Sampai = kemarin. Lalu klik **Reset**. | Kosong (semua aktivitas hari ini). Reset menampilkan semua lagi. | | |

---

## 20. Hak akses per peran

Login bergantian sebagai `teller`, `admin1`, dan `bendahara`. Beri ✅ bila sesuai tabel.

### 20.1 Menu yang tampil

| Menu | teller | admin1 | bendahara |
| --- | --- | --- | --- |
| Dashboard | tampil | tampil | tampil |
| Data Master → Nasabah | tampil | **tidak** | tampil |
| Data Master → Jenis Rekening | **tidak** | tampil | tampil |
| Tabungan → Setor / Tarik | tampil | **tidak** | tampil |
| Tabungan → Rekening | tampil | **tidak** | tampil |
| Kas → Sesi kas | tampil | **tidak** | tampil |
| Kas → Penerimaan kas / Pengeluaran kas | tampil | **tidak** | tampil |
| Keuangan → Pinjaman | **tidak** | **tidak** | tampil |
| Keuangan → Jurnal umum / Bagan akun | **tidak** | **tidak** | tampil |
| Laporan → Laporan kas harian | tampil | **tidak** | tampil |
| Laporan → Laporan keuangan | tampil | **tidak** | tampil |
| Sistem → Pengaturan | tampil | tampil | tampil |
| Sistem → Pengguna | **tidak** | tampil | tampil |
| Sistem → Log aktivitas | **tidak** | **tidak** | tampil |

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| HAK-01 | Login `teller`, cocokkan menu dengan tabel 20.1. | Sesuai kolom teller. | | |
| HAK-02 | Login `admin1` (`Rahasia123`), cocokkan menu. | Sesuai kolom admin1. | | |
| HAK-03 | Login `bendahara`, cocokkan menu. Buka **Pinjaman**. | Sesuai kolom bendahara. Pinjaman menampilkan halaman segera hadir. | | |
| HAK-04 | Sebagai `teller`, ketik langsung di alamat browser: `/jurnal`, `/akun`, `/pengguna`, `/log-aktivitas`, `/jenis-rekening`, `/pinjaman`. | Setiap alamat menampilkan halaman **403 / tidak punya akses**, bukan isi halaman. | | |
| HAK-05 | Sebagai `admin1`, ketik `/rekening`, `/nasabah`, `/transaksi`, `/kas/penerimaan`, `/kas/sesi`, `/laporan`, `/laporan/harian`. | Semua 403. Tanda sesi kas tidak tampil di bagian atas. |  | | |
| HAK-06 | Sebagai `teller`, buka Pengaturan. | Kartu **Operasional harian** bisa diubah. Kartu **Keuangan** dan **Umum** bertanda **Hanya lihat**, isian abu-abu, tanpa tombol Simpan. | | |
| HAK-07 | Sebagai `admin1`, buka Pengaturan. | Kartu **Umum** bisa diubah; **Operasional harian** dan **Keuangan** hanya lihat. | | |
| HAK-08 | Sebagai `admin1`, buka Jenis Rekening dan Pengguna. Tambah, ubah, reset kata sandi tersedia. | Bisa dipakai seperti bendahara. | | |
| HAK-09 | Sebagai `teller`, buka detail nasabah Budi dan bukti kas KM-…-000001. | Tidak ada tombol Nonaktifkan/Hapus nasabah; tidak ada tombol Batalkan dan Lihat jurnal pada bukti. | | |
| HAK-10 | Sebagai `admin1`, buka Dashboard. | Kartu angka tampil, tetapi kartu Total saldo tabungan dan Nasabah aktif tidak bisa diklik. | | |

---

## 21. Uji umum & ketahanan

> **Perhatian:** skenario UMM-05 dan UMM-06 menambah transaksi, sehingga angka di bagian 16, 17, dan 18 tidak berlaku lagi setelahnya. Keduanya dikerjakan pada tanggal T+1 dengan sesi kas yang dibuka di TSK-10.

| ID | Skenario & langkah | Hasil yang diharapkan | Hasil | Catatan |
| --- | --- | --- | --- | --- |
| UMM-01 | Ketik alamat yang tidak ada, mis. `/tidak-ada`. Juga `/laporan/tidak-ada`. | Halaman **404** yang rapi dengan tombol **Kembali ke dashboard**. | | |
| UMM-02 | **Sesi kedaluwarsa:** buka form Setor, biarkan, lalu di tab lain klik Keluar dan login lagi. Kembali ke tab pertama dan klik simpan. | Muncul "Sesi telah berakhir, silakan coba lagi." atau diminta login; tidak ada transaksi ganda/aneh. | | |
| UMM-03 | **Tampilan HP:** buka aplikasi di HP (atau perkecil jendela browser selebar HP). Coba Dashboard, Setor / Tarik, daftar Nasabah, buku tabungan, Laporan. | Menu bisa dibuka lewat tombol menu, tidak ada teks terpotong, tabel bisa digeser, tombol bisa ditekan. | | |
| UMM-04 | **Mode gelap** di semua halaman di atas. | Tulisan terbaca, tidak ada teks putih di atas putih atau hitam di atas hitam. | | |
| UMM-05 | **Klik ganda:** setor R1 `1.000`, lalu klik tombol konfirmasi dua kali dengan cepat. | Hanya **satu** setoran Rp 1.000 tercatat di buku tabungan R1. | | |
| UMM-06 | **Dua teller bersamaan:** `teller` (browser 1) dan `teller2` (browser 2) menyiapkan penarikan R2 (saldo Rp 50.000) masing-masing `40.000` sampai dialog konfirmasi, lalu konfirmasi hampir bersamaan. | Hanya **satu** penarikan berhasil. Yang lain: "Saldo tidak cukup. Saldo Rp 10.000, penarikan Rp 40.000." Saldo R2 tidak pernah minus. | | |
| UMM-07 | **Nominal besar & desimal:** setor R1 `1.500,50`. | Diterima, saldo dan terbilang menampilkan desimal dengan benar (koma), atau isian tidak mengizinkan desimal; catat perilakunya. | | |
| UMM-08 | Muat ulang (F5) di tengah halaman mana pun. | Halaman tampil kembali normal, tetap login. | | |

---

## 22. Ringkasan hasil

| Bagian | Jumlah skenario | ✅ | ❌ | ⏭️ |
| --- | --- | --- | --- | --- |
| 1. Login & kata sandi | 18 | | | |
| 2. Pengaturan | 7 | | | |
| 3. Pengguna | 15 | | | |
| 4. Jenis rekening | 10 | | | |
| 5. Nasabah | 19 | | | |
| 6. Sesi kas (buka) | 6 | | | |
| 7. Buka rekening | 17 | | | |
| 8. Setor & tarik | 14 | | | |
| 9. Koreksi transaksi | 6 | | | |
| 10. Tutup rekening | 7 | | | |
| 11. Penerimaan kas | 11 | | | |
| 12. Pengeluaran kas | 5 | | | |
| 13. Pembatalan bukti kas | 7 | | | |
| 14. Bagan akun | 9 | | | |
| 15. Jurnal umum | 14 | | | |
| 16. Laporan keuangan | 12 | | | |
| 17. Dashboard | 11 | | | |
| 18. Tutup sesi kas & laporan kas harian | 10 | | | |
| 19. Log aktivitas | 8 | | | |
| 20. Hak akses | 10 | | | |
| 21. Uji umum & ketahanan | 8 | | | |
| **Total** | **224** | | | |

**Daftar bug ditemukan**

| No | ID skenario | Ringkasan masalah | Tingkat (tinggi/sedang/rendah) | Screenshot |
| --- | --- | --- | --- | --- |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

**Pengesahan**

| | Nama | Tanggal | Tanda tangan |
| --- | --- | --- | --- |
| Tester | | | |
| Bendahara | | | |
| Developer | | | |
