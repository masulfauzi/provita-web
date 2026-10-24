<?php

use App\Http\Modules\Akuntansi\Models\Akun;
use App\Http\Modules\Akuntansi\Services\JurnalService;
use App\Http\Modules\Laporan\Exports\LaporanExport;
use App\Http\Modules\Laporan\Services\LaporanService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\JenisRekening;
use App\Http\Modules\Rekening\Services\RekeningService;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

/*
| Skenario Oktober 2026:
|  01/10 setoran modal 1.000.000 (jurnal umum)       kas +1.000.000
|  05/10 buka rekening, setoran awal 100.000          kas   +100.000
|  10/10 penarikan 20.000                             kas    -20.000
|  15/10 tutup rekening: biaya adm 5.000, sisa 75.000 kas    -75.000
|  20/10 beban operasional 10.000 (jurnal umum)       kas    -10.000
|  02/11 setoran 50.000 rekening lain (di luar periode)
| Saldo kas 31/10 = 995.000; simpanan = 0; laba = 5.000 - 10.000 = -5.000.
*/
beforeEach(function () {
    $this->seed(PengaturanSeeder::class);
    $pengaturan = app(PengaturanService::class);
    $pengaturan->set('adm_penutupan', 5000);
    $tanggal = fn (string $nilai) => $pengaturan->set('tanggal_transaksi', $nilai);

    // Layanan di-resolve ulang setiap langkah karena PengaturanService menyimpan nilai per instance
    // (di aplikasi nyata satu request = satu tanggal transaksi).
    $jurnal = fn () => app(JurnalService::class);
    $rekening = fn () => app(RekeningService::class);
    $transaksi = fn () => app(TransaksiService::class);
    $kas = Akun::peran('kas')->id;
    $tabungan = JenisRekening::kode('tabungan');

    $jurnal()->catat('2026-10-01', 'Setoran modal', [
        ['akun_id' => $kas, 'debit' => 1000000],
        ['akun_id' => Akun::peran('modal')->id, 'kredit' => 1000000],
    ]);

    $tanggal('2026-10-05');
    $milikSiti = $rekening()->buka(Nasabah::factory()->create(), $tabungan, 100000);

    $tanggal('2026-10-10');
    $transaksi()->tarik($milikSiti, 20000);

    $tanggal('2026-10-15');
    $transaksi()->tutup($milikSiti);

    $jurnal()->catat('2026-10-20', 'Beban listrik', [
        ['akun_id' => Akun::where('kode', '5-102')->value('id'), 'debit' => 10000],
        ['akun_id' => $kas, 'kredit' => 10000],
    ]);

    $tanggal('2026-11-02');
    $rekening()->buka(Nasabah::factory()->create(), $tabungan, 50000);

    $tanggal('2026-10-31');
    $this->laporan = app(LaporanService::class);
});

$nilai = fn (iterable $akun) => collect($akun)->mapWithKeys(fn ($item) => [$item['kode'] => $item['nilai']])->all();

it('neraca seimbang dengan laba berjalan di ekuitas', function () use ($nilai) {
    $neraca = $this->laporan->neraca('2026-10-31');

    expect($nilai($neraca['aset']))->toMatchArray(['1-101' => '995000.00', '1-102' => '0.00'])
        ->and($nilai($neraca['kewajiban']))->toBe(['2-101' => '0.00'])
        ->and($nilai($neraca['ekuitas']))->toMatchArray(['3-101' => '1000000.00'])
        ->and($neraca)->toMatchArray([
            'laba_berjalan' => '-5000.00',
            'total_aset' => '995000.00',
            'total_kewajiban' => '0.00',
            'total_ekuitas' => '995000.00',
            'total_pasiva' => '995000.00',
            'seimbang' => true,
        ]);

    // Per 2 November: setoran 50.000 menambah kas dan simpanan.
    expect($this->laporan->neraca('2026-11-02'))->toMatchArray([
        'total_aset' => '1045000.00',
        'total_kewajiban' => '50000.00',
        'seimbang' => true,
    ]);
});

it('laba rugi hanya menghitung periode yang dipilih', function () use ($nilai) {
    $labaRugi = $this->laporan->labaRugi('2026-10-01', '2026-10-31');

    expect($nilai($labaRugi['pendapatan']))->toMatchArray(['4-101' => '5000.00'])
        ->and($nilai($labaRugi['beban']))->toMatchArray(['5-102' => '10000.00'])
        ->and($labaRugi)->toMatchArray(['total_pendapatan' => '5000.00', 'total_beban' => '10000.00', 'laba' => '-5000.00']);

    expect($this->laporan->labaRugi('2026-10-16', '2026-10-31')['total_pendapatan'])->toBe('0.00');
});

it('arus kas mengelompokkan penerimaan dan pengeluaran per akun lawan', function () use ($nilai) {
    $arusKas = $this->laporan->arusKas('2026-10-01', '2026-10-31');

    expect($nilai($arusKas['penerimaan']))->toBe(['2-101' => '100000.00', '3-101' => '1000000.00'])
        ->and($nilai($arusKas['pengeluaran']))->toBe(['2-101' => '95000.00', '5-102' => '10000.00'])
        ->and($arusKas)->toMatchArray([
            'saldo_awal' => '0.00',
            'total_penerimaan' => '1100000.00',
            'total_pengeluaran' => '105000.00',
            'kenaikan' => '995000.00',
            'saldo_akhir' => '995000.00',
        ]);

    expect($this->laporan->arusKas('2026-11-01', '2026-11-30'))->toMatchArray([
        'saldo_awal' => '995000.00',
        'saldo_akhir' => '1045000.00',
    ]);
});

it('neraca saldo seimbang', function () {
    $neracaSaldo = $this->laporan->neracaSaldo('2026-10-31');
    $akun = collect($neracaSaldo['akun'])->keyBy('kode');

    expect($akun['1-101'])->toMatchArray(['debit' => '995000.00', 'kredit' => '0.00'])
        ->and($akun['3-101'])->toMatchArray(['debit' => '0.00', 'kredit' => '1000000.00'])
        ->and($akun['5-102'])->toMatchArray(['debit' => '10000.00', 'kredit' => '0.00'])
        ->and($neracaSaldo)->toMatchArray(['total_debit' => '1005000.00', 'total_kredit' => '1005000.00', 'seimbang' => true]);
});

it('buku besar menampilkan saldo awal dan saldo berjalan', function () {
    $kas = Akun::peran('kas');
    $oktober = $this->laporan->bukuBesar($kas, '2026-10-01', '2026-10-31');

    expect($oktober['saldo_awal'])->toBe('0.00')
        ->and(array_column($oktober['baris'], 'saldo'))->toBe(['1000000.00', '1100000.00', '1080000.00', '1005000.00', '995000.00'])
        ->and($oktober['saldo_akhir'])->toBe('995000.00');

    $november = $this->laporan->bukuBesar($kas, '2026-11-01', '2026-11-30');

    expect($november['saldo_awal'])->toBe('995000.00')
        ->and($november['baris'])->toHaveCount(1)
        ->and($november['saldo_akhir'])->toBe('1045000.00');
});

it('teller dan bendahara membuka semua laporan dengan periode default bulan berjalan', function (string $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->get('/laporan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Laporan::Index')
            ->where('periode', ['dari' => '2026-10-01', 'sampai' => '2026-10-31'])
            ->where('ringkasan.kas', '995000.00')
            ->where('ringkasan.laba', '-5000.00')
            ->where('ringkasan.seimbang', true));

    foreach (['neraca', 'laba-rugi', 'arus-kas', 'neraca-saldo', 'buku-besar'] as $laporan) {
        $this->actingAs($user)->get("/laporan/{$laporan}?dari=2026-10-01&sampai=2026-10-31")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan::Laporan')
                ->where('laporan', $laporan)
                ->where('kop.nama_bank', 'Bank Provita')
                ->has('data'));
    }

    $this->actingAs($user)->get('/laporan/neraca')
        ->assertInertia(fn (Assert $page) => $page->where('kop.periode', 'Per 31 Oktober 2026'));
    $this->actingAs($user)->get('/laporan/buku-besar?akun='.Akun::peran('modal')->id)
        ->assertInertia(fn (Assert $page) => $page->where('data.akun.kode', '3-101')->where('data.saldo_akhir', '1000000.00'));
})->with(['teller', 'bendahara']);

it('admin tidak boleh membuka laporan dan laporan tak dikenal 404', function () {
    $this->actingAs(userWithRole('admin'))->get('/laporan')->assertForbidden();
    $this->actingAs(userWithRole('admin'))->get('/laporan/neraca/excel')->assertForbidden();
    $this->actingAs(userWithRole('bendahara'))->get('/laporan/rahasia')->assertNotFound();
    $this->actingAs(userWithRole('bendahara'))->get('/laporan/neraca?sampai=31-10-2026')->assertSessionHasErrors('sampai');
});

it('mengunduh laporan dalam format Excel', function () {
    Excel::fake();

    $this->actingAs(userWithRole('teller'))->get('/laporan/laba-rugi/excel?dari=2026-10-01&sampai=2026-10-31');

    Excel::assertDownloaded('laba_rugi_2026-10-01_2026-10-31.xlsx', function (LaporanExport $export) {
        $baris = $export->array();

        return $baris[0] === ['Bank Provita']
            && in_array(['', 'Rugi bersih', -5000.0], $baris, true)
            && $export->title() === 'Laporan laba rugi';
    });
});

it('file Excel benar-benar terbentuk', function (string $laporan) {
    $response = $this->actingAs(userWithRole('bendahara'))->get("/laporan/{$laporan}/excel?dari=2026-10-01&sampai=2026-10-31");

    $response->assertOk()->assertDownload();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
})->with(['neraca', 'laba-rugi', 'arus-kas', 'neraca-saldo', 'buku-besar']);
