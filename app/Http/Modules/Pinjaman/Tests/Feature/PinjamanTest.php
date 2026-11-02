<?php

use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Pinjaman\Models\Pinjaman;
use App\Http\Modules\Pinjaman\Services\PinjamanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->pengaturan->set('tanggal_transaksi', '2026-11-02');
    $this->service = app(PinjamanService::class);
    $this->jurnal = app(JurnalService::class);
    $this->teller = userWithRole('teller');
    $this->guru = Nasabah::factory()->create(['jenis' => 'guru', 'nama' => 'Bu Sri Wahyuni', 'nisn' => null]);

    bukaSesiKas();
    // Kas 5.000.000 dari setoran tabungan: uang inilah yang dipinjamkan.
    app(TransaksiService::class)->setor(Rekening::factory()->create(), 5000000);
});

/**
 * @return array<string, array{0: string, 1: string}> kode akun => [debit, kredit]
 */
function barisJurnalPinjaman(Transaksi $transaksi): array
{
    return Jurnal::where('transaksi_id', $transaksi->id)->firstOrFail()->detail()->with('akun')->get()
        ->mapWithKeys(fn ($d) => [$d->akun->kode => [$d->debit, $d->kredit]])
        ->all();
}

it('membuat jadwal flat: pokok rata, jasa 1% per bulan, jatuh tempo tiap bulan', function () {
    $jadwal = $this->service->jadwal(1000000, 5, '1', '2026-11-02');

    expect($jadwal)->toHaveCount(5)
        ->and(array_column($jadwal, 'jatuh_tempo'))->toBe(['2026-12-02', '2027-01-02', '2027-02-02', '2027-03-02', '2027-04-02'])
        ->and(array_unique(array_column($jadwal, 'pokok')))->toBe(['200000.00'])
        ->and(array_unique(array_column($jadwal, 'jasa')))->toBe(['10000.00'])
        ->and(array_unique(array_column($jadwal, 'total')))->toBe(['210000.00']);
});

it('sisa pembagian pokok masuk angsuran terakhir dan jatuh tempo tidak melewati akhir bulan', function () {
    $jadwal = $this->service->jadwal(1000000, 3, '1', '2026-01-31');

    expect(array_column($jadwal, 'pokok'))->toBe(['333333.00', '333333.00', '333334.00'])
        ->and(array_column($jadwal, 'jatuh_tempo'))->toBe(['2026-02-28', '2026-03-31', '2026-04-30']);

    // Jasa dibulatkan ke rupiah (half up): 1.234.550 × 1% = 12.345,50 → 12.346.
    expect($this->service->jadwal(1234550, 2, '1', '2026-01-01')[0]['jasa'])->toBe('12346.00');
});

it('teller membuat akad: jadwal tersimpan, uang keluar dari kas, dan jurnal D Piutang / K Kas', function () {
    $this->actingAs($this->teller)->get('/pinjaman/create')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pinjaman::Create')
            ->where('plafon', '10000000.00')
            ->where('jasaPersen', '1')
            ->where('tenorMaksimal', 24));

    $this->actingAs($this->teller)->post('/pinjaman', [
        'nasabah_id' => $this->guru->id,
        'pokok' => 1000000,
        'tenor' => 5,
        'keterangan' => 'Biaya sekolah anak',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $pinjaman = Pinjaman::with('angsuran')->firstOrFail();

    expect($pinjaman)
        ->nomor->toBe('PJ-2026-000001')
        ->status->toBe('berjalan')
        ->pokok->toBe('1000000.00')
        ->total_jasa->toBe('50000.00')
        ->dibuat_oleh->toBe($this->teller->id)
        ->and($pinjaman->angsuran)->toHaveCount(5)
        ->and($pinjaman->angsuran->sortBy('ke')->first()->jatuh_tempo->toDateString())->toBe('2026-12-02');

    $cair = $pinjaman->transaksi;

    expect($cair)
        ->jenis->toBe(Transaksi::PINJAMAN_CAIR)
        ->rekening_id->toBeNull()
        ->nasabah_id->toBe($this->guru->id)
        ->mutasi->toBe('-1000000.00')
        ->saldo_sesudah->toBe('4000000.00')
        ->and(barisJurnalPinjaman($cair))->toBe([
            '1-201' => ['1000000.00', '0.00'],
            '1-101' => ['0.00', '1000000.00'],
        ])
        ->and($this->jurnal->saldoKas())->toBe('4000000.00');

    $this->actingAs($this->teller)->get("/pinjaman/{$pinjaman->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pinjaman::Show')
            ->where('pinjaman.sisa_pokok', '1000000.00')
            ->where('pinjaman.sisa_jasa', '50000.00')
            ->has('angsuran', 5)
            ->where('angsuran.0.total', '210000.00')
            ->where('angsuran.0.status', 'belum')
            ->has('riwayat', 1));
});

it('pencairan dan angsuran tercatat di rekap sesi kas', function () {
    $pinjaman = $this->service->buat($this->guru, 1000000, 5, null, $this->teller);
    $this->service->bayar($pinjaman, 1, $this->teller);

    $rekap = app(SesiKasService::class)->rekap('2026-11-02');

    expect(collect($rekap['pengeluaran'])->firstWhere('id', 'pinjaman_cair')['nilai'])->toBe('1000000.00')
        ->and(collect($rekap['pemasukan'])->firstWhere('id', 'angsuran')['nilai'])->toBe('210000.00')
        ->and($rekap['saldo_akhir'])->toBe('4210000.00');
});

it('menolak akad yang tidak memenuhi aturan', function (?string $jenis, array $input, string $field, string $pesan) {
    $nasabah = $jenis ? Nasabah::factory()->create(['jenis' => $jenis]) : $this->guru;

    $this->actingAs($this->teller)
        ->post('/pinjaman', ['nasabah_id' => $nasabah->id, 'pokok' => 1000000, 'tenor' => 5, ...$input])
        ->assertSessionHasErrors([$field => $pesan]);

    expect(Pinjaman::count())->toBe(0);
})->with([
    'nasabah siswa' => ['siswa', [], 'nasabah_id', 'Pinjaman hanya untuk nasabah guru/karyawan yang aktif.'],
    'nasabah lembaga' => ['lembaga', [], 'nasabah_id', 'Pinjaman hanya untuk nasabah guru/karyawan yang aktif.'],
    'melebihi plafon' => [null, ['pokok' => 10000001], 'pokok', 'Pokok pinjaman melebihi plafon Rp 10.000.000.'],
    'melebihi tenor' => [null, ['tenor' => 25], 'tenor', 'Jangka waktu 1 sampai 24 bulan.'],
    'saldo kas kurang' => [null, ['pokok' => 6000000], 'pokok', 'Saldo kas tidak cukup: tersedia Rp 5.000.000, dibutuhkan Rp 6.000.000.'],
]);

it('satu nasabah hanya boleh punya satu pinjaman berjalan', function () {
    $this->service->buat($this->guru, 500000, 2, null, $this->teller);

    $this->actingAs($this->teller)
        ->post('/pinjaman', ['nasabah_id' => $this->guru->id, 'pokok' => 1000000, 'tenor' => 5])
        ->assertSessionHasErrors(['nasabah_id' => 'Bu Sri Wahyuni masih punya pinjaman berjalan (PJ-2026-000001). Lunasi dulu sebelum meminjam lagi.']);

    expect(Pinjaman::count())->toBe(1);
});

it('akad wajib saat sesi kas terbuka', function () {
    $this->pengaturan->set('tanggal_transaksi', '2026-11-03');

    $this->actingAs($this->teller)
        ->post('/pinjaman', ['nasabah_id' => $this->guru->id, 'pokok' => 1000000, 'tenor' => 5])
        ->assertSessionHasErrors(['pokok' => 'Sesi kas tanggal 3 November 2026 belum dibuka. Buka sesi kas terlebih dahulu.']);

    expect(Pinjaman::count())->toBe(0);
});

it('plafon dan jasa mengikuti pengaturan; akad lama tidak berubah', function () {
    $lama = $this->service->buat($this->guru, 1000000, 5, null, $this->teller);

    $this->pengaturan->setMany(['plafon_pinjaman' => 0, 'jasa_pinjaman_persen' => 2]);
    $service = app(PinjamanService::class);
    $service->bayar($lama, 5, $this->teller);
    $baru = $service->buat($this->guru, 3000000, 3, null, $this->teller);

    expect($service->plafon())->toBe('0.00')
        ->and($lama->refresh()->total_jasa)->toBe('50000.00')
        ->and($baru->refresh()->jasa_persen)->toBe('2.0000')
        ->and($baru->angsuran()->value('jasa'))->toBe('60000.00');
});

it('membayar angsuran berurutan dengan jurnal pokok & jasa, lalu lunas', function () {
    $pinjaman = $this->service->buat($this->guru, 1000000, 5, null, $this->teller);

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/bayar", ['jumlah' => 2])
        ->assertRedirect()
        ->assertSessionHas('success', 'Angsuran AP-2026-000001 sebesar Rp 420.000 diterima.');

    $bayar = Transaksi::where('jenis', Transaksi::ANGSURAN)->firstOrFail();

    expect($bayar)
        ->nominal->toBe('420000.00')
        ->mutasi->toBe('420000.00')
        ->keterangan->toBe('Angsuran ke-1–2 pinjaman PJ-2026-000001')
        ->and(barisJurnalPinjaman($bayar))->toBe([
            '1-101' => ['420000.00', '0.00'],
            '1-201' => ['0.00', '400000.00'],
            '4-102' => ['0.00', '20000.00'],
        ])
        ->and($pinjaman->angsuran()->whereNotNull('dibayar_tanggal')->pluck('ke')->sort()->values()->all())->toBe([1, 2])
        ->and($pinjaman->load('angsuran')->sisaPokok())->toBe('600000.00')
        ->and(Pinjaman::dariTransaksi($bayar)?->id)->toBe($pinjaman->id);

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/bayar", ['jumlah' => 4])
        ->assertSessionHasErrors(['jumlah' => 'Sisa angsuran hanya 3 kali.']);

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/bayar", ['jumlah' => 3])
        ->assertSessionHas('success', 'Angsuran AP-2026-000002 sebesar Rp 630.000 diterima. Pinjaman PJ-2026-000001 lunas.');

    expect($pinjaman->refresh()->status)->toBe('lunas')
        ->and($pinjaman->lunas_at)->not->toBeNull()
        ->and($this->jurnal->saldoKas())->toBe('5050000.00');

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/bayar", ['jumlah' => 1])
        ->assertSessionHasErrors(['jumlah' => 'Pinjaman PJ-2026-000001 sudah lunas.']);

    // Setelah lunas boleh meminjam lagi.
    expect($this->service->buat($this->guru, 500000, 2, null, $this->teller)->nomor)->toBe('PJ-2026-000002');
});

it('status angsuran terlambat mengikuti tanggal transaksi aktif', function () {
    $pinjaman = $this->service->buat($this->guru, 1000000, 5, null, $this->teller);

    $this->pengaturan->set('tanggal_transaksi', '2026-12-03');

    $this->actingAs($this->teller)->get("/pinjaman/{$pinjaman->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('angsuran.0.status', 'terlambat')
            ->where('angsuran.1.status', 'belum'));

    $this->actingAs($this->teller)->get('/pinjaman')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pinjaman::Index')
            ->where('items.data.0.jatuh_tempo_berikutnya', '2026-12-02')
            ->where('items.data.0.sisa_angsuran', 5));
});

it('membatalkan pembayaran terakhir mengembalikan uang dan membalik jurnal', function () {
    $pinjaman = $this->service->buat($this->guru, 1000000, 1, null, $this->teller);
    $bayar = $this->service->bayar($pinjaman, 1, $this->teller);
    expect($pinjaman->refresh()->status)->toBe('lunas');

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/batal-bayar", ['alasan' => 'Salah pilih nasabah'])
        ->assertSessionHas('success', 'Pembayaran angsuran sebesar Rp 1.010.000 dibatalkan.');

    $pembalik = Transaksi::where('pembalik_dari_id', $bayar->id)->firstOrFail();

    expect($pinjaman->refresh()->status)->toBe('berjalan')
        ->and($pinjaman->lunas_at)->toBeNull()
        ->and($pinjaman->angsuran()->whereNotNull('dibayar_tanggal')->exists())->toBeFalse()
        ->and($pembalik->mutasi)->toBe('-1010000.00')
        ->and(barisJurnalPinjaman($pembalik))->toBe([
            '1-101' => ['0.00', '1010000.00'],
            '1-201' => ['1000000.00', '0.00'],
            '4-102' => ['10000.00', '0.00'],
        ])
        ->and($this->jurnal->saldoKas())->toBe('4000000.00')
        ->and(Pinjaman::dariTransaksi($pembalik)?->id)->toBe($pinjaman->id);

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/batal-bayar", ['alasan' => 'lagi'])
        ->assertSessionHasErrors(['alasan' => 'Belum ada angsuran yang dibayar.']);
});

it('membatalkan pinjaman hanya bila belum ada angsuran dibayar', function () {
    $pinjaman = $this->service->buat($this->guru, 1000000, 5, null, $this->teller);
    $this->service->bayar($pinjaman, 1, $this->teller);

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/batal", ['alasan' => 'Salah input'])
        ->assertSessionHasErrors(['alasan' => 'Sudah ada angsuran yang dibayar. Batalkan dulu pembayaran angsurannya.']);

    $this->service->batalPembayaran($pinjaman, 'Salah input', $this->teller);

    $this->actingAs($this->teller)->post("/pinjaman/{$pinjaman->id}/batal", ['alasan' => 'Salah input'])
        ->assertSessionHas('success', 'Pinjaman PJ-2026-000001 dibatalkan; uang pinjaman kembali ke kas.');

    $pembalik = Transaksi::where('pembalik_dari_id', $pinjaman->transaksi_id)->firstOrFail();

    expect($pinjaman->refresh()->status)->toBe('dibatalkan')
        ->and(barisJurnalPinjaman($pembalik))->toBe([
            '1-201' => ['0.00', '1000000.00'],
            '1-101' => ['1000000.00', '0.00'],
        ])
        ->and($this->jurnal->saldoKas())->toBe('5000000.00');

    // Jurnal pinjaman tidak bisa dibalik dari menu jurnal umum.
    expect(fn () => $this->jurnal->balik(Jurnal::where('transaksi_id', $pinjaman->transaksi_id)->firstOrFail()))
        ->toThrow(ValidationException::class, 'Jurnal pinjaman dikoreksi lewat pembatalan di menu Pinjaman.');

    // Setelah dibatalkan nasabah boleh meminjam lagi.
    expect($this->service->buat($this->guru, 500000, 2, null, $this->teller)->status)->toBe('berjalan');
});

it('pencarian peminjam hanya guru/karyawan aktif dan menandai pinjaman berjalan', function () {
    $karyawan = Nasabah::factory()->create(['jenis' => 'karyawan', 'nama' => 'Pak Sri Karyo', 'nisn' => null]);
    Nasabah::factory()->create(['nama' => 'Sri Siswa']);
    Nasabah::factory()->nonaktif()->create(['jenis' => 'guru', 'nama' => 'Sri Nonaktif', 'nisn' => null]);
    $this->service->buat($this->guru, 500000, 2, null, $this->teller);

    $hasil = $this->actingAs($this->teller)->getJson('/pinjaman/cari-nasabah?q=Sri')->assertOk()->json();

    expect(collect($hasil)->pluck('nama')->all())->toBe(['Bu Sri Wahyuni', 'Pak Sri Karyo'])
        ->and(collect($hasil)->firstWhere('id', $this->guru->id)['pinjaman_berjalan'])->toBe('PJ-2026-000001')
        ->and(collect($hasil)->firstWhere('id', $karyawan->id)['pinjaman_berjalan'])->toBeNull();
});

it('menolak pengguna tanpa permission pinjaman', function () {
    $pinjaman = $this->service->buat($this->guru, 500000, 2, null, $this->teller);
    $admin = userWithRole('admin');

    $this->actingAs($admin)->get('/pinjaman')->assertForbidden();
    $this->actingAs($admin)->get("/pinjaman/{$pinjaman->id}")->assertForbidden();
    $this->actingAs($admin)->post('/pinjaman', ['nasabah_id' => $this->guru->id, 'pokok' => 1000, 'tenor' => 1])->assertForbidden();
    $this->actingAs($admin)->post("/pinjaman/{$pinjaman->id}/bayar", ['jumlah' => 1])->assertForbidden();
    $this->actingAs($admin)->post("/pinjaman/{$pinjaman->id}/batal", ['alasan' => 'x'])->assertForbidden();
});
