<?php

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->travelTo('2026-11-11 10:00:00');

    $this->nasabah = Nasabah::factory()->create();
    $this->akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);
    $this->rekening = Rekening::factory()->saldoAwal('100000.00')->create(['nasabah_id' => $this->nasabah->id, 'tanggal_buka' => '2026-01-10']);

    $this->lain = Nasabah::factory()->create();
    $this->rekeningLain = Rekening::factory()->saldoAwal('350000000.00')->create(['nasabah_id' => $this->lain->id]);
});

/**
 * Transaksi sistem (tanpa user) pada tanggal tertentu.
 */
function mutasiPada(Rekening $rekening, string $tanggal, string $jenis, int $nominal): Transaksi
{
    app(PengaturanService::class)->set('tanggal_transaksi', $tanggal);

    return app(TransaksiService::class)->{$jenis}($rekening, $nominal);
}

it('daftar rekening hanya milik nasabah yang login, saldo string', function () {
    $nol = Rekening::factory()->create(['nasabah_id' => $this->nasabah->id]);

    sebagaiNasabah($this->akun)->getJson('/api/v1/nasabah/rekening')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->rekening->id)
        ->assertJsonPath('data.0.no_rekening', $this->rekening->nomor)
        ->assertJsonPath('data.0.produk', 'reguler')
        ->assertJsonPath('data.0.nama_produk', 'Tabungan Pelajar')
        ->assertJsonPath('data.0.saldo', '100000.00')
        ->assertJsonPath('data.0.status', 'aktif')
        ->assertJsonPath('data.0.tanggal_buka', '2026-01-10')
        ->assertJsonPath('data.0.suku_bunga', null)
        ->assertJsonPath('data.0.jatuh_tempo', null)
        ->assertJsonPath('data.1.id', $nol->id)
        ->assertJsonPath('data.1.saldo', '0.00')
        ->assertJsonMissingPath('data.0.nasabah_id')
        ->assertJsonMissingPath('data.0.saldo_awal')
        ->assertJsonPath('meta.saldo_per', '2026-11-11T10:00:00+07:00');
});

it('rekening nasabah lain atau yang tidak ada dijawab 404', function () {
    $akunLain = AkunNasabah::factory()->create(['nasabah_id' => $this->lain->id]);

    sebagaiNasabah($akunLain)->getJson("/api/v1/nasabah/rekening/{$this->rekeningLain->id}")->assertOk()->assertJsonPath('data.saldo', '350000000.00');

    foreach (['', '/mutasi', '/mutasi.pdf'] as $akhiran) {
        sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekeningLain->id}{$akhiran}")->assertNotFound();
        sebagaiNasabah($this->akun)->getJson('/api/v1/nasabah/rekening/'.Str::uuid().$akhiran)->assertNotFound();
    }

    expect(collect(sebagaiNasabah($this->akun)->getJson('/api/v1/nasabah/rekening')->json('data'))->pluck('id'))
        ->not->toContain($this->rekeningLain->id);
});

it('rekening ditutup ikut tampil dengan status ditutup', function () {
    $this->rekening->forceFill(['status' => Rekening::TUTUP])->save();

    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}")->assertJsonPath('data.status', 'ditutup');
});

it('mutasi beserta ringkasan periode yang dihitung dari seluruh periode', function () {
    mutasiPada($this->rekening, '2026-08-01', 'setor', 50000);   // sebelum periode → saldo awal 150.000
    mutasiPada($this->rekening, '2026-09-15', 'setor', 200000);
    mutasiPada($this->rekening, '2026-10-02', 'tarik', 30000);
    mutasiPada($this->rekening, '2026-11-01', 'setor', 25000);
    $terakhir = mutasiPada($this->rekening, '2026-11-10', 'tarik', 45000);
    mutasiPada($this->rekeningLain, '2026-11-10', 'setor', 999000); // milik orang lain

    $respons = sebagaiNasabah($this->akun)
        ->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi?dari=2026-09-01&sampai=2026-11-11&per_page=2")
        ->assertOk()
        ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 2, 'total' => 4, 'last_page' => 2, 'dari' => '2026-09-01', 'sampai' => '2026-11-11'])
        ->assertJsonPath('ringkasan', [
            'saldo_awal' => '150000.00',
            'total_masuk' => '225000.00',
            'total_keluar' => '75000.00',
            'saldo_akhir' => '300000.00',
            'jumlah_masuk' => 2,
            'jumlah_keluar' => 2,
        ])
        ->assertJsonPath('data.0.id', $terakhir->id)
        ->assertJsonPath('data.0.jenis', 'keluar')
        ->assertJsonPath('data.0.kategori', 'penarikan_tunai')
        ->assertJsonPath('data.0.nominal', '45000.00')
        ->assertJsonPath('data.0.saldo_setelah', '300000.00')
        ->assertJsonPath('data.0.petugas', 'Sistem')
        ->assertJsonPath('data.0.loket', null)
        ->assertJsonPath('data.1.kategori', 'setoran_tunai');

    expect($respons->json('data.0.waktu'))->toStartWith('2026-11-10T')->toEndWith('+07:00')
        ->and($respons->json('ringkasan.saldo_akhir'))->toBe($terakhir->saldo_sesudah);

    // Filter jenis hanya memengaruhi daftar, bukan ringkasan.
    sebagaiNasabah($this->akun)
        ->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi?dari=2026-09-01&sampai=2026-11-11&jenis=keluar")
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.jenis', 'keluar')
        ->assertJsonPath('data.1.jenis', 'keluar')
        ->assertJsonPath('ringkasan.total_masuk', '225000.00');
});

it('tanpa transaksi sebelum periode, saldo awal = saldo awal rekening', function () {
    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi")
        ->assertOk()
        ->assertJsonPath('meta.dari', '2026-08-11')
        ->assertJsonPath('meta.sampai', '2026-11-11')
        ->assertJsonPath('ringkasan.saldo_awal', '100000.00')
        ->assertJsonPath('ringkasan.saldo_akhir', '100000.00')
        ->assertJsonCount(0, 'data');
});

it('memvalidasi rentang tanggal mutasi', function (string $query, string $pesan) {
    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi?{$query}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['dari' => $pesan]);
})->with([
    'lebih dari 3 bulan' => ['dari=2026-08-10&sampai=2026-11-11', 'Rentang tanggal maksimal 3 bulan.'],
    'dari setelah sampai' => ['dari=2026-11-12&sampai=2026-11-11', 'Tanggal awal harus sebelum tanggal akhir.'],
    'format' => ['dari=11-11-2026', 'Format tanggal tidak valid.'],
]);

it('rentang tepat 3 bulan diterima, per_page maksimal 100', function () {
    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi?dari=2026-08-11&sampai=2026-11-11")->assertOk();
    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi?per_page=101")->assertStatus(422);
});

it('mengunduh PDF rekening koran untuk rekening sendiri', function () {
    mutasiPada($this->rekening, '2026-11-01', 'setor', 25000);

    $respons = sebagaiNasabah($this->akun)
        ->get("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi.pdf?dari=2026-10-01&sampai=2026-11-11")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "attachment; filename=\"rekening-koran-{$this->rekening->nomor}-2026-10-01-2026-11-11.pdf\"");

    expect($respons->headers->get('Cache-Control'))->toContain('no-store')
        ->and(substr($respons->getContent(), 0, 4))->toBe('%PDF');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'unduh_pdf']);

    // Error tetap JSON.
    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi.pdf?dari=2026-01-01")
        ->assertStatus(422)->assertJsonValidationErrors('dari');
});

it('transaksi pada tanggal akhir periode ikut dihitung', function () {
    $hariIni = mutasiPada($this->rekening, '2026-11-11', 'setor', 5000);

    sebagaiNasabah($this->akun)->getJson("/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi?sampai=2026-11-11")
        ->assertJsonPath('data.0.id', $hariIni->id)
        ->assertJsonPath('ringkasan.total_masuk', '5000.00')
        ->assertJsonPath('ringkasan.saldo_akhir', '105000.00');
});
