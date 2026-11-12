<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint publik IB yang menyimpan status di sesi (login, aktivasi). Sesi & CSRF hanya ada untuk
 * origin di SANCTUM_STATEFUL_DOMAINS (frontend IB); request lain dijawab 419, sama dengan CSRF
 * kedaluwarsa, agar frontend mengambil ulang csrf-cookie.
 */
class WajibSesiNasabah
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'Permintaan harus dari aplikasi Internet Banking (sesi/CSRF tidak tersedia).',
            ], 419);
        }

        return $next($request);
    }
}
