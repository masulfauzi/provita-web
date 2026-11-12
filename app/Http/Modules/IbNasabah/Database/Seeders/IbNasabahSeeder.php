<?php

namespace App\Http\Modules\IbNasabah\Database\Seeders;

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Nasabah\Services\NasabahService;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Nasabah Internet Banking contoh untuk pengembangan lokal:
 *  - NIS 25039 (NISN 0090025039, rekening 25039): BELUM aktivasi, untuk mencoba alur aktivasi.
 *  - NIS 25040: sudah aktivasi, login 081234567891 / Provita2026.
 *  - NIS 25041: sudah aktivasi, login 081234567890 / Provita2026.
 * Hanya berjalan di environment local; aman dijalankan ulang (dilewati bila NIS sudah ada).
 */
class IbNasabahSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $contoh = [
            ['25039', 'Ayu Lestari', 'X AKL 1', 'P', null],
            ['25040', 'Bagas Pratama', 'XI PPLG 2', 'L', '081234567891'],
            ['25041', 'Rizky Ramadhan', 'XII TJKT 3', 'L', '081234567890'],
        ];

        $pengaturan = app(PengaturanService::class);
        $tanggalAsli = $pengaturan->get('tanggal_transaksi');

        foreach ($contoh as [$nis, $nama, $kelas, $jk, $noHp]) {
            if (Nasabah::query()->where('nis', $nis)->exists()) {
                continue;
            }

            DB::transaction(function () use ($nis, $nama, $kelas, $jk, $noHp, $pengaturan) {
                $nasabah = new Nasabah([
                    'nis' => $nis,
                    'nisn' => '00900'.$nis,
                    'nama' => $nama,
                    'jenis' => 'siswa',
                    'jenis_kelamin' => $jk,
                    'kelas' => $kelas,
                    'is_active' => true,
                    ...($noHp ? [
                        'tempat_lahir' => 'Semarang',
                        'tanggal_lahir' => '2008-03-14',
                        'alamat_jalan' => 'Jl. Sidodadi Barat No. 8',
                        'rt' => '003',
                        'rw' => '005',
                        'kelurahan' => 'Karangkidul',
                        'kecamatan' => 'Semarang Tengah',
                        'kota' => 'Kota Semarang',
                        'kode_pos' => '50136',
                        'no_hp' => $noHp,
                        'nama_orang_tua' => 'Slamet Riyadi',
                        'wali_hubungan' => 'ayah',
                        'wali_hp' => '081298765432',
                    ] : []),
                ]);
                $nasabah->alamat = Nasabah::susunAlamat($nasabah->getAttributes());
                $nasabah->nomor = app(NasabahService::class)->nomorBerikutnya();
                $nasabah->save();

                if ($noHp) {
                    $nasabah->akunIb()->create([
                        'no_hp' => $noHp,
                        'password' => 'Provita2026',
                        'diaktivasi_at' => now()->subDays(30),
                        'password_diganti_at' => now()->subDays(30),
                        'is_active' => true,
                    ]);
                }

                $rekening = $nasabah->rekening()->create([
                    'nomor' => $nis,
                    'jenis_rekening_id' => JenisRekening::kode(JenisRekening::TABUNGAN)->id,
                    'saldo_awal' => '0.00',
                    'status' => Rekening::AKTIF,
                    'tanggal_buka' => today()->subDays(70),
                ]);

                // Mutasi contoh tersebar selama ~2 bulan (tanpa user = transaksi sistem). Service
                // di-resolve ulang agar membaca tanggal transaksi yang baru diset.
                foreach ([[70, 'setor', 250000], [55, 'setor', 100000], [41, 'tarik', 50000], [30, 'setor', 75000], [14, 'tarik', 25000], [6, 'setor', 150000], [2, 'setor', 20000]] as [$hari, $jenis, $nominal]) {
                    $pengaturan->set('tanggal_transaksi', today()->subDays($hari)->toDateString());
                    app(TransaksiService::class)->{$jenis}($rekening, $nominal, null, $jenis === 'setor' ? 'Setoran tunai' : 'Penarikan tunai');
                }
            });
        }

        $pengaturan->set('tanggal_transaksi', $tanggalAsli);
    }
}
