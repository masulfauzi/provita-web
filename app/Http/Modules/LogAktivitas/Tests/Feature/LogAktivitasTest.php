<?php

use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('bendahara melihat log aktivitas beserta nilai lama dan baru', function () {
    $this->seed(PengaturanSeeder::class);
    $bendahara = userWithRole('bendahara');

    $this->actingAs($bendahara)->put('/pengaturan', ['nilai' => ['uang_modal' => 200000]]);

    $this->actingAs($bendahara)
        ->get('/log-aktivitas?log_name=pengaturan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LogAktivitas::Index')
            ->where('items.data.0.event', 'updated')
            ->where('items.data.0.causer.username', $bendahara->username)
            ->where('items.data.0.changes.old.nilai', '0.00')
            ->where('items.data.0.changes.attributes.nilai', '200000.00'));
});

it('teller tidak dapat melihat log aktivitas', function () {
    $this->actingAs(userWithRole('teller'))->get('/log-aktivitas')->assertForbidden();
});
