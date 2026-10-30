<?php

namespace App\Http\Modules\Nasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\Nasabah\Exceptions\DataSiswaTidakTersedia;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Nasabah\Services\DataSiswaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar siswa: mencari siswa di sistem sekolah per nama, lengkap dengan status
 * apakah siswa tersebut sudah terdaftar sebagai nasabah (dicocokkan lewat NISN).
 */
class DaftarSiswaController extends Controller
{
    public function index(Request $request, DataSiswaService $dataSiswa): Response
    {
        // Kesalahan isian ditampilkan di halaman (bukan redirect) karena ini pencarian GET.
        $nama = mb_substr(preg_replace('/\s+/', ' ', trim($request->string('nama')->toString())), 0, 100);
        $galatNama = $nama !== '' && mb_strlen($nama) < 3 ? 'Nama siswa minimal 3 huruf.' : null;

        $hasil = null;
        $galat = null;

        if ($nama !== '' && ! $galatNama) {
            try {
                $hasil = $this->tandaiNasabah($dataSiswa->cariNama($nama));
            } catch (DataSiswaTidakTersedia $e) {
                report($e);
                $galat = $e->getMessage();
            }
        }

        return Inertia::render('Nasabah::DaftarSiswa', compact('nama', 'hasil', 'galat', 'galatNama'));
    }

    /**
     * @param  list<array{nama: string|null, nis: string|null, nisn: string|null, kelas: string|null}>  $siswa
     * @return list<array<string, mixed>>
     */
    protected function tandaiNasabah(array $siswa): array
    {
        $nasabah = Nasabah::query()
            ->whereIn('nisn', array_filter(array_column($siswa, 'nisn')))
            ->get(['id', 'nomor', 'nama', 'nisn', 'is_active'])
            ->keyBy('nisn');

        return array_map(fn (array $item) => [
            ...$item,
            'nasabah' => $item['nisn'] ? $nasabah->get($item['nisn'])?->only('id', 'nomor', 'nama', 'is_active') : null,
        ], $siswa);
    }
}
