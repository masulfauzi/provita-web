<?php

use App\Http\Modules\Perangkat\Models\Perangkat;
use App\Http\Modules\Perangkat\Services\PerangkatService;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['auth.perangkat.wajib' => true]);

    $this->bendahara = userWithRole('bendahara');
    $this->teller = userWithRole('teller');
    $this->teller->update(['username' => 'teller1']);
});

/**
 * Mendaftarkan perangkat lewat service dan mengembalikan token mentah untuk cookie.
 */
function perangkatTerdaftar(string $nama = 'PC Teller 1'): array
{
    [$perangkat, $cookie] = app(PerangkatService::class)->daftarkan($nama, test()->bendahara);

    return [$perangkat, $cookie->getValue()];
}

function loginTeller(?string $token = null)
{
    $request = $token ? test()->withCookie(PerangkatService::COOKIE, $token) : test();

    return $request->post('/login', ['username' => 'teller1', 'password' => 'password']);
}

it('menolak teller login dari komputer yang belum terdaftar dan mencatatnya', function () {
    loginTeller()->assertSessionHasErrors(['username' => PerangkatService::PESAN_DITOLAK]);

    $this->assertGuest();
    expect(Activity::where('log_name', 'perangkat')->where('event', 'ditolak')->where('causer_id', $this->teller->id)->exists())->toBeTrue();
});

it('mengizinkan teller login dari komputer terdaftar dan mencatat waktu pemakaian', function () {
    [$perangkat, $token] = perangkatTerdaftar();

    loginTeller($token)->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->teller);
    expect($perangkat->fresh()->terakhir_dipakai_at)->not->toBeNull();
});

it('menolak token palsu dan perangkat nonaktif', function () {
    loginTeller('token-palsu')->assertSessionHasErrors('username');

    [$perangkat, $token] = perangkatTerdaftar();
    $perangkat->update(['is_active' => false]);

    loginTeller($token)->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('tidak membatasi bendahara dan admin', function (string $role) {
    $user = userWithRole($role);
    $user->update(['username' => "user-{$role}"]);

    $this->post('/login', ['username' => "user-{$role}", 'password' => 'password'])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
})->with(['bendahara', 'admin']);

it('teller yang punya peran bendahara juga tidak dibatasi', function () {
    $this->teller->assignRole('bendahara');

    loginTeller()->assertRedirect('/dashboard');
});

it('mengeluarkan teller yang sedang login bila perangkatnya dinonaktifkan', function () {
    [$perangkat, $token] = perangkatTerdaftar();

    $this->actingAs($this->teller)->withCookie(PerangkatService::COOKIE, $token)
        ->get('/dashboard')->assertOk();

    $perangkat->update(['is_active' => false]);

    $this->actingAs($this->teller)->withCookie(PerangkatService::COOKIE, $token)
        ->put('/profil/password', [])
        ->assertStatus(303)->assertRedirect('/login')
        ->assertSessionHas('error', PerangkatService::PESAN_DITOLAK);

    $this->assertGuest();
});

it('mengeluarkan sesi teller yang dipakai di komputer lain', function () {
    $this->actingAs($this->teller)->get('/dashboard')->assertRedirect('/login');

    $this->assertGuest();
});

it('bendahara mendaftarkan komputer ini dan hanya hash token yang disimpan', function () {
    $response = $this->actingAs($this->bendahara)
        ->post('/perangkat', ['nama' => 'PC Teller 1'])
        ->assertSessionHas('success');

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === PerangkatService::COOKIE);
    expect($cookie)->not->toBeNull()->and($cookie->isHttpOnly())->toBeTrue();

    $perangkat = Perangkat::sole();
    $token = decrypt($cookie->getValue(), false);
    $token = str_contains($token, '|') ? explode('|', $token, 2)[1] : $token;

    expect($perangkat->nama)->toBe('PC Teller 1')
        ->and($perangkat->didaftarkan_oleh)->toBe($this->bendahara->id)
        ->and($perangkat->getRawOriginal('token_hash'))->toBe(hash('sha256', $token))
        ->and($perangkat->getRawOriginal('token_hash'))->not->toBe($token);

    loginTeller($token)->assertRedirect('/dashboard');
});

it('mendaftar ulang di browser yang sama menonaktifkan perangkat lama', function () {
    [$lama, $token] = perangkatTerdaftar('PC Lama');

    $this->actingAs($this->bendahara)->withCookie(PerangkatService::COOKIE, $token)
        ->post('/perangkat', ['nama' => 'PC Baru'])->assertSessionHas('success');

    expect($lama->fresh()->is_active)->toBeFalse()
        ->and(Perangkat::where('is_active', true)->sole()->nama)->toBe('PC Baru');
});

it('menampilkan daftar perangkat dan menandai komputer ini', function () {
    [$perangkat, $token] = perangkatTerdaftar();
    perangkatTerdaftar('PC Teller 2');

    $this->actingAs($this->bendahara)->withCookie(PerangkatService::COOKIE, $token)
        ->get('/perangkat')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Perangkat::Index')
            ->where('perangkatIni.nama', 'PC Teller 1')
            ->has('items', 2)
            ->where('items.0.ini', true)
            ->missing('items.0.token_hash'));
});

it('bendahara mengubah nama, menonaktifkan, dan menghapus perangkat', function () {
    [$perangkat] = perangkatTerdaftar();

    $this->actingAs($this->bendahara)->put("/perangkat/{$perangkat->id}", ['nama' => 'PC Depan'])->assertSessionHas('success');
    expect($perangkat->fresh()->nama)->toBe('PC Depan');

    $this->post("/perangkat/{$perangkat->id}/toggle")->assertSessionHas('success');
    expect($perangkat->fresh()->is_active)->toBeFalse();

    $this->post("/perangkat/{$perangkat->id}/toggle");
    expect($perangkat->fresh()->is_active)->toBeTrue();

    $this->delete("/perangkat/{$perangkat->id}")->assertSessionHas('success');
    expect(Perangkat::count())->toBe(0);
});

it('teller tidak bisa membuka menu perangkat', function () {
    [, $token] = perangkatTerdaftar();

    $this->actingAs($this->teller)->withCookie(PerangkatService::COOKIE, $token)
        ->get('/perangkat')->assertForbidden();
    $this->post('/perangkat', ['nama' => 'PC Liar'])->assertForbidden();
});

it('menolak token API untuk teller', function () {
    $this->postJson('/api/v1/auth/token', ['username' => 'teller1', 'password' => 'password', 'device_name' => 'hp'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');
});

it('tidak membatasi teller bila pembatasan dimatikan', function () {
    config(['auth.perangkat.wajib' => false]);

    loginTeller()->assertRedirect('/dashboard');
    expect(app(PerangkatService::class)->wajibPerangkat(User::find($this->teller->id)))->toBeFalse();
});
