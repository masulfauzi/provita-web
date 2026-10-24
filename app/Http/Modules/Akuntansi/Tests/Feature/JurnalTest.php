<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Services\RekeningService;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    app(PengaturanService::class)->setMany(['tanggal_transaksi' => '2026-10-22', 'adm_penutupan' => 5000]);
    $this->bendahara = userWithRole('bendahara');
});

/**
 * Baris jurnal sebuah transaksi dalam bentuk [peran/kode akun => [debit, kredit]].
 */
function barisJurnal(Transaksi $transaksi): array
{
    return Jurnal::where('transaksi_id', $transaksi->id)->firstOrFail()
        ->detail()->with('akun')->get()
        ->mapWithKeys(fn ($d) => [$d->akun->peran => [$d->debit, $d->kredit]])
        ->all();
}

function bukaRekeningUji(int $setoran = 100000)
{
    return app(RekeningService::class)->buka(
        Nasabah::factory()->create(),
        JenisRekening::kode('tabungan'),
        $setoran,
    );
}

it('mencatat jurnal otomatis untuk setiap jenis transaksi tabungan', function () {
    $rekening = bukaRekeningUji();
    $transaksi = app(TransaksiService::class);

    $setor = $rekening->transaksi()->firstOrFail();
    $tarik = $transaksi->tarik($rekening, 20000);
    $koreksi = $transaksi->balik($tarik);
    $transaksi->tutup($rekening);
    $biaya = $rekening->transaksi()->where('jenis', 'biaya')->firstOrFail();
    $penutupan = $rekening->transaksi()->where('jenis', 'penutupan')->firstOrFail();

    expect(barisJurnal($setor))->toBe(['kas' => ['100000.00', '0.00'], 'simpanan' => ['0.00', '100000.00']])
        ->and(barisJurnal($tarik))->toBe(['simpanan' => ['20000.00', '0.00'], 'kas' => ['0.00', '20000.00']])
        ->and(barisJurnal($koreksi))->toBe(['kas' => ['20000.00', '0.00'], 'simpanan' => ['0.00', '20000.00']])
        ->and(barisJurnal($biaya))->toBe(['simpanan' => ['5000.00', '0.00'], 'pendapatan_administrasi' => ['0.00', '5000.00']])
        ->and(barisJurnal($penutupan))->toBe(['simpanan' => ['95000.00', '0.00'], 'kas' => ['0.00', '95000.00']]);

    expect(Jurnal::where('sumber', 'transaksi')->count())->toBe(Transaksi::count())
        ->and(Jurnal::firstOrFail())
        ->tanggal->toDateString()->toBe('2026-10-22')
        ->nomor->toBe('JU-2026-000001');
});

it('transaksi yang ditolak tidak meninggalkan jurnal', function () {
    $rekening = bukaRekeningUji(10000);

    expect(fn () => app(TransaksiService::class)->tarik($rekening, 50000))->toThrow(TransaksiDitolakException::class);

    expect(Jurnal::count())->toBe(1)->and(Transaksi::count())->toBe(1);
});

it('bendahara mencatat jurnal umum yang seimbang', function () {
    $kas = Akun::peran('kas');
    $modal = Akun::peran('modal');

    $this->actingAs($this->bendahara)->post('/jurnal', [
        'tanggal' => '2026-10-01',
        'keterangan' => 'Setoran modal awal',
        'baris' => [
            ['akun_id' => $kas->id, 'debit' => 1000000],
            ['akun_id' => $modal->id, 'kredit' => 1000000],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $jurnal = Jurnal::firstOrFail();

    expect($jurnal)
        ->sumber->toBe('manual')
        ->nomor->toBe('JU-2026-000001')
        ->user_id->toBe($this->bendahara->id)
        ->and($jurnal->detail()->pluck('debit')->all())->toBe(['1000000.00', '0.00']);

    $this->actingAs($this->bendahara)->get("/jurnal/{$jurnal->id}")
        ->assertInertia(fn (Assert $page) => $page->component('Akuntansi::Jurnal/Show')->has('jurnal.detail', 2));
});

it('menolak jurnal umum yang tidak valid', function (array $baris, string $error) {
    $this->actingAs($this->bendahara)->post('/jurnal', [
        'tanggal' => '2026-10-01',
        'keterangan' => 'Uji',
        'baris' => $baris,
    ])->assertSessionHasErrors($error);

    expect(Jurnal::count())->toBe(0);
})->with([
    'tidak seimbang' => [fn () => [
        ['akun_id' => Akun::peran('kas')->id, 'debit' => 1000],
        ['akun_id' => Akun::peran('modal')->id, 'kredit' => 900],
    ], 'baris'],
    'satu baris' => [fn () => [['akun_id' => Akun::peran('kas')->id, 'debit' => 1000]], 'baris'],
    'debit dan kredit di baris sama' => [fn () => [
        ['akun_id' => Akun::peran('kas')->id, 'debit' => 1000, 'kredit' => 1000],
        ['akun_id' => Akun::peran('modal')->id, 'kredit' => 0],
    ], 'baris.0.debit'],
    'akun nonaktif' => [function () {
        $akun = Akun::where('kode', '5-102')->firstOrFail();
        $akun->update(['is_active' => false]);

        return [
            ['akun_id' => $akun->id, 'debit' => 1000],
            ['akun_id' => Akun::peran('kas')->id, 'kredit' => 1000],
        ];
    }, 'baris.0.akun_id'],
]);

it('jurnal umum dapat dikoreksi sekali dengan jurnal pembalik', function () {
    $jurnal = app(JurnalService::class)->catat('2026-10-01', 'Beban ATK', [
        ['akun_id' => Akun::where('kode', '5-102')->value('id'), 'debit' => 15000],
        ['akun_id' => Akun::peran('kas')->id, 'kredit' => 15000],
    ]);

    $this->actingAs($this->bendahara)->post("/jurnal/{$jurnal->id}/balik", ['alasan' => 'Salah nominal'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $pembalik = Jurnal::where('pembalik_dari_id', $jurnal->id)->firstOrFail();

    expect($pembalik)
        ->keterangan->toBe('Koreksi JU-2026-000001: Salah nominal')
        ->tanggal->toDateString()->toBe('2026-10-22')
        ->and($pembalik->detail()->pluck('kredit')->all())->toBe(['15000.00', '0.00']);

    $this->actingAs($this->bendahara)->post("/jurnal/{$jurnal->id}/balik", ['alasan' => 'Lagi'])
        ->assertSessionHas('error');
    $this->actingAs($this->bendahara)->post("/jurnal/{$pembalik->id}/balik", ['alasan' => 'Lagi'])
        ->assertSessionHas('error');

    expect(Jurnal::count())->toBe(2);
});

it('jurnal dari transaksi tidak bisa dikoreksi lewat jurnal umum', function () {
    bukaRekeningUji();
    $jurnal = Jurnal::firstOrFail();

    $this->actingAs($this->bendahara)->post("/jurnal/{$jurnal->id}/balik", ['alasan' => 'x'])
        ->assertSessionHas('error');
});

it('nomor jurnal berurutan per tahun', function () {
    $baris = fn () => [
        ['akun_id' => Akun::peran('kas')->id, 'debit' => 1000],
        ['akun_id' => Akun::peran('modal')->id, 'kredit' => 1000],
    ];
    $service = app(JurnalService::class);

    expect($service->catat('2026-12-31', 'a', $baris())->nomor)->toBe('JU-2026-000001')
        ->and($service->catat('2026-12-31', 'b', $baris())->nomor)->toBe('JU-2026-000002')
        ->and($service->catat('2027-01-01', 'c', $baris())->nomor)->toBe('JU-2027-000001');
});

it('jurnal tidak bisa diubah atau dihapus', function () {
    bukaRekeningUji();

    expect(fn () => Jurnal::firstOrFail()->update(['keterangan' => 'x']))->toThrow(Exception::class)
        ->and(fn () => Jurnal::firstOrFail()->delete())->toThrow(Exception::class);
});

it('command jurnal-transaksi membuatkan jurnal transaksi lama dan aman diulang', function () {
    bukaRekeningUji();
    app(TransaksiService::class)->tarik(Transaksi::firstOrFail()->rekening_id, 10000);

    // Simulasi data sebelum modul Akuntansi: hapus jurnal langsung di database.
    DB::table('jurnal_detail')->delete();
    DB::table('jurnal')->delete();

    $this->artisan('akuntansi:jurnal-transaksi')->expectsOutputToContain('2 transaksi dijurnal.')->assertSuccessful();
    $this->artisan('akuntansi:jurnal-transaksi')->expectsOutputToContain('0 transaksi dijurnal.')->assertSuccessful();

    expect(Jurnal::count())->toBe(2);
});

it('hanya bendahara yang membuka jurnal dan bagan akun', function (string $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->get('/jurnal')->assertForbidden();
    $this->actingAs($user)->get('/jurnal/create')->assertForbidden();
    $this->actingAs($user)->get('/akun')->assertForbidden();

    $this->actingAs($this->bendahara)->get('/jurnal')->assertOk();
    $this->actingAs($this->bendahara)->get('/jurnal/create')
        ->assertInertia(fn (Assert $page) => $page->component('Akuntansi::Jurnal/Create')->where('tanggal', '2026-10-22'));
    $this->actingAs($this->bendahara)->get('/akun')->assertInertia(fn (Assert $page) => $page->has('items', 10));
})->with(['teller', 'admin']);
