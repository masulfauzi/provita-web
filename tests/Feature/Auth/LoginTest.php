<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan halaman login', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('login dengan username dan password', function () {
    $user = User::factory()->create(['username' => 'teller1']);

    $this->post('/login', ['username' => 'teller1', 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('menolak password yang salah', function () {
    User::factory()->create(['username' => 'teller1']);

    $this->post('/login', ['username' => 'teller1', 'password' => 'salah'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('menolak login pengguna nonaktif', function () {
    User::factory()->inactive()->create(['username' => 'lama']);

    $this->post('/login', ['username' => 'lama', 'password' => 'password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('membatasi percobaan login berulang', function () {
    User::factory()->create(['username' => 'teller1']);
    RateLimiter::clear('teller1|127.0.0.1');

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['username' => 'teller1', 'password' => 'salah']);
    }

    $this->post('/login', ['username' => 'teller1', 'password' => 'password'])
        ->assertSessionHasErrors('username');

    expect(session('errors')->first('username'))->toStartWith('Terlalu banyak percobaan masuk');

    $this->assertGuest();
});

it('logout mengakhiri sesi', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('mencatat login dan logout ke log aktivitas', function () {
    $user = User::factory()->create(['username' => 'teller1']);

    $this->post('/login', ['username' => 'teller1', 'password' => 'password']);
    $this->post('/logout');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'login', 'causer_id' => $user->id]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'logout', 'causer_id' => $user->id]);
});
