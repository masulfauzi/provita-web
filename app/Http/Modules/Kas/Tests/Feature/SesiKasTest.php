<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Kas\Models\SesiKas;
use App\Http\Modules\Kas\Services\KasService;
use App\Http\Modules\Kas\Services\SesiKasService;
use App\Http\Modules\Laporan\Exports\LaporanExport;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Rekening\Services\RekeningService;
use App\Http\Modules\Transaksi\Exceptions\TransaksiDitolakException;
use App\Http\Modules\Transaksi\Services\RingkasanService;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $this->pengaturan = app(PengaturanService::class);
    $this->pengaturan->setMany(['tanggal_transaksi' => '2026-10-30', 'adm_penutupan' => 5000]);
    $this->sesi = app(SesiKasService::class);
    $this->teller = userWithRole('teller');
    $this->bendahara = userWithRole('bendahara');
    $this->rekening = Rekening::factory()->saldoAwal('100000.00')->create();
    $this->piket = Nasabah::factory()->create(['nama' => 'Budi Santoso', 'kelas' => 'X TKJ 1']);
});

it('teller membuka sesi dengan modal awal', function () {
    $this->actingAs($this->teller)->get('/kas/sesi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Kas::Sesi')->where('rekap', null)->where('tanggal', '2026-10-30'));

    $this->travelTo('2026-10-30 07:05:00');
    $citra = Nasabah::factory()->create(['nama' => 'Citra Lestari', 'kelas' => 'XI AK 2']);

    $this->actingAs($this->teller)->post('/kas/sesi', ['modal_awal' => 1000000, 'petugas' => [$this->piket->id, $citra->id]])
        ->assertSessionHas('success', 'Sesi kas 30 Oktober 2026 dibuka dengan modal awal Rp 1.000.000. Teller piket: Budi Santoso, Citra Lestari (hadir pukul 07.05).');

    $sesi = SesiKas::sole();

    expect($sesi)
        ->tanggal->toDateString()->toBe('2026-10-30')
        ->modal_awal->toBe('1000000.00')
        ->dibuka_oleh->toBe($this->teller->id)
        ->ditutup_at->toBeNull()
        ->and($sesi->petugas->pluck('nama')->all())->toBe(['Budi Santoso', 'Citra Lestari'])
        ->and($sesi->petugas->pluck('pivot.hadir_at')->unique()->all())->toBe(['2026-10-30 07:05:00']);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'sesi_kas', 'event' => 'petugas', 'subject_id' => $sesi->id]);

    $this->actingAs($this->teller)->get('/kas/sesi')
        ->assertInertia(fn (Assert $page) => $page
            ->where('rekap.sesi.petugas.0.nama', 'Budi Santoso')
            ->where('rekap.sesi.petugas.1.kelas', 'XI AK 2')
            ->where('riwayat.data.0.petugas.1.nama', 'Citra Lestari'));

    $this->actingAs($this->teller)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('app.sesi_kas', 'terbuka'));
});

it('satu sesi per tanggal dan sesi lama harus ditutup dulu', function () {
    $this->sesi->buka(500000, [$this->piket->id]);

    expect(fn () => $this->sesi->buka(100, [$this->piket->id]))->toThrow(ValidationException::class, 'Sesi kas tanggal 30 Oktober 2026 sudah dibuka.');

    $this->pengaturan->set('tanggal_transaksi', '2026-10-31');

    expect(fn () => app(SesiKasService::class)->buka(100, [$this->piket->id]))
        ->toThrow(ValidationException::class, 'Sesi kas tanggal 30 Oktober 2026 belum ditutup. Tutup dulu sesi tersebut.');

    // Halaman sesi mengutamakan sesi lama yang belum ditutup.
    $this->actingAs($this->teller)->get('/kas/sesi')
        ->assertInertia(fn (Assert $page) => $page->where('rekap.tanggal', '2026-10-30')->where('tanggal', '2026-10-31'));
});

it('modal awal tidak boleh negatif', function () {
    $this->actingAs($this->teller)->post('/kas/sesi', ['modal_awal' => -1, 'petugas' => [$this->piket->id]])->assertSessionHasErrors('modal_awal');

    expect(SesiKas::count())->toBe(0);
});

it('teller tidak bisa bertransaksi sebelum sesi dibuka', function () {
    $pesan = 'Sesi kas tanggal 30 Oktober 2026 belum dibuka. Buka sesi kas terlebih dahulu.';

    $this->actingAs($this->teller)
        ->post('/transaksi/setor', ['rekening_id' => $this->rekening->id, 'nominal' => 1000])
        ->assertSessionHasErrors(['nominal' => $pesan]);

    $this->actingAs($this->teller)
        ->post('/rekening', ['nasabah_id' => Nasabah::factory()->create(['nisn' => '0012345678'])->id, 'jenis_rekening_id' => JenisRekening::kode('tabungan')->id, 'setoran_awal' => 10000])
        ->assertSessionHasErrors('setoran_awal');

    $this->actingAs($this->teller)
        ->post('/kas/penerimaan', ['akun_id' => Akun::peran('pendapatan_buku_tabungan')->id, 'nominal' => 5000, 'keterangan' => 'Buku'])
        ->assertSessionHasErrors(['nominal' => $pesan]);

    expect($this->rekening->fresh()->saldo)->toBe('100000.00')
        ->and(Rekening::count())->toBe(1);
});

it('teller tidak bisa bertransaksi setelah sesi ditutup', function () {
    $sesi = $this->sesi->buka(0, [$this->piket->id]);
    $this->sesi->tutup($sesi, 0);

    expect(fn () => app(TransaksiService::class)->tarik($this->rekening, 1000, $this->teller))
        ->toThrow(TransaksiDitolakException::class, 'Sesi kas tanggal 30 Oktober 2026 sudah ditutup.');

    $this->actingAs($this->teller)->post("/rekening/{$this->rekening->id}/tutup")
        ->assertSessionHas('error', 'Sesi kas tanggal 30 Oktober 2026 sudah ditutup.');

    expect($this->rekening->fresh())->saldo->toBe('100000.00')->isAktif()->toBeTrue();
});

it('bendahara juga tidak bisa bertransaksi sebelum teller membuka sesi', function () {
    $pesan = 'Sesi kas tanggal 30 Oktober 2026 belum dibuka. Buka sesi kas terlebih dahulu.';
    $setoran = app(TransaksiService::class)->setor($this->rekening, 5000); // proses sistem, tanpa user
    $km = app(KasService::class)->terima(Akun::peran('pendapatan_buku_tabungan'), 5000, 'Buku');

    $this->actingAs($this->bendahara)
        ->post('/transaksi/setor', ['rekening_id' => $this->rekening->id, 'nominal' => 1000])
        ->assertSessionHasErrors(['nominal' => $pesan]);

    $this->actingAs($this->bendahara)->post("/transaksi/{$setoran->id}/balik", ['alasan' => 'Uji'])
        ->assertSessionHas('error', $pesan);

    $this->actingAs($this->bendahara)->post("/kas/{$km->id}/batal", ['alasan' => 'Uji'])
        ->assertSessionHas('error', $pesan);

    expect($this->rekening->fresh()->saldo)->toBe('105000.00');

    // Setelah teller membuka sesi, bendahara bisa bertransaksi.
    $this->actingAs($this->teller)->post('/kas/sesi', ['modal_awal' => 0, 'petugas' => [$this->piket->id]]);

    $this->actingAs($this->bendahara)
        ->post('/transaksi/setor', ['rekening_id' => $this->rekening->id, 'nominal' => 1000])
        ->assertSessionHasNoErrors();

    expect($this->rekening->fresh()->saldo)->toBe('106000.00');
});

it('menutup sesi menghitung saldo sistem dari modal, tabungan, koreksi, dan kas lain', function () {
    $sesi = $this->sesi->buka(1000000, [$this->piket->id], $this->teller);
    $transaksi = app(TransaksiService::class);
    $kas = app(KasService::class);

    $buka = app(RekeningService::class)->buka(Nasabah::factory()->create(['nisn' => '0012345678']), JenisRekening::kode('tabungan'), 20000, $this->teller);
    $transaksi->setor($this->rekening, 250000, $this->teller);
    $transaksi->tarik($this->rekening, 75000, $this->teller);
    $salah = $transaksi->setor($this->rekening, 10000, $this->teller);
    $transaksi->balik($salah, $this->bendahara);
    $transaksi->tutup($buka, $this->teller); // biaya 5.000 bukan uang fisik, 15.000 diserahkan
    $kas->terima(Akun::peran('pendapatan_buku_tabungan'), 15000, 'Buku', null, $this->teller);
    $km = $kas->terima(Akun::peran('pendapatan_administrasi'), 10000, 'Salah', null, $this->teller);
    $kas->keluarkan(Akun::where('kode', '5-103')->firstOrFail(), 45000, 'ATK', $this->teller);
    $kas->batal($km, 'Salah catat', $this->bendahara);

    // 1.000.000 + 20.000 + 250.000 - 75.000 + 10.000 - 10.000 - 15.000 + 15.000 + 10.000 - 45.000 - 10.000
    $rekap = $this->sesi->rekap('2026-10-30');

    expect($rekap)
        ->total_pemasukan->toBe('305000.00')
        ->total_pengeluaran->toBe('155000.00')
        ->saldo_akhir->toBe('1150000.00')
        ->and(collect($rekap['pemasukan'])->firstWhere('id', 'setor'))->jumlah->toBe(3)->nilai->toBe('280000.00')
        ->and(collect($rekap['pengeluaran'])->firstWhere('id', 'koreksi'))->jumlah->toBe(2)->nilai->toBe('20000.00')
        ->and(collect($rekap['rincian'])->pluck('jenis'))->not->toContain('Biaya administrasi')
        // Sama dengan dashboard: modal + mutasi kas.
        ->and(Money::add('1000000', app(RingkasanService::class)->harian($this->pengaturan->tanggalTransaksi())['mutasi_kas']))->toBe('1150000.00');

    $this->actingAs($this->teller)->post("/kas/sesi/{$sesi->id}/tutup", ['uang_fisik' => 1150000])
        ->assertSessionHas('success', 'Sesi kas 30 Oktober 2026 ditutup. Uang fisik cocok.');

    expect($sesi->fresh())
        ->saldo_sistem->toBe('1150000.00')
        ->uang_fisik->toBe('1150000.00')
        ->selisih->toBe('0.00')
        ->ditutup_oleh->toBe($this->teller->id);
});

it('selisih saat tutup wajib diberi keterangan', function () {
    $sesi = $this->sesi->buka(100000, [$this->piket->id]);
    app(TransaksiService::class)->setor($this->rekening, 50000, $this->teller);

    $this->actingAs($this->teller)->post("/kas/sesi/{$sesi->id}/tutup", ['uang_fisik' => 148000])
        ->assertSessionHasErrors(['keterangan' => 'Ada selisih -Rp 2.000. Jelaskan penyebabnya di keterangan.']);

    expect($sesi->fresh()->ditutup_at)->toBeNull();

    $this->actingAs($this->teller)->post("/kas/sesi/{$sesi->id}/tutup", ['uang_fisik' => 148000, 'keterangan' => 'Salah kembalian'])
        ->assertSessionHas('success', 'Sesi kas 30 Oktober 2026 ditutup. Uang fisik kurang Rp 2.000.');

    expect($sesi->fresh())->selisih->toBe('-2000.00')->keterangan->toBe('Salah kembalian');

    $this->actingAs($this->teller)->post("/kas/sesi/{$sesi->id}/tutup", ['uang_fisik' => 150000])
        ->assertSessionHasErrors(['sesi' => 'Sesi kas tanggal 30 Oktober 2026 sudah ditutup.']);
});

it('saran modal diambil dari uang fisik sesi terakhir', function () {
    $sesi = $this->sesi->buka(100000, [$this->piket->id]);
    $this->sesi->tutup($sesi, 90000, 'Uji');
    $this->pengaturan->set('tanggal_transaksi', '2026-10-31');

    expect(app(SesiKasService::class)->saranModal())->toBe('90000.00');
});

it('hanya petugas berizin yang bisa membuka sesi', function () {
    $this->actingAs(userWithRole('admin'))->get('/kas/sesi')->assertForbidden();
    $this->actingAs(userWithRole('admin'))->post('/kas/sesi', ['modal_awal' => 0, 'petugas' => [$this->piket->id]])->assertForbidden();

    $this->actingAs(userWithRole('admin'))->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('app.sesi_kas', null));
});

it('laporan kas harian menampilkan modal, pemasukan, pengeluaran, dan selisih', function () {
    $sesi = $this->sesi->buka(500000, [$this->piket->id], $this->teller);
    app(TransaksiService::class)->setor($this->rekening, 40000, $this->teller);
    app(TransaksiService::class)->tarik($this->rekening, 15000, $this->teller);
    $this->sesi->tutup($sesi, 520000, 'Uang lebih ditemukan', $this->teller);

    $this->actingAs($this->teller)->get('/laporan/harian?tanggal=2026-10-30')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Laporan::Harian')
            ->where('data.modal_awal', '500000.00')
            ->where('data.total_pemasukan', '40000.00')
            ->where('data.total_pengeluaran', '15000.00')
            ->where('data.saldo_akhir', '525000.00')
            ->where('data.sesi.selisih', '-5000.00')
            ->where('data.setelah_tutup', false)
            ->has('data.rincian', 2)
            ->where('kop.periode', 'Tanggal Jumat, 30 Oktober 2026'));

    // Transaksi sistem (tanpa user) setelah sesi ditutup tetap masuk laporan, dengan peringatan.
    $this->travel(1)->minutes();
    app(TransaksiService::class)->setor($this->rekening, 1000);

    $this->actingAs($this->teller)->get('/laporan/harian?tanggal=2026-10-30')
        ->assertInertia(fn (Assert $page) => $page->where('data.setelah_tutup', true)->where('data.saldo_akhir', '526000.00'));
});

it('laporan kas harian tanpa sesi memakai modal 0 dan bisa diunduh Excel', function () {
    app(TransaksiService::class)->setor($this->rekening, 40000);
    Excel::fake();

    $this->actingAs($this->teller)->get('/laporan/harian')
        ->assertInertia(fn (Assert $page) => $page->where('tanggal', '2026-10-30')->where('data.sesi', null)->where('data.saldo_akhir', '40000.00'));

    $this->actingAs($this->teller)->get('/laporan/harian/excel?tanggal=2026-10-30');

    Excel::assertDownloaded('kas_harian_2026-10-30.xlsx', fn (LaporanExport $export) => in_array(['', 'Saldo kas akhir', 40000.0], $export->array(), true));
});

it('laporan kas harian butuh izin dan tanggal yang valid', function () {
    $this->actingAs(userWithRole('admin'))->get('/laporan/harian')->assertForbidden();
    $this->actingAs($this->teller)->get('/laporan/harian?tanggal=30-10-2026')->assertSessionHasErrors('tanggal');
});

it('teller piket wajib diisi dan harus siswa nasabah aktif', function () {
    $this->actingAs($this->teller)->post('/kas/sesi', ['modal_awal' => 0, 'petugas' => []])->assertSessionHasErrors('petugas');
    $this->actingAs($this->teller)->post('/kas/sesi', ['modal_awal' => 0])->assertSessionHasErrors('petugas');

    $guru = Nasabah::factory()->create(['jenis' => 'guru', 'nisn' => null]);
    $nonaktif = Nasabah::factory()->nonaktif()->create();

    foreach ([$guru, $nonaktif] as $bukanPiket) {
        expect(fn () => $this->sesi->buka(0, [$this->piket->id, $bukanPiket->id]))
            ->toThrow(ValidationException::class, 'Teller piket harus siswa yang terdaftar sebagai nasabah aktif.');
    }

    expect(fn () => $this->sesi->buka(0, []))->toThrow(ValidationException::class, 'Pilih minimal satu teller piket.')
        ->and(SesiKas::count())->toBe(0);

    // Duplikat diabaikan.
    expect($this->sesi->buka(0, [$this->piket->id, $this->piket->id])->petugas)->toHaveCount(1);
});

it('pencarian teller piket hanya menampilkan siswa aktif', function () {
    // Nama memakai kata unik agar tidak bentrok dengan nama acak dari factory.
    Nasabah::factory()->create(['nama' => 'Zulpiket Siswa']);
    Nasabah::factory()->create(['nama' => 'Zulpiket Guru', 'jenis' => 'guru', 'nisn' => null]);
    Nasabah::factory()->nonaktif()->create(['nama' => 'Zulpiket Lulus']);

    $this->actingAs($this->teller)->getJson('/nasabah/cari?jenis=siswa&q=Zulpiket')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nama', 'Zulpiket Siswa');

    $this->actingAs($this->teller)->getJson('/nasabah/cari?q=Zulpiket')->assertJsonCount(2);
    $this->actingAs($this->teller)->getJson('/nasabah/cari?jenis=lain')->assertUnprocessable();
});

it('laporan kas harian memuat teller piket dan jam hadirnya', function () {
    $this->travelTo('2026-10-30 07:05:00');
    $this->sesi->buka(100000, [$this->piket->id], $this->teller);
    Excel::fake();

    $this->actingAs($this->teller)->get('/laporan/harian')
        ->assertInertia(fn (Assert $page) => $page
            ->where('data.sesi.petugas.0.nama', 'Budi Santoso')
            ->where('data.sesi.petugas.0.kelas', 'X TKJ 1'));

    $this->actingAs($this->teller)->get('/laporan/harian/excel');

    Excel::assertDownloaded('kas_harian_2026-10-30.xlsx', fn (LaporanExport $export) => in_array(['', 'Teller piket', 'Budi Santoso (X TKJ 1), hadir 07.05'], $export->array(), true));
});
