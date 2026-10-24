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
    return ['kode' => 'tabungan_berjangka', 'nama' => 'Tabungan Berjangka', 'prefix' => ' 3 ', ...$data];
}

it('migrasi membuat jenis tabungan bawaan sebagai rekening utama', function () {
    expect($this->tabungan)
        ->nama->toBe('Tabungan')
        ->prefix->toBeNull()
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
    expect($jenis->prefix)->toBe('3');

    $this->actingAs($user)->put("/jenis-rekening/{$jenis->id}", dataJenis(['nama' => 'Tabungan Wisata', 'prefix' => '4']))
        ->assertSessionHasNoErrors();

    expect($jenis->refresh())->nama->toBe('Tabungan Wisata')->prefix->toBe('4');
})->with(['bendahara', 'admin']);

it('teller tidak dapat mengelola jenis rekening', function () {
    $teller = userWithRole('teller');

    $this->actingAs($teller)->get('/jenis-rekening')->assertForbidden();
    $this->actingAs($teller)->post('/jenis-rekening', dataJenis())->assertForbidden();
    $this->actingAs($teller)->put("/jenis-rekening/{$this->tabungan->id}", dataJenis())->assertForbidden();
});

it('memvalidasi kode, nama, dan prefix', function () {
    $bendahara = userWithRole('bendahara');

    $this->actingAs($bendahara)
        ->post('/jenis-rekening', ['kode' => 'Bukan Kode!', 'nama' => 'Tabungan', 'prefix' => 'TAB-'])
        ->assertSessionHasErrors(['kode', 'nama', 'prefix']);

    foreach (['1a', '-2', '123456'] as $prefix) {
        $this->actingAs($bendahara)->post('/jenis-rekening', dataJenis(['prefix' => $prefix]))
            ->assertSessionHasErrors('prefix');
    }

    JenisRekening::factory()->create(['prefix' => '5']);
    $this->actingAs($bendahara)->post('/jenis-rekening', dataJenis(['kode' => 'tabungan', 'prefix' => '5']))
        ->assertSessionHasErrors(['kode', 'prefix']);
});

it('prefix kosong (rekening utama) hanya boleh satu jenis', function () {
    $bendahara = userWithRole('bendahara');

    $this->actingAs($bendahara)->post('/jenis-rekening', dataJenis(['prefix' => '']))
        ->assertSessionHasErrors(['prefix' => 'Rekening utama (tanpa prefix) sudah ada: Tabungan. Isi prefix angka.']);

    // Tabungan sendiri tetap boleh disimpan tanpa prefix.
    $this->actingAs($bendahara)->put("/jenis-rekening/{$this->tabungan->id}", ['nama' => 'Tabungan', 'prefix' => null])
        ->assertSessionHasNoErrors();

    // Bila Tabungan diberi prefix, jenis lain boleh menjadi rekening utama.
    $this->actingAs($bendahara)->put("/jenis-rekening/{$this->tabungan->id}", ['nama' => 'Tabungan', 'prefix' => '1'])
        ->assertSessionHasNoErrors();
    $this->actingAs($bendahara)->post('/jenis-rekening', dataJenis(['prefix' => '']))
        ->assertSessionHasNoErrors();

    expect(JenisRekening::kode('tabungan_berjangka')->prefix)->toBeNull();
});

it('jenis baru dapat dipilih teller dan nomornya memakai prefix sendiri', function () {
    $jenis = JenisRekening::factory()->create(['nama' => 'Tabungan Berjangka', 'prefix' => '7']);
    $teller = userWithRole('teller');
    $nasabah = Nasabah::factory()->create(['nisn' => '0098765432']);

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
        ->nomor->toBe('70098765432')
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

it('prefix boleh diubah tanpa mengubah nomor rekening lama, tetapi jenis terpakai tidak bisa dihapus', function () {
    $wisata = JenisRekening::factory()->create(['nama' => 'Tabungan Wisata', 'prefix' => '2']);
    $rekening = Rekening::factory()->create(['nomor' => '20012345678', 'jenis_rekening_id' => $wisata->id]);
    $bendahara = userWithRole('bendahara');

    $this->actingAs($bendahara)
        ->put("/jenis-rekening/{$wisata->id}", ['nama' => 'Tabungan Wisata', 'prefix' => '8'])
        ->assertSessionHasNoErrors();

    $this->actingAs($bendahara)->delete("/jenis-rekening/{$wisata->id}")
        ->assertSessionHas('error');

    expect($wisata->refresh()->prefix)->toBe('8')
        ->and($rekening->refresh()->nomor)->toBe('20012345678');
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
