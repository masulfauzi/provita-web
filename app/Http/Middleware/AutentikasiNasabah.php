<?php

namespace App\Http\Middleware;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya nasabah yang login lewat guard `nasabah`. Login petugas (guard web) tidak berlaku.
 * Sesi ditolak (401) bila akun/nasabah dinonaktifkan atau versi sesi berubah (password direset
 * petugas atau diganti dari perangkat lain).
 */
class AutentikasiNasabah
{
    public const KUNCI_VERSI = 'ib_versi_sesi';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('nasabah');
        /** @var AkunNasabah|null $akun */
        $akun = $guard->user();

        if (! $akun || ! $akun->bisaLogin() || (int) $request->session()->get(self::KUNCI_VERSI, -1) !== (int) $akun->versi_sesi) {
            if ($akun) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Model lain (policy, request->user()) memakai akun nasabah pada request ini.
        Auth::shouldUse('nasabah');

        return $next($request);
    }
}
