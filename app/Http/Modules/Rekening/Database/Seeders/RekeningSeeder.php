<?php

namespace App\Http\Modules\Rekening\Database\Seeders;

use App\Http\Modules\Rekening\Models\JenisRekening;
use Illuminate\Database\Seeder;

/**
 * Jenis rekening tabungan bawaan. Aman dijalankan ulang: baris dicari per kode lalu
 * diperbarui. Kode "tabungan" (dibuat migration) tetap rekening utama tanpa prefix.
 */
class RekeningSeeder extends Seeder
{
    /**
     * [kode, nama, prefix, keterangan]
     */
    public const JENIS = [
        ['tabungan', 'Tabungan Pelajar', null, 'Rekening utama; nomor rekening = NISN/NIS siswa.'],
        ['wisata', 'Tabungan Wisata', '41', 'Tabungan untuk kegiatan wisata/study tour.'],
        ['wisuda', 'Tabungan Wisuda', '42', 'Tabungan untuk biaya wisuda.'],
        ['foto_katalog', 'Tabungan Foto Katalog', '43', 'Tabungan untuk foto buku katalog/tahunan.'],
    ];

    public function run(): void
    {
        foreach (self::JENIS as [$kode, $nama, $prefix, $keterangan]) {
            JenisRekening::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'prefix' => $prefix, 'is_active' => true, 'keterangan' => $keterangan],
            );
        }
    }
}
