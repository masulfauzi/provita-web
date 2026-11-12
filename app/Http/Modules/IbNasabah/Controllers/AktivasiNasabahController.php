<?php

namespace App\Http\Modules\IbNasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\IbNasabah\Requests\AktivasiRequest;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Aktivasi Internet Banking oleh nasabah sendiri:
 *  1. POST /aktivasi/cek  — NISN (siswa) / No. nasabah (guru, lembaga) + nomor rekening.
 *  2. POST /aktivasi      — data pribadi + No. HP (ID login) + password, dalam sesi yang sama.
 * Sesudahnya nasabah login dengan No. HP + password.
 */
class AktivasiNasabahController extends Controller
{
    public const KUNCI_SESI = 'ib_aktivasi';

    public const BATAS_GAGAL = 5;

    public const PESAN_GAGAL = 'NISN/No. nasabah atau nomor rekening tidak cocok.';

    public function __construct(protected AkunNasabahService $akun) {}

    public function cek(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identitas' => ['required', 'string', 'max:50'],
            'no_rekening' => ['required', 'string', 'max:50'],
        ], [
            'identitas.required' => 'NISN / No. nasabah wajib diisi.',
            'no_rekening.required' => 'Nomor rekening wajib diisi.',
        ]);

        $kunci = 'ib-aktivasi|'.Str::lower(trim($data['identitas'])).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_GAGAL)) {
            $detik = RateLimiter::availableIn($kunci);

            return response()->json([
                'message' => 'Terlalu banyak percobaan aktivasi. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit.',
                'retry_after' => $detik,
            ], 429, ['Retry-After' => (string) $detik]);
        }

        $nasabah = $this->akun->cariNasabahAktivasi($data['identitas'], $data['no_rekening']);

        if (! $nasabah) {
            RateLimiter::hit($kunci, 60);
            $request->session()->forget(self::KUNCI_SESI);

            return response()->json([
                'message' => self::PESAN_GAGAL,
                'errors' => ['identitas' => [self::PESAN_GAGAL]],
                'sisa_percobaan' => max(0, self::BATAS_GAGAL - RateLimiter::attempts($kunci)),
            ], 422);
        }

        RateLimiter::clear($kunci);

        if ($nasabah->akunIb()->exists()) {
            return $this->sudahAktivasi();
        }

        $berlakuSampai = now()->addMinutes((int) config('ib.aktivasi_menit', 15));
        $request->session()->put(self::KUNCI_SESI, ['nasabah_id' => $nasabah->id, 'berlaku_sampai' => $berlakuSampai->toIso8601String()]);

        return response()->json(['data' => [
            'nama' => $nasabah->nama,
            'kelas' => $nasabah->kelas,
            'jenis_nasabah' => $nasabah->jenis,
            'wajib_wali' => $nasabah->jenis === 'siswa',
            'berlaku_sampai' => $berlakuSampai->toIso8601String(),
        ]]);
    }

    public function simpan(AktivasiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $akun = $this->akun->aktivasi($request->nasabah(), $data);
        $request->session()->forget(self::KUNCI_SESI);

        if (! $akun) {
            return $this->sudahAktivasi();
        }

        return response()->json([
            'message' => 'Aktivasi berhasil. Silakan masuk menggunakan No. HP dan password Anda.',
            'data' => ['no_hp' => $akun->no_hp],
        ], 201);
    }

    protected function sudahAktivasi(): JsonResponse
    {
        return response()->json([
            'message' => 'Akun sudah diaktivasi. Silakan masuk dengan No. HP dan password. Lupa password atau ganti No. HP? Hubungi teller.',
            'kode' => 'SUDAH_AKTIVASI',
        ], 409);
    }
}
