<?php

namespace App\Http\Modules\Nasabah\Services;

use App\Http\Modules\Nasabah\Exceptions\DataSiswaTidakTersedia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Mengambil data siswa dari API sekolah berdasarkan NISN, lalu menyesuaikannya
 * dengan field form nasabah. Foto dan id siswa sekolah tidak dipakai.
 */
class DataSiswaService
{
    /**
     * Data siswa, atau null bila NISN tidak ditemukan.
     *
     * @return array{nama: string|null, nis: string|null, nisn: string|null, nik: string|null, no_hp: string|null, kelas: string|null, email: string|null}|null
     *
     * @throws DataSiswaTidakTersedia
     */
    public function cari(string $nisn): ?array
    {
        try {
            $response = Http::timeout(config('services.siswa_api.timeout', 8))
                ->acceptJson()
                ->get(config('services.siswa_api.url'), ['nisn' => $nisn]);
        } catch (ConnectionException $e) {
            throw new DataSiswaTidakTersedia('API data siswa tidak dapat dihubungi.', previous: $e);
        }

        if ($response->notFound() || ($response->successful() && $response->json('success') === false)) {
            return null;
        }

        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data)) {
            throw new DataSiswaTidakTersedia("API data siswa membalas tidak wajar (HTTP {$response->status()}).");
        }

        return $this->normalisasi($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{nama: string|null, nis: string|null, nisn: string|null, nik: string|null, no_hp: string|null, kelas: string|null, email: string|null}
     */
    protected function normalisasi(array $data): array
    {
        $teks = fn (string $kunci) => filled($data[$kunci] ?? null) ? preg_replace('/\s+/', ' ', trim((string) $data[$kunci])) : null;
        $digit = fn (string $kunci) => filled($data[$kunci] ?? null) ? (preg_replace('/\D/', '', (string) $data[$kunci]) ?: null) : null;

        $noHp = $digit('no_hp');

        return [
            'nama' => $teks('nama_siswa'),
            'nis' => $teks('nis'),
            'nisn' => $digit('nisn'),
            'nik' => $digit('nik'),
            // Nomor 62812… ditulis sebagai 0812… seperti kebiasaan di form.
            'no_hp' => $noHp && str_starts_with($noHp, '62') ? '0'.substr($noHp, 2) : $noHp,
            'kelas' => ($kelas = $teks('kelas')) ? mb_strtoupper($kelas) : null,
            'email' => ($email = $teks('email')) ? mb_strtolower($email) : null,
        ];
    }
}
