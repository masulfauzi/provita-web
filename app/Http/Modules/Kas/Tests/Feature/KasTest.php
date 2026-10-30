<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Laporan\Services\LaporanService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    app(PengaturanService::class)->setMany(['tanggal_transaksi' => '2026-10-25', 'harga_buku_tabungan' => 5000]);
    $this->teller = userWithRole('teller');
    $this->bendahara = userWithRole('bendahara');
    bukaSesiKas();
    $this->bukuTabungan = Akun::peran('pendapatan_buku_tabungan');
    $this->atk = Akun::where('kode', '5-103')->firstOrFail();

    // Kas 100.000 dari setoran tabungan nasabah: uang inilah yang dipakai untuk pengeluaran.
    $this->rekening = Rekening::factory()->create();
    app(TransaksiService::class)->setor($this->rekening, 100000);
});

/**
 * @return array<string, array{0: string, 1: string}> kode akun => [debit, kredit]
 */
function barisJurnalKas(Transaksi $transaksi): array
{
    return Jurnal::where('transaksi_id', $transaksi->id)->firstOrFail()->detail()->with('akun')->get()
        ->mapWithKeys(fn ($d) => [$d->akun->kode => [$d->debit, $d->kredit]])
        ->all();
}

function kasLain(): Builder
{
    return Transaksi::query()->kasLain()->whereIn('jenis', Transaksi::JENIS_KAS_LAIN);
}

it('tidak punya tabel sendiri; akun dan pengaturan harga buku tersedia', function () {
    expect(Schema::hasTable('kas'))->toBeFalse()
        ->and($this->bukuTabungan)->kode->toBe('4-103')->kategori->toBe('pendapatan')
        ->and($this->atk->kategori)->toBe('beban')
        ->and(app(PengaturanService::class)->uang('harga_buku_tabungan'))->toBe('5000.00');
});

it('teller mencatat penjualan buku tabungan sebagai penerimaan kas di tabel transaksi', function () {
    $nasabah = Nasabah::factory()->create();

    $this->actingAs($this->teller)->get('/kas/penerimaan')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Kas::Index')
            ->where('jenis', 'kas_masuk')
            ->where('saldoKas', '100000.00')
            ->where('hargaBukuTabungan', '5000.00')
            ->where('akunOptions', fn ($akun) => collect($akun)->pluck('peran')->contains('pendapatan_buku_tabungan')
                && collect($akun)->every(fn ($item) => str_starts_with($item['label'], '4-'))));

    $this->actingAs($this->teller)->post('/kas/penerimaan', [
        'akun_id' => $this->bukuTabungan->id,
        'nominal' => 5000,
        'keterangan' => 'Penjualan buku tabungan',
        'nasabah_id' => $nasabah->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $kas = kasLain()->firstOrFail();

    expect($kas)
        ->rekening_id->toBeNull()
        ->nomor->toBe('KM-2026-000001')
        ->jenis->toBe('kas_masuk')
        ->nominal->toBe('5000.00')
        ->mutasi->toBe('5000.00')
        ->saldo_sesudah->toBe('105000.00')
        ->akun_id->toBe($this->bukuTabungan->id)
        ->nasabah_id->toBe($nasabah->id)
        ->user_id->toBe($this->teller->id)
        ->and($kas->tanggal->toDateString())->toBe('2026-10-25')
        // Akun lawannya pendapatan, bukan simpanan seperti setoran tabungan.
        ->and(barisJurnalKas($kas))->toBe(['1-101' => ['5000.00', '0.00'], '4-103' => ['0.00', '5000.00']])
        ->and(Jurnal::where('transaksi_id', $kas->id)->value('sumber'))->toBe('transaksi')
        ->and(app(JurnalService::class)->saldoKas())->toBe('105000.00')
        // Saldo tabungan nasabah tidak berubah.
        ->and($this->rekening->fresh()->saldo)->toBe('100000.00');
});

it('pengeluaran kas memakai uang tabungan di kas tanpa mengubah saldo rekening', function () {
    $this->actingAs($this->teller)->post('/kas/pengeluaran', [
        'akun_id' => $this->atk->id,
        'nominal' => 25000,
        'keterangan' => 'Belanja kertas & tinta',
    ])->assertSessionHasNoErrors();

    $kas = kasLain()->firstOrFail();

    expect($kas)->nomor->toBe('KK-2026-000001')->jenis->toBe('kas_keluar')
        ->mutasi->toBe('-25000.00')->saldo_sesudah->toBe('75000.00')
        ->and(barisJurnalKas($kas))->toBe(['5-103' => ['25000.00', '0.00'], '1-101' => ['0.00', '25000.00']])
        ->and(app(JurnalService::class)->saldoKas())->toBe('75000.00')
        ->and($this->rekening->fresh()->saldo)->toBe('100000.00')
        // Tidak muncul di buku tabungan.
        ->and($this->rekening->transaksi()->count())->toBe(1);
});

it('memvalidasi kategori, nominal, dan saldo kas', function () {
    // Akun beban tidak boleh dipakai di penerimaan, dan sebaliknya.
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->atk->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => Akun::peran('simpanan')->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');

    $this->atk->update(['is_active' => false]);
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 1000, 'keterangan' => 'x'])
        ->assertSessionHasErrors('akun_id');
    $this->atk->update(['is_active' => true]);

    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 0, 'keterangan' => ''])
        ->assertSessionHasErrors(['nominal', 'keterangan']);

    // Pengeluaran kas tidak boleh memilih nasabah.
    $this->actingAs($this->teller)->post('/kas/pengeluaran', [
        'akun_id' => $this->atk->id, 'nominal' => 1000, 'keterangan' => 'x', 'nasabah_id' => Nasabah::factory()->create()->id,
    ])->assertSessionHasErrors('nasabah_id');

    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 150000, 'keterangan' => 'Terlalu besar'])
        ->assertSessionHasErrors(['nominal' => 'Saldo kas tidak cukup: tersedia Rp 100.000, dibutuhkan Rp 150.000.']);

    expect(kasLain()->count())->toBe(0)->and(Jurnal::count())->toBe(1);
});

it('bendahara membatalkan bukti kas dengan transaksi pembalik, sekali saja', function () {
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 25000, 'keterangan' => 'Belanja']);
    $kas = kasLain()->firstOrFail();

    $this->actingAs($this->teller)->post("/kas/{$kas->id}/batal", ['alasan' => 'Salah'])->assertForbidden();

    $this->actingAs($this->bendahara)->post("/kas/{$kas->id}/batal", ['alasan' => 'Salah input'])
        ->assertSessionHas('success');

    $pembalik = $kas->pembalik()->firstOrFail();

    expect($pembalik)
        ->jenis->toBe('pembalik')->rekening_id->toBeNull()
        ->mutasi->toBe('25000.00')->saldo_sesudah->toBe('100000.00')
        ->keterangan->toBe('Salah input')->user_id->toBe($this->bendahara->id)
        ->and(barisJurnalKas($pembalik))->toEqual(['1-101' => ['25000.00', '0.00'], '5-103' => ['0.00', '25000.00']])
        ->and(Jurnal::where('transaksi_id', $pembalik->id)->value('keterangan'))->toBe('Pembatalan KK-2026-000001: Salah input')
        ->and(app(JurnalService::class)->saldoKas())->toBe('100000.00');

    $this->actingAs($this->bendahara)->post("/kas/{$kas->id}/batal", ['alasan' => 'Lagi'])
        ->assertSessionHas('error', 'KK-2026-000001 sudah dibatalkan.');
    // Pembaliknya sendiri bukan bukti kas.
    $this->actingAs($this->bendahara)->post("/kas/{$pembalik->id}/batal", ['alasan' => 'x'])->assertNotFound();

    $this->actingAs($this->teller)->get("/kas/{$kas->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('kas.batal_alasan', 'Salah input')
            ->where('kas.batal_user.name', $this->bendahara->name));

    // Tetap di riwayat, tetapi tidak dihitung di total.
    $this->actingAs($this->teller)->get('/kas/pengeluaran')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('total', '0.00'));
});

it('pembatalan penerimaan ditolak bila kas sudah tidak cukup', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku']);
    $this->actingAs($this->teller)->post('/kas/pengeluaran', ['akun_id' => $this->atk->id, 'nominal' => 103000, 'keterangan' => 'Belanja']);

    $penerimaan = kasLain()->where('jenis', 'kas_masuk')->firstOrFail();

    $this->actingAs($this->bendahara)->post("/kas/{$penerimaan->id}/batal", ['alasan' => 'x'])
        ->assertSessionHas('error', 'Saldo kas tidak cukup: tersedia Rp 2.000, dibutuhkan Rp 5.000.');

    expect($penerimaan->pembalik()->exists())->toBeFalse();
});

it('bukti kas hanya dikoreksi lewat menu Kas dan tidak bisa diubah', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku']);
    $kas = kasLain()->firstOrFail();
    $jurnal = Jurnal::where('transaksi_id', $kas->id)->firstOrFail();

    $this->actingAs($this->bendahara)->post("/jurnal/{$jurnal->id}/balik", ['alasan' => 'x'])
        ->assertSessionHas('error', 'Jurnal dari penerimaan/pengeluaran kas dikoreksi dengan membatalkan bukti kasnya.');
    $this->actingAs($this->bendahara)->post("/transaksi/{$kas->id}/balik", ['alasan' => 'x'])
        ->assertSessionHas('error', 'Penerimaan/pengeluaran kas dibatalkan lewat menu Kas.');
    $this->actingAs($this->bendahara)->get("/jurnal/{$jurnal->id}")
        ->assertInertia(fn (Assert $page) => $page->where('kas.nomor', 'KM-2026-000001'));

    expect(fn () => $kas->update(['nominal' => 1]))->toThrow(Exception::class)
        ->and(fn () => $kas->delete())->toThrow(Exception::class);
});

it('menampilkan bukti kas dan memfilter riwayat', function () {
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => $this->bukuTabungan->id, 'nominal' => 5000, 'keterangan' => 'Buku Andi']);
    $this->actingAs($this->teller)->post('/kas/penerimaan', ['akun_id' => Akun::peran('pendapatan_administrasi')->id, 'nominal' => 2000, 'keterangan' => 'Lain']);
    $kas = kasLain()->where('keterangan', 'Buku Andi')->firstOrFail();

    $this->actingAs($this->teller)->get("/kas/{$kas->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Kas::Show')
            ->where('kas.nomor', 'KM-2026-000001')
            ->where('kas.jenis', 'kas_masuk')
            ->where('kas.jurnal_id', Jurnal::where('transaksi_id', $kas->id)->value('id'))
            ->where('kas.batal_at', null)
            ->where('kop.nama_bank', 'Bank Provita'));

    // Transaksi tabungan tidak bisa dibuka sebagai bukti kas.
    $this->actingAs($this->teller)->get('/kas/'.$this->rekening->transaksi()->value('id'))->assertNotFound();

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
        ->and(collect($arusKas['penerimaan'])->pluck('nilai', 'kode')->all())->toMatchArray(['2-101' => '100000.00', '4-103' => '5000.00'])
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
