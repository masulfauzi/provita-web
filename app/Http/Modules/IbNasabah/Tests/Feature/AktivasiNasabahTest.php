<?php

use App\Http\Modules\IbNasabah\Controllers\AktivasiNasabahController;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->travelTo('2026-11-12 09:00:00');
    $this->siswa = Nasabah::factory()->create(['nis' => '25039', 'nisn' => '0090025039', 'nama' => 'Ayu Lestari', 'kelas' => 'X AKL 1', 'no_hp' => null]);
    $this->rekening = Rekening::factory()->create(['nasabah_id' => $this->siswa->id, 'nomor' => '25039']);
    $this->rekeningKedua = Rekening::factory()->create(['nasabah_id' => $this->siswa->id, 'nomor' => '4210025039']);
});

/**
 * Isian langkah 2 aktivasi yang valid.
 */
function isianAktivasi(array $ubah = []): array
{
    return profilValid([
        'no_hp' => '081311112222',
        'no_hp_confirmation' => '0813-1111-2222',
        'password' => 'Ayu2026ok',
        'password_confirmation' => 'Ayu2026ok',
        'persetujuan' => true,
        ...$ubah,
    ]);
}

function cekAktivasi(string $identitas, string $rekening)
{
    return dariIb()->postJson('/api/v1/nasabah/aktivasi/cek', ['identitas' => $identitas, 'no_rekening' => $rekening]);
}

it('siswa: cek NISN + rekening, lengkapi data, lalu login dengan No. HP', function () {
    cekAktivasi('0090025039', '4210025039')
        ->assertOk()
        ->assertExactJson(['data' => [
            'nama' => 'Ayu Lestari',
            'kelas' => 'X AKL 1',
            'jenis_nasabah' => 'siswa',
            'wajib_wali' => true,
            'berlaku_sampai' => '2026-11-12T09:15:00+07:00',
        ]]);

    dariIb()->postJson('/api/v1/nasabah/aktivasi', isianAktivasi(['no_hp' => '+62 813 1111 2222']))
        ->assertCreated()
        ->assertExactJson([
            'message' => 'Aktivasi berhasil. Silakan masuk menggunakan No. HP dan password Anda.',
            'data' => ['no_hp' => '081311112222'],
        ]);

    $akun = AkunNasabah::sole();
    $siswa = $this->siswa->refresh();

    expect($akun->nasabah_id)->toBe($siswa->id)
        ->and($akun->no_hp)->toBe('081311112222')
        ->and(Hash::check('Ayu2026ok', $akun->password))->toBeTrue()
        ->and($akun->diaktivasi_at->toDateTimeString())->toBe('2026-11-12 09:00:00')
        ->and($siswa->no_hp)->toBe('081311112222')
        ->and($siswa->kelurahan)->toBe('Karangkidul')
        ->and($siswa->nama_orang_tua)->toBe('Slamet Riyadi')
        ->and($siswa->alamat)->toContain('Jl. Sidodadi Barat No. 8')
        ->and(session()->has(AktivasiNasabahController::KUNCI_SESI))->toBeFalse()
        ->and(auth('nasabah')->check())->toBeFalse();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'aktivasi', 'causer_id' => $akun->id]);

    dariIb()->postJson('/api/v1/nasabah/login', ['no_hp' => '081311112222', 'password' => 'Ayu2026ok'])
        ->assertOk()
        ->assertJsonPath('data.nama', 'Ayu Lestari')
        ->assertJsonPath('data.no_hp', '081311112222');
});

it('guru dan lembaga aktivasi dengan No. nasabah (awalan NSB- boleh tidak ditulis), tanpa data wali', function (string $jenis) {
    $nasabah = Nasabah::factory()->create(['nomor' => 'NSB-000777', 'jenis' => $jenis, 'nis' => null, 'nisn' => null, 'kelas' => null]);
    Rekening::factory()->create(['nasabah_id' => $nasabah->id, 'nomor' => '9900000001']);

    cekAktivasi('000777', '9900000001')
        ->assertOk()
        ->assertJsonPath('data.jenis_nasabah', $jenis)
        ->assertJsonPath('data.wajib_wali', false);

    cekAktivasi('nsb-000777', '9900000001')->assertOk();

    dariIb()->postJson('/api/v1/nasabah/aktivasi', isianAktivasi(['wali_nama' => null, 'wali_hubungan' => null, 'wali_hp' => null]))
        ->assertCreated();

    expect($nasabah->akunIb()->exists())->toBeTrue();
})->with(['guru', 'lembaga']);

it('data tidak cocok dijawab dengan pesan yang sama', function () {
    $lain = Nasabah::factory()->create();
    Rekening::factory()->create(['nasabah_id' => $lain->id, 'nomor' => '7777777777']);
    $tutup = Rekening::factory()->create(['nasabah_id' => $this->siswa->id, 'nomor' => '5550025039', 'status' => Rekening::TUTUP]);

    $kasus = [
        ['0090025039', '7777777777'],   // rekening milik orang lain
        ['1234567890', '25039'],        // NISN tidak dikenal
        ['0090025039', $tutup->nomor],  // rekening ditutup
    ];

    foreach ($kasus as [$identitas, $rekening]) {
        cekAktivasi($identitas, $rekening)
            ->assertStatus(422)
            ->assertJsonPath('message', 'NISN/No. nasabah atau nomor rekening tidak cocok.')
            ->assertJsonPath('errors.identitas.0', 'NISN/No. nasabah atau nomor rekening tidak cocok.');
    }

    $this->siswa->update(['is_active' => false]);
    cekAktivasi('0090025039', '25039')->assertStatus(422);

    expect(session()->has(AktivasiNasabahController::KUNCI_SESI))->toBeFalse();
});

it('nasabah yang sudah aktivasi dijawab 409 SUDAH_AKTIVASI', function () {
    AkunNasabah::factory()->create(['nasabah_id' => $this->siswa->id]);

    cekAktivasi('0090025039', '25039')
        ->assertStatus(409)
        ->assertJsonPath('kode', 'SUDAH_AKTIVASI');

    expect(session()->has(AktivasiNasabahController::KUNCI_SESI))->toBeFalse();
});

it('percobaan cek ke-6 dalam semenit ditolak 429', function () {
    foreach (range(1, 5) as $i) {
        cekAktivasi('0090025039', '000000000'.$i)->assertStatus(422)->assertJsonPath('sisa_percobaan', 5 - $i);
    }

    $respons = cekAktivasi('0090025039', '25039')->assertStatus(429);

    expect((int) $respons->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and($respons->json('retry_after'))->toBeInt();
});

it('langkah 2 tanpa verifikasi atau setelah kedaluwarsa dijawab 403 VERIFIKASI_DIPERLUKAN', function () {
    $pesan = ['message' => 'Sesi aktivasi tidak ditemukan atau sudah kedaluwarsa. Ulangi verifikasi NISN/No. nasabah dan nomor rekening.', 'kode' => 'VERIFIKASI_DIPERLUKAN'];

    dariIb()->postJson('/api/v1/nasabah/aktivasi', isianAktivasi())->assertForbidden()->assertExactJson($pesan);

    cekAktivasi('0090025039', '25039')->assertOk();
    $this->travel(16)->minutes();

    dariIb()->postJson('/api/v1/nasabah/aktivasi', isianAktivasi())->assertForbidden()->assertExactJson($pesan);
    expect(AkunNasabah::count())->toBe(0);
});

it('memvalidasi No. HP, password, wali, dan persetujuan', function (array $ubah, string $field, string $pesan) {
    $lain = AkunNasabah::factory()->create(['no_hp' => '081299990000']);

    cekAktivasi('0090025039', '25039')->assertOk();

    dariIb()->postJson('/api/v1/nasabah/aktivasi', isianAktivasi($ubah))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field => $pesan]);

    expect(AkunNasabah::count())->toBe(1);
})->with([
    'hp dipakai akun lain' => [['no_hp' => '6281299990000', 'no_hp_confirmation' => '081299990000'], 'no_hp', 'No. HP sudah dipakai akun lain.'],
    'format hp' => [['no_hp' => '0712345', 'no_hp_confirmation' => '0712345'], 'no_hp', 'Format No. HP tidak valid.'],
    'konfirmasi hp beda' => [['no_hp_confirmation' => '081311112223'], 'no_hp_confirmation', 'Konfirmasi No. HP tidak sama.'],
    'password pendek' => [['password' => 'ab12', 'password_confirmation' => 'ab12'], 'password', 'Password minimal 8 karakter.'],
    'password tanpa angka' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password', 'Password harus mengandung huruf dan angka.'],
    'konfirmasi password beda' => [['password_confirmation' => 'Lain2026x'], 'password_confirmation', 'Konfirmasi password tidak sama.'],
    'wali siswa' => [['wali_nama' => null], 'wali_nama', 'Nama orang tua/wali wajib diisi.'],
    'persetujuan' => [['persetujuan' => false], 'persetujuan', 'Persetujuan wajib dicentang.'],
]);

it('password tidak boleh sama dengan identitas nasabah', function () {
    $this->siswa->update(['nisn' => 'abc1234567']);
    Rekening::factory()->create(['nasabah_id' => $this->siswa->id, 'nomor' => 'rek12345678']);

    cekAktivasi('abc1234567', '25039')->assertOk();

    foreach (['abc1234567', 'rek12345678'] as $password) {
        dariIb()->postJson('/api/v1/nasabah/aktivasi', isianAktivasi(['password' => $password, 'password_confirmation' => $password]))
            ->assertJsonValidationErrors(['password' => 'Password tidak boleh sama dengan NIS/NISN, No. HP, atau nomor rekening.']);
    }
});

it('menormalkan No. HP', function (string $input, string $hasil) {
    expect(app(AkunNasabahService::class)->normalisasiHp($input))->toBe($hasil);
})->with([
    ['081234567890', '081234567890'],
    ['6281234567890', '081234567890'],
    ['+62 812-3456-7890', '081234567890'],
    ['(0812) 3456 7890', '081234567890'],
]);
