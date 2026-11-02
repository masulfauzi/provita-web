<?php

namespace App\Http\Modules\Pengaturan\Database\Seeders;

use App\Http\Modules\Pengaturan\Models\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Daftar pengaturan awal. Nilai yang sudah ada tidak ditimpa, sehingga
 * seeder aman dijalankan ulang.
 */
class PengaturanSeeder extends Seeder
{
    /**
     * @var list<array{kunci: string, label: string, tipe: string, grup: string, nilai: string|null, keterangan?: string}>
     */
    public const DEFAULTS = [
        ['kunci' => 'tanggal_transaksi', 'label' => 'Tanggal transaksi aktif', 'tipe' => 'tanggal', 'grup' => 'harian', 'nilai' => null, 'keterangan' => 'Tanggal yang dipakai untuk semua transaksi. Kosongkan agar otomatis mengikuti tanggal hari ini.'],
        ['kunci' => 'bunga_minimal', 'label' => 'Bunga minimal', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00'],
        ['kunci' => 'suku_bunga_tabungan', 'label' => 'Suku bunga tabungan per tahun', 'tipe' => 'persen', 'grup' => 'keuangan', 'nilai' => '0'],
        ['kunci' => 'setoran_awal_minimal', 'label' => 'Setoran awal minimal', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00'],
        ['kunci' => 'adm_penutupan', 'label' => 'Biaya administrasi penutupan', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00'],
        ['kunci' => 'harga_buku_tabungan', 'label' => 'Harga buku tabungan', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00', 'keterangan' => 'Nominal default saat mencatat penjualan buku tabungan di Penerimaan kas.'],
        ['kunci' => 'plafon_pinjaman', 'label' => 'Plafon pinjaman', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '10000000.00', 'keterangan' => 'Batas maksimal satu pinjaman guru/karyawan. Isi 0 bila tanpa batas.'],
        ['kunci' => 'jasa_pinjaman_persen', 'label' => 'Jasa pinjaman per bulan', 'tipe' => 'persen', 'grup' => 'keuangan', 'nilai' => '1', 'keterangan' => 'Jasa flat per bulan dari pokok pinjaman. Pinjaman yang sudah berjalan tidak ikut berubah.'],
        ['kunci' => 'tenor_maksimal_pinjaman', 'label' => 'Jangka waktu maksimal pinjaman (bulan)', 'tipe' => 'angka', 'grup' => 'keuangan', 'nilai' => '24'],
        ['kunci' => 'nama_bank', 'label' => 'Nama bank', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => 'Bank Provita'],
        ['kunci' => 'nama_sekolah', 'label' => 'Nama sekolah', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => 'SMK Negeri 2 Semarang'],
        ['kunci' => 'alamat_sekolah', 'label' => 'Alamat sekolah', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => null],
        ['kunci' => 'nama_kepala_sekolah', 'label' => 'Nama kepala sekolah', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => null],
        // Tata letak cetak buku tabungan (juga dibuat oleh migration modul Rekening).
        ['kunci' => 'buku_baris_per_halaman', 'label' => 'Baris per halaman buku tabungan', 'tipe' => 'angka', 'grup' => 'umum', 'nilai' => '20', 'keterangan' => 'Jumlah baris mutasi pada satu halaman buku tabungan.'],
        ['kunci' => 'buku_jarak_atas_mm', 'label' => 'Jarak atas cetak buku (mm)', 'tipe' => 'angka', 'grup' => 'umum', 'nilai' => '20', 'keterangan' => 'Jarak baris pertama dari tepi atas halaman.'],
        ['kunci' => 'buku_jarak_kiri_mm', 'label' => 'Jarak kiri cetak buku (mm)', 'tipe' => 'angka', 'grup' => 'umum', 'nilai' => '10', 'keterangan' => 'Jarak kolom No dari tepi kiri halaman.'],
        ['kunci' => 'buku_tinggi_baris_mm', 'label' => 'Tinggi baris cetak buku (mm)', 'tipe' => 'angka', 'grup' => 'umum', 'nilai' => '6', 'keterangan' => 'Jarak antarbaris pada buku tabungan.'],
        ['kunci' => 'buku_lebar_kolom_mm', 'label' => 'Lebar kolom cetak buku (mm)', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => '10,20,10,30,30,32,14', 'keterangan' => 'Lebar kolom No, Tanggal, Sandi, Debit, Kredit, Saldo, Paraf; dipisah koma.'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $urutan => $pengaturan) {
            Pengaturan::firstOrCreate(
                ['kunci' => $pengaturan['kunci']],
                [...$pengaturan, 'urutan' => $urutan],
            );
        }
    }
}
