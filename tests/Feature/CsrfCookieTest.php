<?php

use App\Http\Middleware\ValidasiCsrf;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;

/*
| Panel petugas dan API Internet Banking berbagi host, jadi cookie XSRF keduanya harus bernama
| beda. Bila sama, respons IB menimpa token panel dan form panel yang sudah terbuka (mis. tutup
| sesi kas) ditolak 419 "Sesi telah berakhir".
*/

/**
 * Token sesi di dalam cookie XSRF (terenkripsi + prefiks nama cookie).
 */
function tokenDariCookie(string $nilai): string
{
    return CookieValuePrefix::remove(decrypt($nilai, false));
}

/**
 * Validasi CSRF sungguhan (di unit test Laravel melewati pengecekan token).
 */
function csrfCocok(string $tokenSesi, string $headerXsrf): bool
{
    $validasi = new class(app(), app('encrypter')) extends ValidasiCsrf
    {
        public function cocok(Request $request): bool
        {
            return $this->tokensMatch($request);
        }
    };

    $sesi = new Store('uji', new ArraySessionHandler(1));
    $sesi->put('_token', $tokenSesi);

    $request = Request::create('/kas/sesi/1/tutup', 'POST', server: ['HTTP_X_XSRF_TOKEN' => $headerXsrf]);
    $request->setLaravelSession($sesi);

    return $validasi->cocok($request);
}

it('halaman panel memasang cookie XSRF-TOKEN-PANEL, bukan XSRF-TOKEN', function () {
    $this->get('/login')
        ->assertOk()
        ->assertCookie(ValidasiCsrf::COOKIE_PANEL, encrypted: true)
        ->assertCookieMissing(ValidasiCsrf::COOKIE_IB);
});

it('csrf-cookie Internet Banking memasang XSRF-TOKEN, bukan cookie panel', function () {
    foreach (['/api/v1/sanctum/csrf-cookie', '/sanctum/csrf-cookie'] as $url) {
        dariIb()->get($url)
            ->assertNoContent()
            ->assertCookie(ValidasiCsrf::COOKIE_IB, encrypted: true)
            ->assertCookieMissing(ValidasiCsrf::COOKIE_PANEL);
    }
});

it('aktivitas IB tidak lagi merusak token form panel', function () {
    $this->actingAs(User::factory()->create());

    $panel = $this->get('/dashboard')->assertOk()->getCookie(ValidasiCsrf::COOKIE_PANEL, false)->getValue();
    $tokenPanel = session()->token();

    // Cookie XSRF-TOKEN dari IB berisi token sesi IB (sesi lain, token berbeda).
    $ib = encrypt(CookieValuePrefix::create(ValidasiCsrf::COOKIE_IB, app('encrypter')->getKey()).Str::random(40), false);

    expect(tokenDariCookie($panel))->toBe($tokenPanel)
        // Panel mengirim header dari cookie panel → lolos; dari cookie IB (bug lama) → 419.
        ->and(csrfCocok($tokenPanel, $panel))->toBeTrue()
        ->and(csrfCocok($tokenPanel, $ib))->toBeFalse();
});
