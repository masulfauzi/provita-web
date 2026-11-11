<?php

namespace App\Http\Middleware;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alur wajib nasabah: ganti password, lalu lengkapi data pribadi. Parameter menyebut tahap yang
 * tetap boleh, mis. `ib.onboarding:password,profil` untuk me/logout.
 */
class PastikanOnboardingNasabah
{
    public function handle(Request $request, Closure $next, string ...$izin): Response
    {
        /** @var AkunNasabah $akun */
        $akun = Auth::guard('nasabah')->user();

        if ($akun->wajib_ganti_password && ! in_array('password', $izin, true)) {
            return response()->json(['message' => 'Anda wajib mengganti password.', 'kode' => 'WAJIB_GANTI_PASSWORD'], 403);
        }

        if (! $akun->profilLengkap() && ! in_array('profil', $izin, true)) {
            return response()->json(['message' => 'Anda wajib melengkapi data pribadi.', 'kode' => 'WAJIB_LENGKAPI_DATA'], 403);
        }

        return $next($request);
    }
}
