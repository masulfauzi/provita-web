<?php

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->pengaturan->setMany(['setoran_awal_minimal' => 10000, 'adm_penutupan' => 5000]);
    $this->teller = userWithRole('teller');
    bukaSesiKas();
    $this->nasabah = Nasabah::factory()->create(['nisn' => '0012345678', 'nis' => '12345']);
});

function bukaRekening(array $data = []): TestResponse
{
    return test()->actingAs(test()->teller)->post('/rekening', [
        'nasabah_id' => test()->nasabah->id,
        'jenis_rekening_id' => JenisRekening::kode('tabungan')->id,
        'setoran_awal' => 50000,
        ...$data,
    ]);
}

it('teller membuka rekening tabungan dengan setoran awal', function () {
    bukaRekening()->assertRedirect();

    $rekening = Rekening::firstOrFail();

    expect($rekening->nomor)->toBe('0012345678')
        ->and($rekening->jenisRekening->kode)->toBe('tabungan')
        ->and($rekening->nasabah_id)->toBe($this->nasabah->id)
        ->and($rekening->saldo)->toBe('50000.00')
        ->and($rekening->transaksi()->first())
        ->jenis->toBe(Transaksi::SETOR)
        ->keterangan->toBe('Setoran awal')
        ->user_id->toBe($this->teller->id);
});

it('satu nasabah dapat memiliki lebih dari satu rekening', function () {
    bukaRekening()->assertRedirect();
    bukaRekening(['setoran_awal' => 25000])->assertRedirect();

    expect($this->nasabah->rekening()->orderBy('nomor')->pluck('nomor')->all())->toBe(['0012345678', '00123456781']);

    $this->actingAs($this->teller)
        ->get("/nasabah/{$this->nasabah->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Nasabah::Show')
            ->has('rekening', 2)
            ->where('rekening.0.nomor', '0012345678')
            ->where('rekening.1.saldo', '25000.00'));
});

it('jenis rekening default tabungan', function () {
    $this->actingAs($this->teller)
        ->get("/rekening/create?nasabah={$this->nasabah->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rekening::Create')
            ->where('nasabah.id', $this->nasabah->id)
            ->where('jenisOptions.0.value', JenisRekening::kode('tabungan')->id)
            ->where('jenisOptions.0.label', 'Tabungan')
            ->where('setoranAwalMinimal', '10000.00'));
});

it('menolak setoran awal di bawah minimal', function () {
    bukaRekening(['setoran_awal' => 5000])->assertSessionHasErrors('setoran_awal');

    expect(Rekening::count())->toBe(0);
});

it('menolak membuka rekening untuk nasabah nonaktif', function () {
    $this->nasabah->update(['is_active' => false]);

    bukaRekening()->assertSessionHasErrors('nasabah_id');
});

it('buku tabungan menampilkan mutasi terbaru di atas', function () {
    bukaRekening();
    $rekening = Rekening::firstOrFail();
    app(TransaksiService::class)->tarik($rekening, 20000, $this->teller);

    $this->actingAs($this->teller)
        ->get("/rekening/{$rekening->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rekening::Show')
            ->where('rekening.saldo', '30000.00')
            ->where('rekening.nasabah.id', $this->nasabah->id)
            ->has('mutasi.data', 2)
            ->where('mutasi.data.0.urut', 2)
            ->where('mutasi.data.0.jenis', 'tarik')
            ->where('mutasi.data.0.saldo_sesudah', '30000.00')
            ->where('mutasi.data.0.petugas', $this->teller->name)
            ->where('penutupan.biaya', '5000.00')
            ->where('penutupan.diserahkan', '25000.00'));
});

it('daftar rekening dapat dicari berdasarkan nama nasabah', function () {
    bukaRekening();
    Rekening::factory()->create();

    $this->actingAs($this->teller)
        ->get('/rekening?search='.urlencode($this->nasabah->nama))
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.nomor', '0012345678'));

    $this->actingAs($this->teller)
        ->getJson('/rekening/cari?q=0012345678')
        ->assertOk()
        ->assertJsonPath('0.nasabah.nama', $this->nasabah->nama);
});

it('menutup rekening memotong biaya administrasi dan mengosongkan saldo', function () {
    bukaRekening();
    $rekening = Rekening::firstOrFail();

    $this->actingAs($this->teller)->post("/rekening/{$rekening->id}/tutup")->assertSessionHas('success');

    $rekening->refresh();
    expect($rekening->status)->toBe(Rekening::TUTUP)
        ->and($rekening->saldo)->toBe('0.00')
        ->and($rekening->tanggal_tutup)->not->toBeNull()
        ->and($rekening->transaksi()->orderBy('urut')->pluck('jenis')->all())->toBe(['setor', 'biaya', 'penutupan'])
        ->and($rekening->transaksi()->where('jenis', 'penutupan')->value('nominal'))->toBe('45000.00');

    $this->artisan('saldo:rekonsiliasi')->assertSuccessful();
});

it('biaya penutupan tidak melebihi saldo', function () {
    $this->pengaturan->set('setoran_awal_minimal', 0);
    bukaRekening(['setoran_awal' => 3000]);
    $rekening = Rekening::firstOrFail();

    $hasil = app(TransaksiService::class)->tutup($rekening);

    expect($hasil)->toBe(['biaya' => '3000.00', 'diserahkan' => '0.00'])
        ->and($rekening->fresh()->saldo)->toBe('0.00');
});

it('rekening yang sudah ditutup tidak dapat menerima transaksi', function () {
    bukaRekening();
    $rekening = Rekening::firstOrFail();
    $this->actingAs($this->teller)->post("/rekening/{$rekening->id}/tutup");

    $this->actingAs($this->teller)
        ->post('/transaksi/setor', ['rekening_id' => $rekening->id, 'nominal' => 1000])
        ->assertSessionHasErrors(['nominal' => 'Rekening tidak aktif.']);
});

it('nasabah yang memiliki rekening tidak dapat dihapus', function () {
    bukaRekening();

    $this->actingAs(userWithRole('bendahara'))
        ->delete("/nasabah/{$this->nasabah->id}")
        ->assertSessionHas('error');

    expect(Nasabah::find($this->nasabah->id))->not->toBeNull();
});

it('admin tidak dapat mengakses rekening', function () {
    $this->actingAs(userWithRole('admin'))->get('/rekening')->assertForbidden();
});

it('menu tabungan tampil untuk teller', function () {
    $this->actingAs($this->teller)->get('/dashboard')
        ->assertInertia(function (Assert $page) {
            $tabungan = collect($page->toArray()['props']['menu'])->firstWhere('label', 'Tabungan');
            expect(collect($tabungan['items'])->pluck('label')->all())->toBe(['Setor / Tarik', 'Rekening']);
        });
});

it('nomor rekening = prefix jenis + NISN, dengan suffix urut bila sudah dipakai', function () {
    $wisata = JenisRekening::factory()->create(['nama' => 'Tabungan Wisata', 'prefix' => '2']);

    bukaRekening()->assertSessionHasNoErrors();
    bukaRekening()->assertSessionHasNoErrors();
    bukaRekening(['jenis_rekening_id' => $wisata->id])->assertSessionHasNoErrors();
    bukaRekening(['jenis_rekening_id' => $wisata->id])->assertSessionHasNoErrors();
    bukaRekening()->assertSessionHasNoErrors();

    expect($this->nasabah->rekening()->orderBy('created_at')->orderBy('nomor')->pluck('nomor')->all())
        ->toEqualCanonicalizing(['0012345678', '00123456781', '20012345678', '200123456781', '00123456782']);
});

it('nomor dasar memakai NIS bila siswa belum punya NISN, dan NIP untuk guru', function () {
    $siswa = Nasabah::factory()->create(['nisn' => null, 'nis' => '2324.10.055']);
    $guru = Nasabah::factory()->create(['jenis' => 'guru', 'nisn' => null, 'nis' => '19800101 200501 1 001', 'kelas' => null]);

    bukaRekening(['nasabah_id' => $siswa->id])->assertSessionHasNoErrors();
    bukaRekening(['nasabah_id' => $guru->id])->assertSessionHasNoErrors();

    expect($siswa->rekening()->value('nomor'))->toBe('232410055')
        ->and($guru->rekening()->value('nomor'))->toBe('198001012005011001');
});

it('menolak membuka rekening bila nasabah belum punya NISN/NIS', function () {
    $siswa = Nasabah::factory()->create(['nisn' => null, 'nis' => null]);
    $guru = Nasabah::factory()->create(['jenis' => 'guru', 'nisn' => null, 'nis' => null]);

    bukaRekening(['nasabah_id' => $siswa->id])->assertSessionHasErrors([
        'nasabah_id' => "Lengkapi NISN atau NIS {$siswa->nama} terlebih dahulu; nomor rekening dibuat dari NISN/NIS.",
    ]);
    bukaRekening(['nasabah_id' => $guru->id])->assertSessionHasErrors([
        'nasabah_id' => "Lengkapi NIP / No. pegawai {$guru->nama} terlebih dahulu; nomor rekening dibuat dari NIP.",
    ]);

    expect(Rekening::count())->toBe(0);
});

it('nomor yang bentrok dengan nasabah lain mendapat suffix', function () {
    // Prefix 2 + NISN 0012345678 = 20012345678, sama dengan rekening utama ke-2 milik NISN 2001234567.
    $wisata = JenisRekening::factory()->create(['prefix' => '2']);
    $lain = Nasabah::factory()->create(['nisn' => '2001234567']);
    Rekening::factory()->create(['nomor' => '2001234567', 'nasabah_id' => $lain->id]);
    Rekening::factory()->create(['nomor' => '20012345678', 'nasabah_id' => $lain->id]);

    bukaRekening(['jenis_rekening_id' => $wisata->id])->assertSessionHasNoErrors();

    expect($this->nasabah->rekening()->value('nomor'))->toBe('200123456781');
});
