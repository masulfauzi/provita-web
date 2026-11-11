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
 * Akun Internet Banking contoh untuk pengembangan lokal, sama dengan mock frontend provita-ib:
 *  - 25039 / rahasia123  : wajib ganti password
 *  - 25040 / Provita2026 : password sudah diganti, data pribadi belum lengkap
 *  - 25041 / Provita2026 : lengkap
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
            ['25039', 'Ayu Lestari', 'X AKL 1', 'P', 'rahasia123', true, false],
            ['25040', 'Bagas Pratama', 'XI PPLG 2', 'L', 'Provita2026', false, false],
            ['25041', 'Rizky Ramadhan', 'XII TJKT 3', 'L', 'Provita2026', false, true],
        ];

        $pengaturan = app(PengaturanService::class);
        $tanggalAsli = $pengaturan->get('tanggal_transaksi');

        foreach ($contoh as [$nis, $nama, $kelas, $jk, $password, $wajibGanti, $lengkap]) {
            if (Nasabah::query()->where('nis', $nis)->exists()) {
                continue;
            }

            DB::transaction(function () use ($nis, $nama, $kelas, $jk, $password, $wajibGanti, $lengkap, $pengaturan) {
                $nasabah = new Nasabah([
                    'nis' => $nis,
                    'nama' => $nama,
                    'jenis' => 'siswa',
                    'jenis_kelamin' => $jk,
                    'kelas' => $kelas,
                    'is_active' => true,
                    ...($lengkap ? [
                        'tempat_lahir' => 'Semarang',
                        'tanggal_lahir' => '2008-03-14',
                        'alamat_jalan' => 'Jl. Sidodadi Barat No. 8',
                        'rt' => '003',
                        'rw' => '005',
                        'kelurahan' => 'Karangkidul',
                        'kecamatan' => 'Semarang Tengah',
                        'kota' => 'Kota Semarang',
                        'kode_pos' => '50136',
                        'no_hp' => '081234567890',
                        'nama_orang_tua' => 'Slamet Riyadi',
                        'wali_hubungan' => 'ayah',
                        'wali_hp' => '081298765432',
                    ] : []),
                ]);
                $nasabah->alamat = Nasabah::susunAlamat($nasabah->getAttributes());
                $nasabah->nomor = app(NasabahService::class)->nomorBerikutnya();
                $nasabah->save();

                $nasabah->akunIb()->create([
                    'password' => $password,
                    'wajib_ganti_password' => $wajibGanti,
                    'password_diganti_at' => $wajibGanti ? null : now()->subDays(30),
                    'profil_lengkap_at' => $lengkap ? now()->subDays(29) : null,
                    'is_active' => true,
                ]);

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
