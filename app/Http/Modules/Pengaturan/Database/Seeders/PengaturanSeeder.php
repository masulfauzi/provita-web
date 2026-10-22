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
        ['kunci' => 'tanggal_transaksi', 'label' => 'Tanggal transaksi aktif', 'tipe' => 'tanggal', 'grup' => 'harian', 'nilai' => null, 'keterangan' => 'Tanggal yang dipakai untuk semua transaksi hari ini.'],
        ['kunci' => 'uang_modal', 'label' => 'Uang modal', 'tipe' => 'uang', 'grup' => 'harian', 'nilai' => '0.00', 'keterangan' => 'Uang kas awal yang diterima teller saat membuka sesi.'],
        ['kunci' => 'bunga_minimal', 'label' => 'Bunga minimal', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00'],
        ['kunci' => 'suku_bunga_tabungan', 'label' => 'Suku bunga tabungan per tahun', 'tipe' => 'persen', 'grup' => 'keuangan', 'nilai' => '0'],
        ['kunci' => 'setoran_awal_minimal', 'label' => 'Setoran awal minimal', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00'],
        ['kunci' => 'adm_penutupan', 'label' => 'Biaya administrasi penutupan', 'tipe' => 'uang', 'grup' => 'keuangan', 'nilai' => '0.00'],
        ['kunci' => 'nama_bank', 'label' => 'Nama bank', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => 'Bank Provita'],
        ['kunci' => 'nama_sekolah', 'label' => 'Nama sekolah', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => 'SMK Negeri 2 Semarang'],
        ['kunci' => 'alamat_sekolah', 'label' => 'Alamat sekolah', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => null],
        ['kunci' => 'nama_kepala_sekolah', 'label' => 'Nama kepala sekolah', 'tipe' => 'teks', 'grup' => 'umum', 'nilai' => null],
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
