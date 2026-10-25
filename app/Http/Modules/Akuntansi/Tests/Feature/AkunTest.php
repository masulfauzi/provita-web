<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;

beforeEach(function () {
    $this->bendahara = userWithRole('bendahara');
});

it('migrasi membuat bagan akun bawaan dengan akun sistem', function () {
    // Modul lain (mis. Kas) boleh menambah akun bawaan sendiri.
    expect(Akun::count())->toBeGreaterThanOrEqual(10)
        ->and(Akun::peran('kas')->kode)->toBe('1-101')
        ->and(Akun::peran('simpanan')->kategori)->toBe('kewajiban')
        ->and(Akun::peran('pendapatan_administrasi')->kategori)->toBe('pendapatan');
});

it('bendahara menambah dan mengubah akun', function () {
    $this->actingAs($this->bendahara)
        ->post('/akun', ['kode' => '5-199', 'nama' => 'Beban ATK', 'kategori' => 'beban'])
        ->assertSessionHasNoErrors();

    $akun = Akun::where('kode', '5-199')->firstOrFail();

    $this->actingAs($this->bendahara)
        ->put("/akun/{$akun->id}", ['kode' => '5-199', 'nama' => 'Beban alat tulis', 'kategori' => 'aset'])
        ->assertSessionHasNoErrors();

    expect($akun->refresh())->nama->toBe('Beban alat tulis')->kategori->toBe('aset');
});

it('memvalidasi kode unik dan kategori', function () {
    $this->actingAs($this->bendahara)
        ->post('/akun', ['kode' => '1-101', 'nama' => 'Kas', 'kategori' => 'harta'])
        ->assertSessionHasErrors(['kode', 'nama', 'kategori']);
});

it('akun sistem dan akun terpakai terlindungi', function () {
    $kas = Akun::peran('kas');
    $beban = Akun::where('kode', '5-102')->firstOrFail();

    app(JurnalService::class)->catat('2026-10-01', 'Beban', [
        ['akun_id' => $beban->id, 'debit' => 1000],
        ['akun_id' => $kas->id, 'kredit' => 1000],
    ]);

    $this->actingAs($this->bendahara)->put("/akun/{$kas->id}", ['kode' => '1-101', 'nama' => 'Kas', 'kategori' => 'beban'])
        ->assertSessionHasErrors('kategori');
    $this->actingAs($this->bendahara)->put("/akun/{$beban->id}", ['kode' => '5-102', 'nama' => 'Beban operasional', 'kategori' => 'aset'])
        ->assertSessionHasErrors('kategori');

    $this->actingAs($this->bendahara)->patch("/akun/{$kas->id}/aktif")->assertSessionHas('error');
    $this->actingAs($this->bendahara)->delete("/akun/{$kas->id}")->assertSessionHas('error');
    $this->actingAs($this->bendahara)->delete("/akun/{$beban->id}")->assertSessionHas('error');

    $this->actingAs($this->bendahara)->patch("/akun/{$beban->id}/aktif")->assertSessionHas('success');
    $jumlah = Akun::count();
    expect($beban->refresh()->is_active)->toBeFalse()
        ->and(Akun::whereIn('id', [$kas->id, $beban->id])->count())->toBe(2)
        ->and(Akun::count())->toBe($jumlah);
});

it('akun yang belum dipakai dapat dihapus', function () {
    $akun = Akun::where('kode', '4-102')->firstOrFail();

    $this->actingAs($this->bendahara)->delete("/akun/{$akun->id}")->assertSessionHas('success');

    expect(Akun::find($akun->id))->toBeNull();
});
