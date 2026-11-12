<?php

namespace App\Http\Modules\IbNasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AutentikasiNasabah;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Resources\MeResource;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthNasabahController extends Controller
{
    public const BATAS_GAGAL = 5;

    public const PESAN_GAGAL = 'No. HP atau password salah.';

    public function __construct(protected AkunNasabahService $akun) {}

    /**
     * Login dengan No. HP (format 08…, 62…, atau +62…) + password. No. HP tidak dikenal dan password
     * salah dijawab dengan pesan yang sama. Maksimal 5 kali gagal per menit per No. HP + IP.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'no_hp' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ], ['no_hp.required' => 'No. HP wajib diisi.', 'password.required' => 'Password wajib diisi.']);

        $noHp = $this->akun->normalisasiHp($data['no_hp']);
        $kunci = 'ib-login|'.($noHp !== '' ? $noHp : Str::lower(trim($data['no_hp']))).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_GAGAL)) {
            $detik = RateLimiter::availableIn($kunci);

            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit.',
                'retry_after' => $detik,
            ], 429, ['Retry-After' => (string) $detik]);
        }

        $akun = $this->akun->cariAkun($noHp);

        if (! $akun || ! $akun->bisaLogin() || ! Hash::check($data['password'], $akun->password)) {
            RateLimiter::hit($kunci, 60);

            if ($akun) {
                $this->akun->catatLogin($akun, $request, false);
            }

            return response()->json([
                'message' => self::PESAN_GAGAL,
                'errors' => ['no_hp' => [self::PESAN_GAGAL]],
                'sisa_percobaan' => max(0, self::BATAS_GAGAL - RateLimiter::attempts($kunci)),
            ], 422);
        }

        RateLimiter::clear($kunci);

        Auth::guard('nasabah')->login($akun);
        $request->session()->regenerate();
        $request->session()->put(AutentikasiNasabah::KUNCI_VERSI, $akun->versi_sesi);

        $this->akun->catatLogin($akun, $request, true);
        $this->log($akun, 'login', 'Login Internet Banking', $request);

        return response()->json(['data' => new MeResource($akun)]);
    }

    public function logout(Request $request): Response
    {
        /** @var AkunNasabah $akun */
        $akun = Auth::guard('nasabah')->user();
        $this->log($akun, 'logout', 'Logout Internet Banking', $request);

        Auth::guard('nasabah')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new MeResource(Auth::guard('nasabah')->user()->load('nasabah'))]);
    }

    protected function log(AkunNasabah $akun, string $event, string $pesan, Request $request): void
    {
        activity('nasabah')->performedOn($akun->nasabah)->causedBy($akun)->event($event)
            ->withProperties(['ip' => $request->ip(), 'perangkat' => $this->akun->perangkat($request->userAgent())])
            ->log($pesan);
    }
}
