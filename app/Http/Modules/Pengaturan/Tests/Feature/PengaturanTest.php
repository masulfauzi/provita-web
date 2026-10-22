<?php

use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Models\Pengaturan;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
});

it('menampilkan pengaturan per grup', function () {
    $this->actingAs(userWithRole('bendahara'))
        ->get('/pengaturan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pengaturan::Index')
            ->has('groups', 3)
            ->where('groups.0.grup', 'harian')
            ->where('groups.0.editable', true));
});

it('bendahara dapat mengubah semua grup pengaturan', function () {
    $this->actingAs(userWithRole('bendahara'))
        ->put('/pengaturan', ['nilai' => [
            'tanggal_transaksi' => '2026-10-22',
            'uang_modal' => 1250000,
            'adm_penutupan' => '5000',
            'nama_bank' => 'Bank Provita SMKN 2',
        ]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $service = app(PengaturanService::class);
    $service->flush();

    expect($service->tanggalTransaksi()->toDateString())->toBe('2026-10-22')
        ->and($service->uang('uang_modal'))->toBe('1250000.00')
        ->and($service->uang('adm_penutupan'))->toBe('5000.00')
        ->and($service->get('nama_bank'))->toBe('Bank Provita SMKN 2');
});

it('teller hanya dapat mengubah tanggal transaksi dan uang modal', function () {
    $teller = userWithRole('teller');

    $this->actingAs($teller)
        ->put('/pengaturan', ['nilai' => ['tanggal_transaksi' => '2026-10-23', 'uang_modal' => 500000]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($teller)
        ->put('/pengaturan', ['nilai' => ['adm_penutupan' => 10000]])
        ->assertForbidden();

    $this->actingAs($teller)
        ->put('/pengaturan', ['nilai' => ['nama_bank' => 'Bank Lain']])
        ->assertForbidden();

    expect(Pengaturan::where('kunci', 'nama_bank')->value('nilai'))->toBe('Bank Provita');
});

it('memvalidasi nilai sesuai tipe', function () {
    $this->actingAs(userWithRole('bendahara'))
        ->put('/pengaturan', ['nilai' => ['tanggal_transaksi' => 'bukan-tanggal', 'uang_modal' => -1]])
        ->assertSessionHasErrors(['nilai.tanggal_transaksi', 'nilai.uang_modal']);
});

it('membagikan tanggal transaksi aktif ke semua halaman', function () {
    app(PengaturanService::class)->set('tanggal_transaksi', '2026-01-05');

    $this->actingAs(userWithRole('teller'))
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('app.tanggal_transaksi', '2026-01-05'));
});

it('mencatat perubahan pengaturan ke log aktivitas', function () {
    $user = userWithRole('bendahara');

    $this->actingAs($user)->put('/pengaturan', ['nilai' => ['uang_modal' => 750000]]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'pengaturan',
        'event' => 'updated',
        'causer_id' => $user->id,
    ]);
});

it('menolak pengguna tanpa permission', function () {
    $this->actingAs(userWithPermissions())
        ->get('/pengaturan')
        ->assertForbidden();
});
