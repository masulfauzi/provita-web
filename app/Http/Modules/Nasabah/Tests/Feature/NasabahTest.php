<?php

use App\Http\Modules\Nasabah\Models\Nasabah;
use Inertia\Testing\AssertableInertia as Assert;

function dataNasabah(array $override = []): array
{
    return [
        'nis' => '12345',
        'nama' => 'Siti Aminah',
        'jenis' => 'siswa',
        'jenis_kelamin' => 'P',
        'kelas' => 'xi  akl 2',
        'tempat_lahir' => 'Semarang',
        'tanggal_lahir' => '2009-03-14',
        'alamat' => 'Jl. Pemuda 1',
        'no_hp' => '081234567890',
        'nama_orang_tua' => 'Budi',
        ...$override,
    ];
}

beforeEach(function () {
    $this->teller = userWithRole('teller');
});

it('teller melihat daftar nasabah', function () {
    Nasabah::factory()->count(3)->create();

    $this->actingAs($this->teller)
        ->get('/nasabah')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Nasabah::Index')
            ->has('items.data', 3)
            ->has('jenisOptions', 3));
});

it('mencari dan memfilter nasabah', function () {
    Nasabah::factory()->create(['nama' => 'Andi Wijaya', 'kelas' => 'X TKJ 1']);
    Nasabah::factory()->create(['nama' => 'Budi Santoso', 'kelas' => 'XI AKL 2']);
    Nasabah::factory()->nonaktif()->create(['nama' => 'Citra Lestari', 'kelas' => 'XI AKL 2']);

    $this->actingAs($this->teller)->get('/nasabah?search=andi')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.nama', 'Andi Wijaya'));

    $this->actingAs($this->teller)->get('/nasabah?kelas=XI+AKL+2&status=aktif')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.nama', 'Budi Santoso'));
});

it('teller mendaftarkan nasabah dengan nomor otomatis berurutan', function () {
    $this->actingAs($this->teller)->post('/nasabah', dataNasabah())->assertRedirect();
    $this->actingAs($this->teller)->post('/nasabah', dataNasabah(['nis' => '12346', 'nama' => 'Rina']))->assertRedirect();

    $siti = Nasabah::where('nama', 'Siti Aminah')->firstOrFail();

    expect($siti->nomor)->toBe('NSB-000001')
        ->and($siti->kelas)->toBe('XI AKL 2')
        ->and($siti->tanggal_lahir->toDateString())->toBe('2009-03-14')
        ->and(Nasabah::where('nama', 'Rina')->value('nomor'))->toBe('NSB-000002');
});

it('teller mengubah data nasabah', function () {
    $nasabah = Nasabah::factory()->create();

    $this->actingAs($this->teller)
        ->put("/nasabah/{$nasabah->id}", dataNasabah(['nama' => 'Nama Baru', 'nis' => $nasabah->nis]))
        ->assertRedirect("/nasabah/{$nasabah->id}");

    expect($nasabah->fresh()->nama)->toBe('Nama Baru');
});

it('memvalidasi data nasabah', function () {
    Nasabah::factory()->create(['nis' => '12345']);

    $this->actingAs($this->teller)
        ->post('/nasabah', dataNasabah(['nama' => '', 'jenis' => 'alien', 'tanggal_lahir' => '2999-01-01', 'no_hp' => 'abc']))
        ->assertSessionHasErrors(['nis', 'nama', 'jenis', 'tanggal_lahir', 'no_hp']);
});

it('teller tidak dapat menonaktifkan atau menghapus nasabah', function () {
    $nasabah = Nasabah::factory()->create();

    $this->actingAs($this->teller)->patch("/nasabah/{$nasabah->id}/aktif")->assertForbidden();
    $this->actingAs($this->teller)->delete("/nasabah/{$nasabah->id}")->assertForbidden();

    expect($nasabah->fresh()->is_active)->toBeTrue();
});

it('bendahara dapat menonaktifkan dan menghapus nasabah', function () {
    $bendahara = userWithRole('bendahara');
    $nasabah = Nasabah::factory()->create();

    $this->actingAs($bendahara)->patch("/nasabah/{$nasabah->id}/aktif")->assertRedirect();
    expect($nasabah->fresh()->is_active)->toBeFalse();

    $this->actingAs($bendahara)->delete("/nasabah/{$nasabah->id}")->assertRedirect('/nasabah');
    expect(Nasabah::find($nasabah->id))->toBeNull();
});

it('admin tidak memiliki akses ke data nasabah', function () {
    $this->actingAs(userWithRole('admin'))->get('/nasabah')->assertForbidden();
});

it('menu nasabah tampil untuk teller', function () {
    $this->actingAs($this->teller)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('menu.0.label', 'Data Master')->where('menu.0.items.0.label', 'Nasabah'));
});

it('mencatat perubahan data nasabah ke log aktivitas', function () {
    $nasabah = Nasabah::factory()->create(['no_hp' => '0811111111']);

    $this->actingAs($this->teller)->put("/nasabah/{$nasabah->id}", dataNasabah(['nis' => $nasabah->nis, 'no_hp' => '0822222222']));

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'nasabah',
        'event' => 'updated',
        'subject_id' => $nasabah->id,
        'causer_id' => $this->teller->id,
    ]);
});

it('menyimpan dan mengubah NISN siswa', function () {
    $this->actingAs($this->teller)->post('/nasabah', dataNasabah(['nisn' => '0012 345 678']))
        ->assertSessionHasNoErrors();

    $nasabah = Nasabah::where('nama', 'Siti Aminah')->firstOrFail();
    expect($nasabah->nisn)->toBe('0012345678');

    $this->actingAs($this->teller)->get("/nasabah/{$nasabah->id}")
        ->assertInertia(fn (Assert $page) => $page->where('item.nisn', '0012345678'));

    // Menyimpan ulang NISN miliknya sendiri tidak dianggap duplikat.
    $this->actingAs($this->teller)->put("/nasabah/{$nasabah->id}", dataNasabah(['nisn' => '0012345678', 'no_hp' => '0899999999']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->teller)->put("/nasabah/{$nasabah->id}", dataNasabah(['nisn' => '0098765432']))
        ->assertSessionHasNoErrors();
    expect($nasabah->fresh()->nisn)->toBe('0098765432');
});

it('NISN boleh kosong, harus 10 digit, dan unik', function () {
    $this->actingAs($this->teller)->post('/nasabah', dataNasabah(['nisn' => '']))->assertSessionHasNoErrors();
    expect(Nasabah::firstOrFail()->nisn)->toBeNull();

    Nasabah::factory()->create(['nisn' => '0011111111']);

    foreach (['12345', '00123456789', '00123abc45', '0011111111'] as $nisn) {
        $this->actingAs($this->teller)->post('/nasabah', dataNasabah(['nis' => '99999', 'nisn' => $nisn]))
            ->assertSessionHasErrors('nisn');
    }
});

it('NISN diabaikan untuk nasabah bukan siswa', function () {
    $this->actingAs($this->teller)
        ->post('/nasabah', dataNasabah(['jenis' => 'guru', 'nisn' => '0012345678']))
        ->assertSessionHasNoErrors();

    expect(Nasabah::firstOrFail()->nisn)->toBeNull();
});

it('mencari nasabah berdasarkan NISN', function () {
    Nasabah::factory()->create(['nama' => 'Dewi Kartika', 'nisn' => '0055512345']);
    Nasabah::factory()->count(2)->create();

    $this->actingAs($this->teller)->get('/nasabah?search=0055512345')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.nama', 'Dewi Kartika'));

    $this->actingAs($this->teller)->getJson('/nasabah/cari?q=0055512345')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nisn', '0055512345');
});
