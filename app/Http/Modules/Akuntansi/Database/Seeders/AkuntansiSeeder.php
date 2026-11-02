<?php

namespace App\Http\Modules\Akuntansi\Database\Seeders;

use App\Http\Modules\Akuntansi\Models\Akun;
use Illuminate\Database\Seeder;

/**
 * Bagan akun bawaan. Akun ber-"peran" wajib ada karena dipakai jurnal otomatis
 * (setoran, penarikan, biaya, kas, laporan). Aman dijalankan ulang: dicari per kode.
 */
class AkuntansiSeeder extends Seeder
{
    /**
     * [kode, nama, kategori, peran]
     */
    public const AKUN = [
        ['1-101', 'Kas', 'aset', Akun::KAS],
        ['1-102', 'Bank', 'aset', Akun::BANK],
        ['1-201', 'Piutang pinjaman', 'aset', Akun::PIUTANG_PINJAMAN],
        ['2-101', 'Simpanan tabungan nasabah', 'kewajiban', Akun::SIMPANAN],
        ['3-101', 'Modal', 'ekuitas', Akun::MODAL],
        ['3-201', 'Laba ditahan', 'ekuitas', 'laba_ditahan'],
        ['4-101', 'Pendapatan administrasi', 'pendapatan', Akun::PENDAPATAN_ADMINISTRASI],
        ['4-102', 'Pendapatan jasa pinjaman', 'pendapatan', Akun::PENDAPATAN_JASA_PINJAMAN],
        ['4-103', 'Pendapatan penjualan buku tabungan', 'pendapatan', 'pendapatan_buku_tabungan'],
        ['5-101', 'Beban bunga tabungan', 'beban', null],
        ['5-102', 'Beban operasional', 'beban', null],
        ['5-103', 'Beban perlengkapan & ATK', 'beban', null],
    ];

    public function run(): void
    {
        foreach (self::AKUN as [$kode, $nama, $kategori, $peran]) {
            // peran tidak fillable (hanya diatur sistem), jadi diisi dengan forceFill.
            Akun::query()->firstOrNew(['kode' => $kode])
                ->forceFill(['nama' => $nama, 'kategori' => $kategori, 'peran' => $peran, 'is_active' => true])
                ->save();
        }
    }
}
