<?php

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
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
    $this->nasabah = Nasabah::factory()->create();
});

function bukaRekening(array $data = []): TestResponse
{
    return test()->actingAs(test()->teller)->post('/rekening', [
        'nasabah_id' => test()->nasabah->id,
        'jenis' => 'tabungan',
        'setoran_awal' => 50000,
        ...$data,
    ]);
}

it('teller membuka rekening tabungan dengan setoran awal', function () {
    bukaRekening()->assertRedirect();

    $rekening = Rekening::firstOrFail();

    expect($rekening->nomor)->toBe('TAB-000001')
        ->and($rekening->jenis)->toBe('tabungan')
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

    expect($this->nasabah->rekening()->orderBy('nomor')->pluck('nomor')->all())->toBe(['TAB-000001', 'TAB-000002']);

    $this->actingAs($this->teller)
        ->get("/nasabah/{$this->nasabah->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Nasabah::Show')
            ->has('rekening', 2)
            ->where('rekening.0.nomor', 'TAB-000001')
            ->where('rekening.1.saldo', '25000.00'));
});

it('jenis rekening default tabungan', function () {
    $this->actingAs($this->teller)
        ->get("/rekening/create?nasabah={$this->nasabah->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rekening::Create')
            ->where('nasabah.id', $this->nasabah->id)
            ->where('jenisOptions.0.value', 'tabungan')
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
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.nomor', 'TAB-000001'));

    $this->actingAs($this->teller)
        ->getJson('/rekening/cari?q=TAB-000001')
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
