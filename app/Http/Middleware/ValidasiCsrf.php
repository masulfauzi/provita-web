<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Validasi CSRF dengan cookie XSRF terpisah untuk panel petugas dan Internet Banking.
 *
 * Panel dan API IB berbagi host (localhost tidak memisahkan cookie per port; di produksi panel
 * dan API satu aplikasi). Dengan satu nama cookie "XSRF-TOKEN", respons IB menimpa token panel
 * (dan sebaliknya) sehingga form panel yang sudah terbuka ditolak 419. IB tetap memakai
 * "XSRF-TOKEN" (kontrak frontend provita-ib); panel memakai "XSRF-TOKEN-PANEL"
 * (lihat resources/js/app.js).
 */
class ValidasiCsrf extends ValidateCsrfToken
{
    public const COOKIE_PANEL = 'XSRF-TOKEN-PANEL';

    public const COOKIE_IB = 'XSRF-TOKEN';

    protected function newCookie($request, $config)
    {
        return new Cookie(
            $request->is(...GunakanSesiNasabah::PATH) ? self::COOKIE_IB : self::COOKIE_PANEL,
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
