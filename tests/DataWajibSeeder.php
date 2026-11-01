<?php

namespace Tests;

use App\Http\Modules\Akuntansi\Database\Seeders\AkuntansiSeeder;
use App\Http\Modules\Rekening\Database\Seeders\RekeningSeeder;
use App\Http\Modules\Rekening\Models\JenisRekening;
use Illuminate\Database\Seeder;

/**
 * Data wajib untuk setiap test: bagan akun bawaan dan jenis rekening utama (Tabungan
 * Pelajar). Jenis lain (Wisata, dll.) sengaja tidak dibuat agar test bebas membuat
 * jenis sendiri tanpa bentrok kode/prefix.
 */
class DataWajibSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AkuntansiSeeder::class);

        [$kode, $nama, $prefix, $keterangan] = RekeningSeeder::JENIS[0];
        JenisRekening::query()->updateOrCreate(['kode' => $kode], ['nama' => $nama, 'prefix' => $prefix, 'is_active' => true, 'keterangan' => $keterangan]);
    }
}
