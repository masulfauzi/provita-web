<?php

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->tabungan = JenisRekening::kode('tabungan');
});

function dataJenis(array $data = []): array
{
    return ['kode' => 'tabungan_berjangka', 'nama' => 'Tabungan Berjangka', 'prefix' => 'tbj-', ...$data];
}

it('migrasi membuat jenis tabungan bawaan', function () {
    expect($this->tabungan)
        ->nama->toBe('Tabungan')
        ->prefix->toBe('TAB-')
        ->is_active->toBeTrue();
});

it('bendahara dan admin dapat mengelola jenis rekening', function (string $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->get('/jenis-rekening')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rekening::JenisRekening')
            ->where('items.0.kode', 'tabungan'));

    $this->actingAs($user)->post('/jenis-rekening', dataJenis())->assertRedirect()->assertSessionHasNoErrors();

    $jenis = JenisRekening::kode('tabungan_berjangka');
    expect($jenis->prefix)->toBe('TBJ-');

    $this->actingAs($user)->put("/jenis-rekening/{$jenis->id}", dataJenis(['nama' => 'Tabungan Wisata', 'prefix' => 'TWS-']))
        ->assertSessionHasNoErrors();

    expect($jenis->refresh())->nama->toBe('Tabungan Wisata')->prefix->toBe('TWS-');
})->with(['bendahara', 'admin']);

it('teller tidak dapat mengelola jenis rekening', function () {
    $teller = userWithRole('teller');

    $this->actingAs($teller)->get('/jenis-rekening')->assertForbidden();
    $this->actingAs($teller)->post('/jenis-rekening', dataJenis())->assertForbidden();
    $this->actingAs($teller)->put("/jenis-rekening/{$this->tabungan->id}", dataJenis())->assertForbidden();
});

it('memvalidasi kode, nama, dan prefix', function () {
    $this->actingAs(userWithRole('bendahara'))
        ->post('/jenis-rekening', ['kode' => 'Bukan Kode!', 'nama' => 'Tabungan', 'prefix' => 'TAB'])
        ->assertSessionHasErrors(['kode', 'nama', 'prefix']);

    $this->actingAs(userWithRole('bendahara'))
        ->post('/jenis-rekening', dataJenis(['kode' => 'tabungan', 'prefix' => 'TAB-']))
        ->assertSessionHasErrors(['kode', 'prefix']);
});

it('jenis baru dapat dipilih teller dan nomornya memakai prefix sendiri', function () {
    $jenis = JenisRekening::factory()->create(['nama' => 'Tabungan Berjangka', 'prefix' => 'TBJ-']);
    $teller = userWithRole('teller');
    $nasabah = Nasabah::factory()->create();

    $this->actingAs($teller)->get('/rekening/create')
        ->assertInertia(fn (Assert $page) => $page
            ->has('jenisOptions', 2)
            ->where('jenisOptions.0.label', 'Tabungan')
            ->where('jenisOptions.1.label', 'Tabungan Berjangka'));

    $this->actingAs($teller)->post('/rekening', [
        'nasabah_id' => $nasabah->id,
        'jenis_rekening_id' => $jenis->id,
        'setoran_awal' => 50000,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Rekening::firstOrFail())
        ->nomor->toBe('TBJ-000001')
        ->jenis_rekening_id->toBe($jenis->id);
});

it('jenis nonaktif tidak muncul di form dan ditolak saat buka rekening', function () {
    $jenis = JenisRekening::factory()->nonaktif()->create();
    $teller = userWithRole('teller');

    $this->actingAs($teller)->get('/rekening/create')
        ->assertInertia(fn (Assert $page) => $page->has('jenisOptions', 1));

    $this->actingAs($teller)->post('/rekening', [
        'nasabah_id' => Nasabah::factory()->create()->id,
        'jenis_rekening_id' => $jenis->id,
        'setoran_awal' => 50000,
    ])->assertSessionHasErrors('jenis_rekening_id');

    // Filter daftar rekening tetap menampilkan jenis nonaktif agar rekening lamanya bisa dicari.
    $this->actingAs($teller)->get('/rekening')
        ->assertInertia(fn (Assert $page) => $page->has('jenisOptions', 2));
});

it('bendahara dapat menonaktifkan dan mengaktifkan jenis rekening', function () {
    $bendahara = userWithRole('bendahara');

    $this->actingAs($bendahara)->patch("/jenis-rekening/{$this->tabungan->id}/aktif")->assertRedirect();
    expect($this->tabungan->refresh()->is_active)->toBeFalse();

    $this->actingAs($bendahara)->patch("/jenis-rekening/{$this->tabungan->id}/aktif");
    expect($this->tabungan->refresh()->is_active)->toBeTrue();
});

it('prefix tidak bisa diubah dan jenis tidak bisa dihapus setelah dipakai rekening', function () {
    Rekening::factory()->create(['jenis_rekening_id' => $this->tabungan->id]);
    $bendahara = userWithRole('bendahara');

    $this->actingAs($bendahara)
        ->put("/jenis-rekening/{$this->tabungan->id}", ['nama' => 'Tabungan Siswa', 'prefix' => 'TSW-'])
        ->assertSessionHasErrors('prefix');

    $this->actingAs($bendahara)
        ->put("/jenis-rekening/{$this->tabungan->id}", ['nama' => 'Tabungan Siswa', 'prefix' => 'TAB-'])
        ->assertSessionHasNoErrors();

    $this->actingAs($bendahara)->delete("/jenis-rekening/{$this->tabungan->id}")
        ->assertSessionHas('error');

    expect($this->tabungan->refresh())->nama->toBe('Tabungan Siswa')->prefix->toBe('TAB-');
});

it('jenis yang belum dipakai dapat dihapus', function () {
    $jenis = JenisRekening::factory()->create();

    $this->actingAs(userWithRole('bendahara'))->delete("/jenis-rekening/{$jenis->id}")
        ->assertSessionHas('success');

    expect(JenisRekening::find($jenis->id))->toBeNull();
});

it('menu jenis rekening hanya untuk bendahara dan admin', function (string $role, bool $tampil) {
    $this->actingAs(userWithRole($role))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('menu', fn ($menu) => collect($menu)
            ->flatMap(fn ($group) => $group['items'] ?? [$group])
            ->contains(fn ($item) => ($item['label'] ?? null) === 'Jenis Rekening') === $tampil));
})->with([
    ['bendahara', true],
    ['admin', true],
    ['teller', false],
]);
