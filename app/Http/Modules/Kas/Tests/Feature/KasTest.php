<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Kas\Models\Kas;
use App\Http\Modules\Laporan\Services\LaporanService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    app(PengaturanService::class)->setMany(['tanggal_transaksi' => '2026-10-25', 'harga_buku_tabungan' => 5000]);
    $this->teller = userWithRole('teller');
    $this->bendahara = userWithRole('bendahara');
    $this->bukuTabungan = Akun::peran('pendapatan_buku_tabungan');
    $this->atk = Akun::where('kode', '5-103')->firstOrFail();

    // Modal awal 100.000 di kas agar pengeluaran bisa diuji.
    app(JurnalService::class)->catat('2026-10-01', 'Modal', [
        ['akun_id' => Akun::peran('kas')->id, 'debit' => 100000],
        ['akun_id' => Akun::peran('modal')->id, 'kredit' => 100000],
    ]);
});

/**
 * @return array<string, array{0: string, 1: string}> peran/kode akun => [debit, kredit]
 */
function barisJurnalKas(Kas $kas, string $kolom = 'jurnal_id'): array
{
    return Jurnal::findOrFail($kas->{$kolom})->detail()->with('akun')->get()
        ->mapWithKeys(fn ($d) => [$d->akun->kode => [$d->debit, $d->kredit]])
        ->all();
}

it('migrasi menambah akun buku tabungan, akun ATK, dan pengaturan harga buku', function () {
    expect($this->bukuTabungan)->kode->toBe('4-103')->kategori->toBe('pendapatan')
        ->and($this->atk->kategori)->toBe('beban')
        ->and(app(PengaturanService::class)->uang('harga_buku_tabungan'))->toBe('5000.00');
});

it('teller mencatat penjualan buku tabungan sebagai penerimaan kas', function () {
    $nasabah = Nasabah::factory()->create();

    $this->actingAs($this->teller)->get('/kas/penerimaan')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Kas::Index')
            ->where('jenis', 'masuk')
            ->where('hargaBukuTabungan', '5000.00')
            ->where('akunOptions', fn ($akun) => collect($akun)->pluck('peran')->contains('pendapatan_buku_tabungan')
                && collect($akun)->every(fn ($item) => str_starts_with($item['label'], '4-'))));

    $this->actingAs($this->teller)->post('/kas/penerimaan', [
        'akun_id' => $this->bukuTabungan->id,
        'nominal' => 5000,
        'keterangan' => 'Penjualan buku tabungan',
        'nasabah_id' => $nasabah->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $kas = Kas::firstOrFail();

    expect($kas)
        ->nomor->toBe('KM-2026-000001')
        ->jenis->toBe('masuk')
        ->nominal->toBe('5000.00')
        ->nasabah_id->toBe($nasabah->id)
        ->user_id->toBe($this->teller->id)
        ->and($kas->tanggal->toDateString())->toBe('2026-10-25')
        ->and(barisJurnalKas($kas))->toBe(['1-101' => ['5000.00', '0.00'], '4-103' => ['0.00', '5000.00']])
        ->and(Jurnal::find($kas->jurnal_id)->sumber)->toBe('kas');
});

it('teller mencatat belanja kebutuhan bank sebagai pengeluaran kas', function () {
    $this->actingAs($this->teller)->post('/kas/pengeluaran', [
        'akun_id' => $this->atk->id,
        'nominal' => 25000,
        'keterangan' => 'Belanja kertas & tinta',
    ])->assertSessionHasNoErrors();

    $kas = Kas::firstOrFail();

    expect($kas)->nomor->toBe('KK-2026-000001')->jenis->toBe('keluar')
        ->and(barisJurnalKas($kas))->toBe(['5-103' => ['25000.00', '0.00'], '1-101' => ['0.00', '25000.00']])
        ->and(app(JurnalService::class)->saldoKas())->toBe('75000.00');
});

it('memvalidasi kategori, nominal, dan saldo kas', function () {
    // Akun beban tidak boleh dipakai di penerimaan, dan sebaliknya.
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->atk->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');

    $this->atk->update(['is_active' => false]);
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');
    $this->atk->update(['is_active' => true]);

    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 0, 'keterangan' => ''])
        ->assertSessionHasErrors(['nominal', 'keterangan']);

    // Pengeluaran kas keluar tidak boleh memilih nasabah.
    $this->actingAs($this->teller)->post('/kas/pengeluaran', [
        'akun_id' => $this->atk->id, 'nominal' => 1000, 'keterangan' => 'x', 'nasabah_id' => Nasabah::factory()->create()->id,
    ])->assertSessionHasErrors('nasabah_id');

    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 150000, 'keterangan' => 'Terlalu besar'])
        ->assertSessionHasErrors(['nominal' => 'Saldo kas tidak cukup: tersedia Rp 100.000, dibutuhkan Rp 150.000.']);

    expect(Kas::count())->toBe(0)->and(Jurnal::count())->toBe(1);
});

it('bendahara membatalkan bukti kas dengan jurnal pembalik, sekali saja', function () {
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 25000, 'keterangan' => 'Belanja']);
    $kas = Kas::firstOrFail();

    $this->actingAs($this->teller)->post("/kas/{$kas->id}/batal", ['alasan' => 'Salah'])->assertForbidden();

    $this->actingAs($this->bendahara)->post("/kas/{$kas->id}/batal", ['alasan' => 'Salah input'])
        ->assertSessionHas('success');

    $kas->refresh();

    expect($kas->dibatalkan())->toBeTrue()
        ->and($kas)->batal_alasan->toBe('Salah input')->batal_user_id->toBe($this->bendahara->id)
        ->and(barisJurnalKas($kas, 'jurnal_batal_id'))->toEqual(['1-101' => ['25000.00', '0.00'], '5-103' => ['0.00', '25000.00']])
        ->and(app(JurnalService::class)->saldoKas())->toBe('100000.00');

    $this->actingAs($this->bendahara)->post("/kas/{$kas->id}/batal", ['alasan' => 'Lagi'])->assertForbidden();

    // Tidak muncul di total periode setelah dibatalkan.
    $this->actingAs($this->teller)->get('/kas/pengeluaran')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('total', '0.00'));
});

it('pembatalan penerimaan ditolak bila kas sudah tidak cukup', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku']);
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 103000, 'keterangan' => 'Belanja']);

    $penerimaan = Kas::where('jenis', 'masuk')->firstOrFail();

    $this->actingAs($this->bendahara)->post("/kas/{$penerimaan->id}/batal", ['alasan' => 'x'])
        ->assertSessionHas('error', 'Saldo kas tidak cukup: tersedia Rp 2.000, dibutuhkan Rp 5.000.');

    expect($penerimaan->refresh()->dibatalkan())->toBeFalse();
});

it('jurnal kas tidak bisa dikoreksi dari halaman jurnal dan bukti kas tidak bisa diubah', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku']);
    $kas = Kas::firstOrFail();

    $this->actingAs($this->bendahara)->post("/jurnal/{$kas->jurnal_id}/balik", ['alasan' => 'x'])
        ->assertSessionHas('error');
    $this->actingAs($this->bendahara)->get("/jurnal/{$kas->jurnal_id}")
        ->assertInertia(fn (Assert $page) => $page->where('kas.nomor', 'KM-2026-000001'));

    expect(fn () => $kas->update(['nominal' => 1]))->toThrow(Exception::class)
        ->and(fn () => $kas->delete())->toThrow(Exception::class);
});

it('menampilkan bukti kas dan memfilter riwayat', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku Andi']);
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => Akun::peran('pendapatan_administrasi')->id, 'nominal' => 2000, 'keterangan' => 'Lain']);
    $kas = Kas::where('keterangan', 'Buku Andi')->firstOrFail();

    $this->actingAs($this->teller)->get("/kas/{$kas->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Kas::Show')
            ->where('kas.nomor', 'KM-2026-000001')
            ->where('kop.nama_bank', 'Bank Provita'));

    $this->actingAs($this->teller)->get('/kas/penerimaan?search=andi')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('total', '5000.00'));
    $this->actingAs($this->teller)->get('/kas/penerimaan?akun_id='.$this->bukuTabungan->id)
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1));
    $this->actingAs($this->teller)->get('/kas/penerimaan')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 2)->where('total', '7000.00'));
});

it('masuk ke laporan laba rugi dan arus kas', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku']);
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 12000, 'keterangan' => 'ATK']);

    $laporan = app(LaporanService::class);
    $labaRugi = $laporan->labaRugi('2026-10-01', '2026-10-31');
    $arusKas = $laporan->arusKas('2026-10-01', '2026-10-31');

    expect($labaRugi['laba'])->toBe('-7000.00')
        ->and(collect($arusKas['penerimaan'])->pluck('nilai', 'kode')->all())->toMatchArray(['4-103' => '5000.00'])
        ->and(collect($arusKas['pengeluaran'])->pluck('nilai', 'kode')->all())->toMatchArray(['5-103' => '12000.00'])
        ->and($arusKas['saldo_akhir'])->toBe('93000.00');
});

it('hak akses menu kas', function () {
    $admin = userWithRole('admin');

    foreach (['/kas/penerimaan', '/kas/pengeluaran'] as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
        $this->actingAs($this->teller)->get($url)->assertOk();
        $this->actingAs($this->bendahara)->get($url)->assertOk();
    }

    $this->actingAs($admin)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertForbidden();

    $this->actingAs($this->teller)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('menu', fn ($menu) => collect($menu)->firstWhere('label', 'Kas') !== null));
});
