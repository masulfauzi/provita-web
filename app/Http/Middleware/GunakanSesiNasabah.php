<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request Internet Banking nasabah memakai cookie sesi sendiri (lihat config/ib.php), terpisah
 * dari sesi panel petugas: logout IB tidak mengeluarkan teller, dan umur sesi IB lebih pendek.
 * Dipasang global (sebelum StartSession) karena CSRF cookie Sanctum lewat grup web.
 */
class GunakanSesiNasabah
{
    public const PATH = ['api/v1/nasabah', 'api/v1/nasabah/*', 'api/v1/sanctum/csrf-cookie', 'sanctum/csrf-cookie'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::PATH)) {
            config([
                'session.cookie' => config('ib.session.cookie'),
                'session.domain' => config('ib.session.domain'),
                'session.lifetime' => config('ib.session.lifetime'),
                'session.secure' => $request->isSecure() ?: config('session.secure'),
                'session.same_site' => 'lax',
                'session.http_only' => true,
            ]);

            // Store sesi bisa sudah dibuat dengan nama cookie panel; nama dipakai StartSession
            // untuk membaca & menulis cookie, jadi ikut diganti.
            app('session')->driver()->setName(config('ib.session.cookie'));
        }

        return $next($request);
    }
}
