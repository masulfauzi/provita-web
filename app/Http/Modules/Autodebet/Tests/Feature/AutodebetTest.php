<?php

use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Autodebet\Models\Autodebet;
use App\Http\Modules\Autodebet\Models\AutodebetRiwayat;
use App\Http\Modules\Autodebet\Services\AutodebetService;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Rekening\Services\BukuTabunganService;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->pengaturan->set('tanggal_transaksi', '2026-11-02'); // Senin
    $this->teller = userWithRole('teller');

    $this->nasabah = Nasabah::factory()->create();
    $this->wisata = JenisRekening::factory()->create(['kode' => 'wisata', 'nama' => 'Tabungan Wisata', 'prefix' => '41']);
    $this->pelajar = Rekening::factory()->for($this->nasabah)->saldoAwal('100000.00')->create();
    $this->tujuan = Rekening::factory()->for($this->nasabah)->create(['jenis_rekening_id' => $this->wisata->id, 'nomor' => '41'.$this->pelajar->nomor]);
});

/**
 * @param  array<string, mixed>  $data
 */
function buatAutodebet(array $data = []): Autodebet
{
    return app(AutodebetService::class)->buat([
        'rekening_id' => test()->tujuan->id,
        'nominal' => 20000,
        'periode' => 'mingguan',
        'hari' => 1,
        'mulai' => '2026-11-02',
        ...$data,
    ], test()->teller);
}

/**
 * Tutup sesi yang terbuka (uang cocok), pindah tanggal, lalu buka sesi baru sebagai teller.
 */
function bukaSesiPada(string $tanggal): void
{
    $sesi = app(SesiKasService::class);

    if ($terbuka = $sesi->terbuka()) {
        $sesi->tutup($terbuka, $sesi->rekap($terbuka->tanggal)['saldo_akhir']);
    }

    test()->pengaturan->set('tanggal_transaksi', $tanggal);
    app(SesiKasService::class)->buka(0, [Nasabah::factory()->create()->id], test()->teller);
}

it('menghitung jatuh tempo pertama mingguan dan bulanan', function () {
    $service = app(AutodebetService::class);
    $selasa = Carbon::parse('2026-11-03');

    expect($service->jadwalPertama('mingguan', 1, $selasa)->toDateString())->toBe('2026-11-09')
        ->and($service->jadwalPertama('mingguan', 2, $selasa)->toDateString())->toBe('2026-11-03')
        ->and($service->jadwalPertama('bulanan', 5, $selasa)->toDateString())->toBe('2026-11-05')
        ->and($service->jadwalPertama('bulanan', 1, $selasa)->toDateString())->toBe('2026-12-01')
        ->and($service->jadwalBerikutnya('bulanan', 28, Carbon::parse('2026-01-28'))->toDateString())->toBe('2026-02-28');
});

it('teller membuat autodebet dari tabungan pelajar ke rekening tujuan', function () {
    $this->actingAs($this->teller)->post('/autodebet', [
        'rekening_id' => $this->tujuan->id,
        'nominal' => 25000,
        'periode' => 'bulanan',
        'hari' => 10,
        'target' => 300000,
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Autodebet Rp 25.000 tiap tanggal 10 dibuat. Jatuh tempo pertama 10 November 2026.');

    expect(Autodebet::sole())
        ->sumber_rekening_id->toBe($this->pelajar->id)
        ->mulai->toDateString()->toBe('2026-11-03')
        ->jatuh_tempo->toDateString()->toBe('2026-11-10')
        ->target->toBe('300000.00')
        ->status->toBe('aktif');
});

it('menolak tujuan rekening utama, tanpa sumber, target kecil, dan autodebet ganda', function () {
    expect(fn () => buatAutodebet(['rekening_id' => $this->pelajar->id]))
        ->toThrow(ValidationException::class, 'Autodebet hanya untuk rekening aktif selain rekening utama (Tabungan Pelajar).');

    // Target = saldo tujuan yang ingin dicapai: harus di atas saldo saat ini.
    app(TransaksiService::class)->setor($this->tujuan, 30000);

    expect(fn () => buatAutodebet(['target' => 30000]))
        ->toThrow(ValidationException::class, 'Target harus lebih besar dari saldo rekening saat ini (Rp 30.000).');

    expect(fn () => buatAutodebet(['periode' => 'mingguan', 'hari' => 8]))
        ->toThrow(ValidationException::class, 'Pilih hari Senin s.d. Minggu.');

    $lain = Rekening::factory()->create(['jenis_rekening_id' => $this->wisata->id]);
    Rekening::query()->where('nasabah_id', $lain->nasabah_id)->where('id', '!=', $lain->id)->delete();

    expect(fn () => buatAutodebet(['rekening_id' => $lain->id]))
        ->toThrow(ValidationException::class, 'Nasabah belum punya Tabungan Pelajar aktif sebagai sumber dana.');

    buatAutodebet();

    expect(fn () => buatAutodebet())->toThrow(ValidationException::class, "Rekening {$this->tujuan->nomor} sudah punya autodebet.");
});

it('membuka sesi kas memproses autodebet yang jatuh tempo', function () {
    $autodebet = buatAutodebet();

    $this->actingAs($this->teller)
        ->post('/kas/sesi', ['modal_awal' => 0, 'petugas' => [Nasabah::factory()->create()->id]])
        ->assertSessionHas('success', fn ($pesan) => str_ends_with($pesan, 'Autodebet: 1 berhasil (Rp 20.000).'));

    $keluar = Transaksi::query()->where('jenis', Transaksi::PINDAH_KELUAR)->sole();
    $masuk = Transaksi::query()->where('jenis', Transaksi::PINDAH_MASUK)->sole();

    expect($this->pelajar->fresh()->saldo)->toBe('80000.00')
        ->and($this->tujuan->fresh()->saldo)->toBe('20000.00')
        ->and($keluar)->rekening_id->toBe($this->pelajar->id)->mutasi->toBe('-20000.00')->user_id->toBe($this->teller->id)
        ->and($masuk)->rekening_id->toBe($this->tujuan->id)->mutasi->toBe('20000.00')
        ->and($autodebet->fresh())->terkumpul->toBe('20000.00')->jatuh_tempo->toDateString()->toBe('2026-11-09')
        ->and(AutodebetRiwayat::sole())->status->toBe('berhasil')->transaksi_masuk_id->toBe($masuk->id);

    // Jurnal pindah buku seimbang dan tidak mengubah saldo akun mana pun.
    $detail = Jurnal::query()->where('transaksi_id', $keluar->id)->sole()->detail;
    expect($detail->sum('debit'))->toEqual($detail->sum('kredit'));

    // Bukan uang fisik: tidak masuk rekap sesi kas.
    expect(app(SesiKasService::class)->rekap('2026-11-02'))
        ->total_pemasukan->toBe('0.00')
        ->total_pengeluaran->toBe('0.00');

    // Sandi buku tabungan 06/07.
    expect(collect(app(BukuTabunganService::class)->baris($this->tujuan->fresh()))->pluck('sandi')->all())->toBe(['07']);
});

it('memproses semua jadwal yang terlewat tanpa dobel', function () {
    $autodebet = buatAutodebet();

    bukaSesiPada('2026-11-02');
    bukaSesiPada('2026-11-23'); // 9, 16, 23 terlewat

    expect(AutodebetRiwayat::query()->orderBy('jadwal')->pluck('jadwal')->map->toDateString()->all())
        ->toBe(['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23'])
        ->and($autodebet->fresh())->terkumpul->toBe('80000.00')->jatuh_tempo->toDateString()->toBe('2026-11-30')
        ->and($this->pelajar->fresh()->saldo)->toBe('20000.00');
});

it('saldo kurang dicatat gagal lalu lanjut ke jadwal berikutnya', function () {
    $autodebet = buatAutodebet(['nominal' => 60000]);

    bukaSesiPada('2026-11-09'); // 2 Nov berhasil (sisa 40.000), 9 Nov gagal

    $riwayat = AutodebetRiwayat::query()->orderBy('jadwal')->get();

    expect($riwayat->pluck('status')->all())->toBe(['berhasil', 'gagal'])
        ->and($riwayat[1]->alasan)->toBe('Saldo tidak cukup. Saldo Rp 40.000, penarikan Rp 60.000.')
        ->and($this->pelajar->fresh()->saldo)->toBe('40000.00')
        ->and($autodebet->fresh())->terkumpul->toBe('60000.00')->jatuh_tempo->toDateString()->toBe('2026-11-16')->status->toBe('aktif');
});

it('target adalah saldo rekening tujuan: berhenti saat saldo tercapai, debet terakhir sebesar sisa', function () {
    app(TransaksiService::class)->setor($this->tujuan, 30000); // saldo awal tujuan ikut dihitung
    $autodebet = buatAutodebet(['target' => 80000]);

    bukaSesiPada('2026-11-30');

    expect(AutodebetRiwayat::query()->orderBy('jadwal')->pluck('nominal')->all())->toBe(['20000.00', '20000.00', '10000.00'])
        ->and($autodebet->fresh())->terkumpul->toBe('50000.00')->status->toBe('selesai')
        ->and($this->tujuan->fresh()->saldo)->toBe('80000.00');

    bukaSesiPada('2026-12-07');

    expect(AutodebetRiwayat::count())->toBe(3);
});

it('setoran tunai ke rekening tujuan ikut dihitung; target tercapai tanpa debet', function () {
    $autodebet = buatAutodebet(['target' => 50000]);

    bukaSesiPada('2026-11-02'); // debet 20.000 → saldo 20.000
    app(TransaksiService::class)->setor($this->tujuan, 35000); // saldo 55.000 ≥ target

    bukaSesiPada('2026-11-09');

    expect(AutodebetRiwayat::count())->toBe(1)
        ->and($autodebet->fresh())->status->toBe('selesai')->terkumpul->toBe('20000.00')
        ->and($this->tujuan->fresh()->saldo)->toBe('55000.00')
        ->and($this->pelajar->fresh()->saldo)->toBe('80000.00');
});

it('target lebih kecil dari nominal diterima bila di atas saldo; ubah target dicek terhadap saldo', function () {
    $autodebet = buatAutodebet(['target' => 15000]);

    bukaSesiPada('2026-11-02');

    expect(AutodebetRiwayat::sole()->nominal)->toBe('15000.00')
        ->and($autodebet->fresh()->status)->toBe('selesai');

    $lain = buatAutodebet(['target' => 40000]);

    $this->actingAs($this->teller)->put("/autodebet/{$lain->id}", ['nominal' => 20000, 'periode' => 'mingguan', 'hari' => 1, 'target' => 15000])
        ->assertSessionHasErrors(['target' => 'Target harus lebih besar dari saldo rekening saat ini (Rp 15.000).']);
});

it('autodebet yang dihentikan tidak diproses dan dilanjutkan tanpa menagih jadwal terlewat', function () {
    $autodebet = buatAutodebet();

    $this->actingAs($this->teller)->post("/autodebet/{$autodebet->id}/hentikan")->assertSessionHas('success', 'Autodebet dihentikan.');

    bukaSesiPada('2026-11-16');
    expect(AutodebetRiwayat::count())->toBe(0);

    $this->actingAs($this->teller)->post("/autodebet/{$autodebet->id}/lanjutkan");

    expect($autodebet->fresh())->status->toBe('aktif')->jatuh_tempo->toDateString()->toBe('2026-11-23');
});

it('mengubah nominal dan jadwal autodebet', function () {
    $autodebet = buatAutodebet();

    $this->actingAs($this->teller)->put("/autodebet/{$autodebet->id}", ['nominal' => 30000, 'periode' => 'bulanan', 'hari' => 15, 'target' => null])
        ->assertSessionHasNoErrors();

    expect($autodebet->fresh())->nominal->toBe('30000.00')->periode->toBe('bulanan')->jatuh_tempo->toDateString()->toBe('2026-11-15');
});

it('transaksi autodebet tidak bisa dikoreksi per baris', function () {
    buatAutodebet();
    bukaSesiPada('2026-11-02');

    expect(fn () => app(TransaksiService::class)->balik(Transaksi::query()->where('jenis', Transaksi::PINDAH_MASUK)->sole()))
        ->toThrow(TransaksiDitolakException::class, 'Transaksi autodebet tidak bisa dikoreksi per baris.');
});

it('halaman autodebet dan kartu di rekening', function () {
    $autodebet = buatAutodebet(['target' => 100000]);

    $this->actingAs($this->teller)->get('/autodebet')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Autodebet::Index')
            ->where('items.data.0.id', $autodebet->id)
            ->where('items.data.0.jadwal', 'Tiap Senin')
            ->where('items.data.0.sumber.id', $this->pelajar->id));

    $this->actingAs($this->teller)->get("/autodebet/{$autodebet->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Autodebet::Show'));

    $this->actingAs($this->teller)->get("/rekening/{$this->tujuan->id}")
        ->assertInertia(fn (Assert $page) => $page->where('autodebet.ada', true)->where('autodebet.item.id', $autodebet->id));

    $this->actingAs($this->teller)->get("/rekening/{$this->pelajar->id}")
        ->assertInertia(fn (Assert $page) => $page->where('autodebet.ada', false));

    $this->actingAs($this->teller)->getJson('/rekening/cari?non_utama=1')
        ->assertJsonCount(1)->assertJsonPath('0.id', $this->tujuan->id);
});

it('hak akses autodebet', function () {
    $this->actingAs(userWithRole('admin'))->get('/autodebet')->assertForbidden();
    $this->actingAs(userWithRole('bendahara'))->get('/autodebet')->assertOk();
});
