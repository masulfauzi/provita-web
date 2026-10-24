<?php

use Inertia\Testing\AssertableInertia as Assert;

/*
| Hak akses per peran. Teller tidak boleh membuka modul pinjaman, jurnal &
| bagan akun, pengguna, maupun log aktivitas walaupun URL diketik langsung.
| Laporan keuangan boleh dilihat teller.
*/

dataset('route khusus bendahara', [
    'pinjaman' => '/pinjaman',
    'jurnal umum' => '/jurnal',
    'bagan akun' => '/akun',
    'pengguna' => '/pengguna',
    'log aktivitas' => '/log-aktivitas',
]);

it('teller mendapat 403 pada route khusus bendahara', function (string $url) {
    $this->actingAs(userWithRole('teller'))->get($url)->assertForbidden();
})->with('route khusus bendahara');

it('bendahara dapat membuka route tersebut', function (string $url) {
    $this->actingAs(userWithRole('bendahara'))->get($url)->assertOk();
})->with('route khusus bendahara');

it('teller dapat membuka dashboard dan pengaturan', function () {
    $teller = userWithRole('teller');

    $this->actingAs($teller)->get('/dashboard')->assertOk();
    $this->actingAs($teller)->get('/pengaturan')->assertOk();
    $this->actingAs($teller)->get('/laporan')->assertOk();
    $this->actingAs($teller)->get('/laporan/neraca')->assertOk();
});

it('admin dapat mengelola pengguna tetapi tidak membuka laporan keuangan', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->get('/pengguna')->assertOk();
    $this->actingAs($admin)->get('/laporan')->assertForbidden();
});

it('menu sidebar disesuaikan dengan peran', function () {
    $menuLabels = fn (Assert $page) => collect($page->toArray()['props']['menu'])
        ->flatMap(fn ($group) => collect($group['items'])->pluck('label'))
        ->all();

    $this->actingAs(userWithRole('teller'))->get('/dashboard')
        ->assertInertia(function (Assert $page) use ($menuLabels) {
            expect($menuLabels($page))->toContain('Pengaturan', 'Laporan keuangan')
                ->not->toContain('Pinjaman')
                ->not->toContain('Jurnal umum')
                ->not->toContain('Pengguna');
        });

    $this->actingAs(userWithRole('bendahara'))->get('/dashboard')
        ->assertInertia(function (Assert $page) use ($menuLabels) {
            expect($menuLabels($page))->toContain('Pengaturan', 'Pinjaman', 'Pengguna', 'Laporan keuangan', 'Jurnal umum', 'Bagan akun');
        });
});

it('membagikan peran dan permission ke frontend', function () {
    $this->actingAs(userWithRole('teller'))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.roles', ['teller'])
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('pengaturan.ubah_harian')
                && ! collect($permissions)->contains('pengaturan.ubah_keuangan')));
});
