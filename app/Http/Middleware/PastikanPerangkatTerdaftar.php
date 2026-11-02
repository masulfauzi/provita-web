<?php

namespace App\Http\Middleware;

use App\Http\Modules\Perangkat\Services\PerangkatService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Teller yang sedang login dikeluarkan bila komputernya tidak (lagi) terdaftar,
 * mis. cookie perangkat hilang atau perangkat dinonaktifkan bendahara. Diperiksa
 * setiap request agar sesi teller yang dibawa ke komputer lain tidak bisa dipakai.
 */
class PastikanPerangkatTerdaftar
{
    public function __construct(protected PerangkatService $perangkat) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $this->perangkat->diizinkan($user, $request)) {
            return $next($request);
        }

        $this->perangkat->catatDitolak($user, $request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(403, PerangkatService::PESAN_DITOLAK);
        }

        // 303 agar request PUT/POST/DELETE Inertia diikuti dengan GET.
        return redirect('/login', 303)->with('error', PerangkatService::PESAN_DITOLAK);
    }
}
