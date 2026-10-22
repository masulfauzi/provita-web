<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = userWithRole('admin');
});

it('menampilkan daftar pengguna', function () {
    User::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get('/pengguna')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Pengguna::Index')->has('items.data', 3));
});

it('menambah pengguna dengan peran', function () {
    $this->actingAs($this->admin)
        ->post('/pengguna', [
            'username' => 'siswa_piket',
            'name' => 'Siswa Piket',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'roles' => ['teller'],
        ])
        ->assertRedirect('/pengguna');

    $user = User::where('username', 'siswa_piket')->firstOrFail();
    expect($user->hasRole('teller'))->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('mengubah peran pengguna', function () {
    $user = userWithRole('teller');

    $this->actingAs($this->admin)
        ->put("/pengguna/{$user->id}", ['name' => 'Nama Baru', 'roles' => ['bendahara']])
        ->assertRedirect('/pengguna');

    expect($user->fresh()->getRoleNames()->all())->toBe(['bendahara']);
    $this->assertDatabaseHas('activity_log', ['event' => 'roles_changed', 'subject_id' => $user->id]);
});

it('mereset kata sandi pengguna', function () {
    $user = userWithRole('teller');

    $this->actingAs($this->admin)
        ->put("/pengguna/{$user->id}/password", ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('baru12345', $user->fresh()->password))->toBeTrue();
});

it('menonaktifkan dan mengaktifkan pengguna', function () {
    $user = userWithRole('teller');

    $this->actingAs($this->admin)->patch("/pengguna/{$user->id}/aktif");
    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->patch("/pengguna/{$user->id}/aktif");
    expect($user->fresh()->is_active)->toBeTrue();
});

it('tidak dapat menonaktifkan akun sendiri', function () {
    $this->actingAs($this->admin)
        ->patch("/pengguna/{$this->admin->id}/aktif")
        ->assertForbidden();
});

it('teller tidak dapat mengelola pengguna', function () {
    $teller = userWithRole('teller');

    $this->actingAs($teller)->get('/pengguna')->assertForbidden();
    $this->actingAs($teller)->post('/pengguna', [])->assertForbidden();
});
