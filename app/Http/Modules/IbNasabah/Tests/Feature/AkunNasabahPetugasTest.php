<?php

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->nasabah = Nasabah::factory()->create(['nis' => '25041']);
    Rekening::factory()->create(['nasabah_id' => $this->nasabah->id, 'nomor' => '1100325041']);
});

it('petugas mereset password IB: password sementara sekali tampil, sesi lama keluar', function () {
    $akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);
    $teller = userWithRole('teller');

    $respons = $this->actingAs($teller)->post("/nasabah/{$this->nasabah->id}/reset-password-ib")
        ->assertRedirect("/nasabah/{$this->nasabah->id}")
        ->assertSessionHas('success');

    $password = session('password_ib');
    $akun->refresh();

    expect($password)->toHaveLength(8)->toMatch('/[a-z]/')->toMatch('/\d/')
        ->and(Hash::check($password, $akun->password))->toBeTrue()
        ->and($akun->wajib_ganti_password)->toBeTrue()
        ->and($akun->versi_sesi)->toBe(1);

    $this->actingAs($teller)->get("/nasabah/{$this->nasabah->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('akunIb.wajib_ganti_password', true)
            ->where('akunIb.profil_lengkap', true));

    sebagaiNasabah($akun, 0)->getJson('/api/v1/nasabah/me')->assertUnauthorized();

    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => $password])
        ->assertOk()
        ->assertJsonPath('data.wajib_ganti_password', true);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'reset_password_ib', 'causer_id' => $teller->id]);
});

it('reset membuat akun bila nasabah belum punya; admin tidak berhak', function () {
    $this->actingAs(userWithRole('admin'))->post("/nasabah/{$this->nasabah->id}/reset-password-ib")->assertForbidden();
    expect(AkunNasabah::count())->toBe(0);

    $this->actingAs(userWithRole('teller'))->post("/nasabah/{$this->nasabah->id}/reset-password-ib")->assertRedirect();

    expect(AkunNasabah::where('nasabah_id', $this->nasabah->id)->value('wajib_ganti_password'))->toBeTrue();
});

it('ib:buat-akun membuat akun untuk nasabah aktif dan mengekspor password awal', function () {
    $this->freezeTime();
    Excel::fake();
    $sudah = Nasabah::factory()->create();
    AkunNasabah::factory()->create(['nasabah_id' => $sudah->id]);
    Nasabah::factory()->nonaktif()->create();

    $this->artisan('ib:buat-akun')->assertExitCode(2);
    $this->artisan('ib:buat-akun --semua')->assertSuccessful();

    expect(AkunNasabah::count())->toBe(2)
        ->and(AkunNasabah::where('nasabah_id', $this->nasabah->id)->first())->wajib_ganti_password->toBeTrue();

    Excel::assertStored('ib-akun-'.now()->format('Ymd-His').'.xlsx', 'local', function ($export) {
        $baris = $export->array();

        return count($baris) === 1 && $baris[0][0] === '25041' && strlen($baris[0][4]) === 8
            && Hash::check($baris[0][4], AkunNasabah::where('nasabah_id', $this->nasabah->id)->value('password'));
    });

    $this->artisan('ib:buat-akun --semua')->expectsOutputToContain('Tidak ada nasabah aktif')->assertSuccessful();
});

it('semua route nasabah selain login memakai auth.nasabah, ib.onboarding, dan throttle:ib', function () {
    $route = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/nasabah'))
        ->reject(fn ($r) => $r->uri() === 'api/v1/nasabah/login');

    expect($route)->not->toBeEmpty();

    foreach ($route as $r) {
        $middleware = $r->gatherMiddleware();

        expect($middleware)->toContain('auth.nasabah', 'throttle:ib')
            ->and(collect($middleware)->contains(fn ($m) => str_starts_with($m, 'ib.onboarding')))->toBeTrue("{$r->uri()} tanpa ib.onboarding");
    }
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
    AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);
    $cookiePanel = config('session.cookie');

    expect($cookiePanel)->not->toBe(config('ib.session.cookie'));

    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])
        ->assertOk()
        ->assertCookie(config('ib.session.cookie'))
        ->assertCookieMissing($cookiePanel);
});
