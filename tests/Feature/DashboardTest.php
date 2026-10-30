<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Kas\Services\KasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Services\RekeningService;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->pengaturan->setMany(['tanggal_transaksi' => '2026-10-22', 'adm_penutupan' => 5000]);
    bukaSesiKas(500000);
    $this->teller = userWithRole('teller');
});

/**
 * @return array<string, array{nilai: string, keterangan: string|null}>
 */
function kartuDashboard(): array
{
    $kartu = [];

    test()->actingAs(test()->teller)->get('/dashboard')
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$kartu) {
            $kartu = collect($page->toArray()['props']['ringkasan'])->keyBy('label')->all();
        });

    return $kartu;
}

it('tetap tampil tanpa data transaksi', function () {
    $kartu = kartuDashboard();

    expect($kartu['Saldo kas hari ini']['nilai'])->toBe('500000.00')
        ->and($kartu['Setoran hari ini']['nilai'])->toBe('0.00')
        ->and($kartu['Nasabah aktif']['nilai'])->toBe('0');
});

it('menghitung setoran, penarikan, kas, dan nasabah pada tanggal transaksi aktif', function () {
    $rekening = app(RekeningService::class);
    $transaksi = app(TransaksiService::class);

    $a = $rekening->buka(Nasabah::factory()->create(), JenisRekening::kode('tabungan'), 100000);
    $b = $rekening->buka(Nasabah::factory()->create(), JenisRekening::kode('tabungan'), 30000);
    Nasabah::factory()->nonaktif()->create();

    $transaksi->setor($a, 50000);
    $transaksi->tarik($a, 20000);
    $transaksi->tutup($b); // biaya adm 5.000 (bukan uang kas) + penutupan 25.000 (uang keluar)

    $kartu = kartuDashboard();

    expect($kartu['Setoran hari ini'])->nilai->toBe('180000.00')->keterangan->toBe('3 transaksi')
        ->and($kartu['Penarikan hari ini'])->nilai->toBe('45000.00')->keterangan->toBe('2 transaksi')
        // 500.000 + 180.000 - 20.000 - 25.000
        ->and($kartu['Saldo kas hari ini']['nilai'])->toBe('635000.00')
        ->and($kartu['Total saldo tabungan'])->nilai->toBe('130000.00')->keterangan->toBe('1 rekening aktif')
        ->and($kartu['Nasabah aktif']['nilai'])->toBe('2');
});

it('tidak menghitung transaksi pada tanggal lain', function () {
    $rekening = app(RekeningService::class)->buka(Nasabah::factory()->create(), JenisRekening::kode('tabungan'), 100000);

    $this->pengaturan->set('tanggal_transaksi', '2026-10-23');
    app(TransaksiService::class)->setor($rekening, 40000);
    // Modal awal berlaku per sesi: tanggal baru tanpa sesi berarti modal 0.

    $kartu = kartuDashboard();

    expect($kartu['Setoran hari ini']['nilai'])->toBe('40000.00')
        ->and($kartu['Saldo kas hari ini'])->nilai->toBe('40000.00')->keterangan->toStartWith('Sesi kas belum dibuka')
        ->and($kartu['Total saldo tabungan']['nilai'])->toBe('140000.00');
});

it('koreksi setoran mengurangi saldo kas', function () {
    $rekening = app(RekeningService::class)->buka(Nasabah::factory()->create(), JenisRekening::kode('tabungan'), 100000);
    $setoran = app(TransaksiService::class)->setor($rekening, 50000);
    app(TransaksiService::class)->balik($setoran);

    expect(kartuDashboard()['Saldo kas hari ini']['nilai'])->toBe('600000.00');
});

it('saldo kas hari ini memperhitungkan penerimaan dan pengeluaran kas lain-lain', function () {
    if (! class_exists(KasService::class)) {
        $this->markTestSkipped('Modul Kas tidak terpasang.');
    }

    $pengaturan = app(PengaturanService::class);
    $tanggal = $pengaturan->tanggalTransaksi()->toDateString();
    $akun = Akun::class;

    app(JurnalService::class)->catat($tanggal, 'Modal', [
        ['akun_id' => $akun::peran('kas')->id, 'debit' => 100000],
        ['akun_id' => $akun::peran('modal')->id, 'kredit' => 100000],
    ]);

    $kas = app(KasService::class);
    $kas->terima($akun::peran('pendapatan_buku_tabungan'), 5000, 'Buku');
    $kas->keluarkan($akun::where('kode', '5-103')->firstOrFail(), 2000, 'ATK');

    $this->actingAs(userWithRole('bendahara'))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ringkasan.0.label', 'Saldo kas hari ini')
            ->where('ringkasan.0.nilai', '503000.00'));
});
