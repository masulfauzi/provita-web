<?php

use App\Http\Modules\Akuntansi\Models\Jurnal;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Database\Seeders\RekeningSeeder;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RekeningSeeder::class);
    $this->bendahara = userWithRole('bendahara');
});

/**
 * File Excel uji berformat sistem lama (data palsu). Baris pertama header.
 *
 * @param  list<array<int, mixed>>  $baris  [kelas, nipd, nisn, nama, saldo, jenis_nasabah, jenis_rekening]
 */
function fileImpor(array $baris): UploadedFile
{
    $sheet = (new Spreadsheet)->getActiveSheet();
    $sheet->fromArray(['no', 'kelas', 'nipd', 'nisn', 'nama_nasabah', 'saldo', 'jenis_nasabah', 'jenis_rekening'], null, 'A1');

    foreach ($baris as $i => $data) {
        $sheet->fromArray([$i + 1, ...$data], null, 'A'.($i + 2), true);
    }

    $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
    (new Xlsx($sheet->getParent()))->save($path);

    return new UploadedFile($path, 'data_uji.xlsx', null, null, true);
}

function dataUji(): array
{
    return [
        ['X AKL 1', 25001, '0012345601', 'Budi Santoso', 150000, 'Siswa ', 'Tabungan Pelajar'],                  // 2
        [null, 42001, '0012345601', 'BUDI S (Tab. Wisata)', 50000, 'Siswa', 'Tabungan Wisata'],                   // 3
        ['XII AKL 3', null, '0012345601', 'Budi', null, 'Siswa', 'Tabungan Wisuda'],                              // 4
        ['XI PM 2', 25002, 12345602, 'Citra Lestari', 0, 'siswa', 'Tabungan pelajar'],                            // 5 NISN 8 digit (angka)
        [null, 42002, '0012345603', 'Dedi Pratama (TAB WISATA)', 5000, 'Siswa', 'Tabungan Wisata'],               // 6 tanpa Pelajar
        [null, 318, null, 'Pak Guru Uji', 1000000, 'guru', 'Tabungan Pelajar'],                                   // 7
        [null, 42750, null, 'Kas Kelas XI AKL 3', 3025000, 'lembaga', 'Tabungan Pelajar'],                        // 8
        [null, 42751, null, 'KAS KELAS XI AKL 3', 5000, 'lembaga', 'Tabungan Pelajar'],                           // 9 nama sama
        [null, 42977, null, 'OSIS Katalog', 700000, 'lembaga', 'Tabungan Katalog'],                               // 10 alias
    ];
}

it('pratinjau menampilkan ringkasan tanpa menyimpan data', function () {
    $this->actingAs($this->bendahara)->get('/nasabah/impor')
        ->assertInertia(fn (Assert $page) => $page->component('Nasabah::Impor')->where('hasil', null)->where('tanggalDefault', '2026-10-30'));

    $this->actingAs($this->bendahara)
        ->post('/nasabah/impor/periksa', ['file' => fileImpor(dataUji()), 'tanggal' => '2026-10-30'])
        ->assertRedirect();

    $this->actingAs($this->bendahara)->get('/nasabah/impor')
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasil.file', 'data_uji.xlsx')
            ->where('hasil.ringkasan.jumlah_baris', 9)
            ->where('hasil.ringkasan.jumlah_nasabah', 7)
            ->where('hasil.ringkasan.jumlah_rekening', 9)
            ->where('hasil.ringkasan.total_saldo', '4935000.00')
            ->where('hasil.error', [])
            ->where('hasil.peringatan', fn ($p) => collect($p)->pluck('baris')->sort()->values()->all() === [4, 6, 9]));

    expect(Nasabah::count())->toBe(0)->and(Rekening::count())->toBe(0)->and(Jurnal::count())->toBe(0);
});

it('mengimpor nasabah, rekening dengan nomor lama, saldo awal, dan satu jurnal', function () {
    $this->actingAs($this->bendahara)
        ->post('/nasabah/impor', ['file' => fileImpor(dataUji()), 'tanggal' => '2026-10-30'])
        ->assertRedirect('/nasabah')
        ->assertSessionHas('success', 'Impor selesai: 7 nasabah, 9 rekening, saldo Rp 4.935.000 (jurnal JU-2026-000001).');

    $budi = Nasabah::where('nisn', '0012345601')->firstOrFail();

    expect($budi)
        ->nama->toBe('Budi Santoso')
        ->kelas->toBe('X AKL 1')
        ->nis->toBe('25001')
        ->jenis_kelamin->toBeNull()
        ->and($budi->rekening()->with('jenisRekening')->get()->mapWithKeys(fn ($r) => [$r->jenisRekening->kode => [$r->nomor, $r->saldo_awal, $r->saldo]])->all())
        ->toEqual([
            'tabungan' => ['25001', '150000.00', '150000.00'],
            'wisata' => ['42001', '50000.00', '50000.00'],
            'wisuda' => ['420012345601', '0.00', '0.00'],
        ]);

    expect(Nasabah::where('nisn', '0012345602')->value('nama'))->toBe('Citra Lestari')
        ->and(Nasabah::where('nisn', '0012345603')->first(['nama', 'kelas', 'nis'])->toArray())->toBe(['nama' => 'Dedi Pratama', 'kelas' => null, 'nis' => null])
        ->and(Nasabah::where('jenis', 'lembaga')->where('nama', 'like', 'Kas Kelas%')->count())->toBe(2)
        ->and(Rekening::where('nomor', '318')->first()->nasabah->only('jenis', 'nis'))->toBe(['jenis' => 'guru', 'nis' => null])
        ->and(Rekening::where('nomor', '42977')->first()->jenisRekening->kode)->toBe('foto_katalog')
        ->and(Rekening::pluck('tanggal_buka')->map->toDateString()->unique()->all())->toBe(['2026-10-30'])
        ->and(Transaksi::count())->toBe(0)
        ->and((string) Rekening::sum('saldo'))->toBe('4935000');

    $jurnal = Jurnal::with('detail.akun')->firstOrFail();

    expect($jurnal->tanggal->toDateString())->toBe('2026-10-30')
        ->and($jurnal->detail->map(fn ($d) => [$d->akun->kode, $d->debit, $d->kredit])->all())->toBe([
            ['1-101', '4935000.00', '0.00'],
            ['2-101', '0.00', '4935000.00'],
        ]);

    $this->artisan('saldo:rekonsiliasi')->assertSuccessful();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'impor', 'causer_id' => $this->bendahara->id]);
});

it('menolak impor kedua kali tanpa menyimpan sebagian', function () {
    $this->actingAs($this->bendahara)->post('/nasabah/impor', ['file' => fileImpor(dataUji()), 'tanggal' => '2026-10-30']);

    $this->actingAs($this->bendahara)
        ->post('/nasabah/impor', ['file' => fileImpor(dataUji()), 'tanggal' => '2026-10-30'])
        ->assertSessionHasErrors('file');

    expect(Nasabah::count())->toBe(7)->and(Jurnal::count())->toBe(1);

    $this->actingAs($this->bendahara)->post('/nasabah/impor/periksa', ['file' => fileImpor(dataUji()), 'tanggal' => '2026-10-30']);
    $this->actingAs($this->bendahara)->get('/nasabah/impor')
        ->assertInertia(fn (Assert $page) => $page->where('hasil.error', fn ($e) => collect($e)->contains('pesan', 'Nomor rekening 25001 sudah ada di sistem.')
            && collect($e)->contains('pesan', 'NISN 0012345601 sudah terdaftar sebagai nasabah.')));
});

it('melaporkan error per baris dan tidak menyimpan apa pun', function () {
    $file = fileImpor([
        ['X AKL 1', 25001, '0012345601', 'Budi', 1000, 'Siswa', 'Tabungan Pelajar'],
        ['X AKL 1', 25001, '0012345602', 'Citra', 1000, 'Siswa', 'Tabungan Pelajar'],     // nipd ganda
        ['X AKL 1', 25003, '0012345603', 'Dedi', -5, 'Siswa', 'Tabungan Pelajar'],        // saldo negatif
        ['X AKL 1', 25004, '0012345604', 'Eko', 1000, 'Alumni', 'Tabungan Pelajar'],      // jenis nasabah
        ['X AKL 1', 25005, '0012345605', 'Fajar', 1000, 'Siswa', 'Tabungan Haji'],        // jenis rekening
        ['X AKL 1', 25006, null, 'Gita', 1000, 'Siswa', 'Tabungan Pelajar'],              // siswa tanpa NISN
    ]);

    $this->actingAs($this->bendahara)->post('/nasabah/impor/periksa', ['file' => $file, 'tanggal' => '2026-10-30']);

    $this->actingAs($this->bendahara)->get('/nasabah/impor')
        ->assertInertia(fn (Assert $page) => $page->where('hasil.error', [
            ['baris' => 2, 'pesan' => 'Nomor rekening (nipd) 25001 dipakai lebih dari satu baris: 2, 3.'],
            ['baris' => 4, 'pesan' => 'Saldo "-5" tidak valid (harus angka tidak negatif, maksimal 2 desimal).'],
            ['baris' => 5, 'pesan' => 'Jenis nasabah "Alumni" tidak dikenal (siswa, guru, karyawan, lembaga).'],
            ['baris' => 6, 'pesan' => 'Jenis rekening "Tabungan Haji" tidak ada di sistem.'],
            ['baris' => 7, 'pesan' => 'Siswa wajib punya NISN.'],
        ]));

    $this->actingAs($this->bendahara)
        ->post('/nasabah/impor', ['file' => $file, 'tanggal' => '2026-10-30'])
        ->assertSessionHasErrors(['file' => 'File masih berisi 5 error. Perbaiki lalu periksa ulang.']);

    expect(Nasabah::count())->toBe(0)->and(Rekening::count())->toBe(0);
});

it('menolak file tanpa kolom wajib atau bukan xlsx', function () {
    $sheet = (new Spreadsheet)->getActiveSheet();
    $sheet->fromArray(['nama', 'saldo'], null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
    (new Xlsx($sheet->getParent()))->save($path);

    $this->actingAs($this->bendahara)->post('/nasabah/impor/periksa', ['file' => new UploadedFile($path, 'salah.xlsx', null, null, true), 'tanggal' => '2026-10-30']);
    $this->actingAs($this->bendahara)->get('/nasabah/impor')
        ->assertInertia(fn (Assert $page) => $page->where('hasil.error.0.pesan', 'Kolom wajib tidak ditemukan: kelas, nipd, nisn, nama_nasabah, jenis_nasabah, jenis_rekening.'));

    $this->actingAs($this->bendahara)
        ->post('/nasabah/impor/periksa', ['file' => UploadedFile::fake()->create('data.csv', 1, 'text/csv'), 'tanggal' => '2026-10-30'])
        ->assertSessionHasErrors('file');
});

it('hanya bendahara yang bisa mengimpor', function () {
    foreach (['teller', 'admin'] as $peran) {
        $user = userWithRole($peran);

        $this->actingAs($user)->get('/nasabah/impor')->assertForbidden();
        $this->actingAs($user)->post('/nasabah/impor', ['file' => fileImpor(dataUji()), 'tanggal' => '2026-10-30'])->assertForbidden();
    }

    expect(Nasabah::count())->toBe(0);
});
