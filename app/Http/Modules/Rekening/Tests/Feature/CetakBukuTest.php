<?php

use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\CetakBukuTabungan;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Rekening\Services\BukuTabunganService;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->pengaturan->setMany(['tanggal_transaksi' => '2026-10-01', 'adm_penutupan' => 5000]);
    $this->teller = userWithRole('teller');
    $this->petugas = User::factory()->create(['name' => 'Siti Nur Aini Rahma']);
    $this->buku = app(BukuTabunganService::class);
    $this->transaksi = app(TransaksiService::class);

    // Pindah hari: sesi kas hari sebelumnya ditutup (uang cocok), lalu sesi baru dibuka.
    $hariBerikutnya = function (string $tanggal) {
        $sesi = app(SesiKasService::class);
        $terbuka = $sesi->terbuka();
        $sesi->tutup($terbuka, $sesi->rekap($terbuka->tanggal)['saldo_akhir']);
        $this->pengaturan->set('tanggal_transaksi', $tanggal);
        bukaSesiKas(1000000);
    };

    // urut 1 setor 100.000 (1 Okt), 2 tarik 30.000 (2 Okt), 3 setor 50.000 (3 Okt), 4 koreksi setoran 3 (3 Okt)
    bukaSesiKas(1000000);
    $this->rekening = Rekening::factory()->create();
    $this->transaksi->setor($this->rekening, 100000, $this->petugas, 'Setoran awal');
    $hariBerikutnya('2026-10-02');
    app(TransaksiService::class)->tarik($this->rekening, 30000, User::factory()->create(['name' => 'Teller']));
    $hariBerikutnya('2026-10-03');
    $setoran = app(TransaksiService::class)->setor($this->rekening, 50000);
    app(TransaksiService::class)->balik($setoran);
});

it('menyusun baris buku tabungan dengan sandi, debit/kredit, saldo, dan paraf', function () {
    $baris = app(BukuTabunganService::class)->baris($this->rekening);

    expect($baris)->toHaveCount(4)
        ->and($baris[0])->toMatchArray(['no' => 1, 'tanggal' => '2026-10-01', 'sandi' => '01', 'debit' => null, 'kredit' => '100000.00', 'saldo' => '100000.00', 'paraf' => 'SNA', 'dicetak' => 0])
        ->and($baris[1])->toMatchArray(['no' => 2, 'sandi' => '02', 'debit' => '30000.00', 'kredit' => null, 'saldo' => '70000.00', 'paraf' => 'TE'])
        ->and($baris[3])->toMatchArray(['no' => 4, 'sandi' => '03', 'debit' => '50000.00', 'saldo' => '70000.00', 'paraf' => '']);
});

it('sandi biaya administrasi dan tutup rekening', function () {
    $this->transaksi->tutup($this->rekening);

    expect(collect(app(BukuTabunganService::class)->baris($this->rekening))->pluck('sandi')->all())
        ->toBe(['01', '02', '01', '03', '04', '05']);
});

it('memilih baris berdasarkan rentang nomor dan rentang tanggal', function () {
    $buku = app(BukuTabunganService::class);

    expect(collect($buku->baris($this->rekening, ['mode' => 'nomor', 'dari_no' => 2, 'sampai_no' => 3]))->pluck('no')->all())->toBe([2, 3])
        ->and(collect($buku->baris($this->rekening, ['mode' => 'tanggal', 'dari' => '2026-10-02', 'sampai' => '2026-10-02']))->pluck('no')->all())->toBe([2])
        ->and(collect($buku->baris($this->rekening, ['mode' => 'tanggal', 'dari' => '2026-10-03']))->pluck('no')->all())->toBe([3, 4]);
});

it('menandai baris yang dicetak dan melanjutkan posisi berikutnya', function () {
    $buku = app(BukuTabunganService::class);
    $baris = $buku->baris($this->rekening);

    expect($buku->posisiBerikutnya($this->rekening))->toBe(['halaman' => 1, 'baris' => 1]);

    $this->actingAs($this->teller)
        ->post("/rekening/{$this->rekening->id}/cetak", ['transaksi' => [$baris[2]['id'], $baris[0]['id'], $baris[1]['id']], 'halaman' => 1, 'baris' => 1])
        ->assertSessionHas('success', '3 baris ditandai sudah dicetak. Cetak berikutnya mulai halaman 1 baris 4.');

    // Dicatat berurutan menurut nomor, bukan urutan kiriman.
    expect(CetakBukuTabungan::query()->orderBy('baris')->get()->map(fn ($c) => [$c->transaksi_id, $c->baris])->all())
        ->toBe([[$baris[0]['id'], 1], [$baris[1]['id'], 2], [$baris[2]['id'], 3]])
        ->and(collect($buku->baris($this->rekening))->pluck('no')->all())->toBe([4])
        ->and($buku->jumlahBelumDicetak($this->rekening))->toBe(1);

    // Cetak ulang menambah riwayat.
    $buku->catat($this->rekening, [$baris[0]['id']], 1, 4);

    expect($buku->baris($this->rekening, ['mode' => 'nomor', 'dari_no' => 1, 'sampai_no' => 1])[0]['dicetak'])->toBe(2)
        ->and($buku->posisiBerikutnya($this->rekening))->toBe(['halaman' => 1, 'baris' => 5]);
});

it('pindah ke halaman berikutnya setelah baris terakhir', function () {
    $this->pengaturan->set('buku_baris_per_halaman', 3);
    $buku = app(BukuTabunganService::class);

    $buku->catat($this->rekening, collect($buku->baris($this->rekening))->pluck('id')->all(), 1, 2);

    expect(CetakBukuTabungan::query()->orderBy('halaman')->orderBy('baris')->get(['halaman', 'baris'])->map->only('halaman', 'baris')->all())
        ->toBe([['halaman' => 1, 'baris' => 2], ['halaman' => 1, 'baris' => 3], ['halaman' => 2, 'baris' => 1], ['halaman' => 2, 'baris' => 2]])
        ->and($buku->posisiBerikutnya($this->rekening))->toBe(['halaman' => 2, 'baris' => 3]);
});

it('menolak mutasi rekening lain dan baris di luar halaman', function () {
    $lain = Rekening::factory()->create();
    $mutasiLain = $this->transaksi->setor($lain, 1000);
    $id = app(BukuTabunganService::class)->baris($this->rekening)[0]['id'];

    $this->actingAs($this->teller)
        ->post("/rekening/{$this->rekening->id}/cetak", ['transaksi' => [$id, $mutasiLain->id], 'halaman' => 1, 'baris' => 1])
        ->assertSessionHasErrors(['transaksi' => 'Ada mutasi yang bukan milik rekening ini.']);

    $this->actingAs($this->teller)
        ->post("/rekening/{$this->rekening->id}/cetak", ['transaksi' => [$id], 'halaman' => 1, 'baris' => 21])
        ->assertSessionHasErrors(['baris' => 'Baris harus 1 sampai 20.']);

    expect(CetakBukuTabungan::count())->toBe(0);
});

it('halaman cetak menampilkan baris, posisi, dan tata letak', function () {
    $this->actingAs($this->teller)->get("/rekening/{$this->rekening->id}/cetak?mode=nomor&dari_no=2")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rekening::Cetak')
            ->where('filter.mode', 'nomor')
            ->has('baris', 3)
            ->where('posisi', ['halaman' => 1, 'baris' => 1])
            ->where('terakhir', null)
            ->where('tataLetak', [
                'baris_per_halaman' => 20,
                'jarak_atas' => 20,
                'jarak_kiri' => 10,
                'tinggi_baris' => 6,
                'lebar_kolom' => [10, 20, 10, 30, 30, 32, 14],
            ]));

    $this->actingAs($this->teller)->get("/rekening/{$this->rekening->id}")
        ->assertInertia(fn (Assert $page) => $page->where('belumDicetak', 4));
});

it('lebar kolom yang tidak valid memakai default', function () {
    $this->pengaturan->setMany(['buku_lebar_kolom_mm' => '10,abc,5', 'buku_tinggi_baris_mm' => 0]);

    expect(app(BukuTabunganService::class)->tataLetak())
        ->lebar_kolom->toBe(BukuTabunganService::LEBAR_KOLOM)
        ->tinggi_baris->toBe(6);
});

it('hanya petugas berizin yang bisa mencetak', function () {
    $this->actingAs(userWithRole('bendahara'))->get("/rekening/{$this->rekening->id}/cetak")->assertOk();
    $this->actingAs(userWithRole('admin'))->get("/rekening/{$this->rekening->id}/cetak")->assertForbidden();
    $this->actingAs(userWithRole('admin'))->post("/rekening/{$this->rekening->id}/cetak", [])->assertForbidden();
});
