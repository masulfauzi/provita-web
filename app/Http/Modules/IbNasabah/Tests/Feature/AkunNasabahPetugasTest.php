<?php

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->nasabah = Nasabah::factory()->create(['nis' => '25041', 'nisn' => '0012345678']);
    Rekening::factory()->create(['nasabah_id' => $this->nasabah->id, 'nomor' => '1100325041']);
});

it('teller mereset akun IB: akun terhapus, sesi berakhir, nasabah bisa aktivasi ulang', function () {
    AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id, 'no_hp' => '081234567890']);
    $teller = userWithRole('teller');

    // Nasabah sedang login di IB.
    dariIb()->postJson('/api/v1/nasabah/login', ['no_hp' => '081234567890', 'password' => 'Provita2026'])->assertOk();
    $sesiIb = session()->all();

    $this->actingAs($teller)->get("/nasabah/{$this->nasabah->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('akunIb.no_hp', '081234567890')
            ->where('akunIb.is_active', true)
            ->has('akunIb.diaktivasi_at')
            ->missing('passwordIb'));

    $this->actingAs($teller)->post("/nasabah/{$this->nasabah->id}/reset-akun-ib")
        ->assertRedirect("/nasabah/{$this->nasabah->id}")
        ->assertSessionHas('success');

    expect(AkunNasabah::count())->toBe(0);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'reset_akun_ib', 'causer_id' => $teller->id]);

    // Request berikutnya dari sesi IB yang sama (guard dibaca ulang dari database).
    app('auth')->forgetGuards();
    dariIb()->withSession($sesiIb)->getJson('/api/v1/nasabah/me')->assertUnauthorized();
    dariIb()->postJson('/api/v1/nasabah/login', ['no_hp' => '081234567890', 'password' => 'Provita2026'])->assertStatus(422);
    dariIb()->postJson('/api/v1/nasabah/aktivasi/cek', ['identitas' => $this->nasabah->nisn, 'no_rekening' => '1100325041'])->assertOk();

    $this->actingAs($teller)->get("/nasabah/{$this->nasabah->id}")
        ->assertInertia(fn (Assert $page) => $page->where('akunIb', null));
});

it('reset tanpa akun ditolak dengan pesan; admin tidak berhak', function () {
    $this->actingAs(userWithRole('teller'))->post("/nasabah/{$this->nasabah->id}/reset-akun-ib")->assertSessionHas('error');

    AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);
    $this->actingAs(userWithRole('admin'))->post("/nasabah/{$this->nasabah->id}/reset-akun-ib")->assertForbidden();

    expect(AkunNasabah::count())->toBe(1);
});

it('semua route nasabah kecuali aktivasi & login memakai auth.nasabah dan throttle:ib', function () {
    $publik = ['api/v1/nasabah/login', 'api/v1/nasabah/aktivasi/cek', 'api/v1/nasabah/aktivasi'];
    $route = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/nasabah'));

    foreach ($route as $r) {
        $middleware = $r->gatherMiddleware();

        if (in_array($r->uri(), $publik, true)) {
            expect($middleware)->toContain('ib.sesi')->not->toContain('auth.nasabah');
        } else {
            expect($middleware)->toContain('auth.nasabah', 'throttle:ib');
        }
    }

    expect($route->map->uri()->intersect($publik))->toHaveCount(3);
});

it('membersihkan riwayat login lama, menyimpan 50 terakhir per akun', function () {
    $akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);

    foreach (range(1, 55) as $i) {
        $akun->riwayatLogin()->create(['waktu' => now()->subMinutes($i), 'berhasil' => true]);
    }

    $this->artisan('ib:bersihkan-login')->assertSuccessful();

    expect($akun->riwayatLogin()->count())->toBe(50)
        ->and($akun->riwayatLogin()->min('waktu'))->toBe(now()->subMinutes(50)->toDateTimeString());
});

it('respons API membawa header keamanan dan CORS untuk frontend IB', function () {
    dariIb()->getJson('/api/v1/nasabah/me')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    dariIb()->get('/api/v1/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
});

it('sesi IB memakai cookie sendiri, terpisah dari sesi panel', function () {
    AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id, 'no_hp' => '081234567890']);
    $cookiePanel = config('session.cookie');

    expect($cookiePanel)->not->toBe(config('ib.session.cookie'));

    dariIb()->postJson('/api/v1/nasabah/login', ['no_hp' => '081234567890', 'password' => 'Provita2026'])
        ->assertOk()
        ->assertCookie(config('ib.session.cookie'))
        ->assertCookieMissing($cookiePanel);
});
