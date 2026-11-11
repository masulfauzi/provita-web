<?php

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Models\LoginNasabah;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;

beforeEach(function () {
    $this->nasabah = Nasabah::factory()->create(['nis' => '25041', 'nama' => 'Rizky Ramadhan', 'kelas' => 'XII TJKT 3']);
    $this->rekening = Rekening::factory()->create(['nasabah_id' => $this->nasabah->id, 'nomor' => '1100325041']);
    $this->akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);
});

it('login dengan NIS, nomor nasabah, atau nomor rekening', function (string $kolom) {
    $identitas = match ($kolom) {
        'nis' => '25041',
        'nomor' => $this->nasabah->nomor,
        'rekening' => '1100325041',
    };

    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => $identitas, 'password' => 'Provita2026'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->nasabah->id)
        ->assertJsonPath('data.nis', '25041')
        ->assertJsonPath('data.nama_panggilan', 'Rizky')
        ->assertJsonPath('data.kelas', 'XII TJKT 3')
        ->assertJsonPath('data.jenis_nasabah', 'siswa')
        ->assertJsonPath('data.nomor_rekening', ['1100325041'])
        ->assertJsonPath('data.wajib_ganti_password', false)
        ->assertJsonPath('data.profil_lengkap', true)
        ->assertJsonPath('data.login_terakhir', null);

    expect(LoginNasabah::where('akun_nasabah_id', $this->akun->id)->where('berhasil', true)->count())->toBe(1);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'login', 'causer_id' => $this->akun->id]);
})->with(['nis', 'nomor', 'rekening']);

it('pesan gagal sama untuk identitas tidak dikenal dan password salah', function () {
    foreach ([['99999', 'Provita2026', 4], ['25041', 'salah123', 4]] as [$nis, $password, $sisa]) {
        dariIb()->postJson('/api/v1/nasabah/login', ['nis' => $nis, 'password' => $password])
            ->assertStatus(422)
            ->assertExactJson([
                'message' => 'NIS atau password salah.',
                'errors' => ['nis' => ['NIS atau password salah.']],
                'sisa_percobaan' => $sisa,
            ]);
    }

    expect(LoginNasabah::where('berhasil', false)->count())->toBe(1);
});

it('percobaan ke-6 dalam semenit ditolak 429 dengan Retry-After', function () {
    foreach (range(1, 5) as $i) {
        dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'salah'])->assertStatus(422);
    }

    $respons = dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])->assertStatus(429);

    expect((int) $respons->headers->get('Retry-After'))->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and($respons->json('retry_after'))->toBeInt();

    // NIS lain dari IP yang sama tidak ikut terkunci.
    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '00000', 'password' => 'x'])->assertStatus(422);
});

it('akun atau nasabah nonaktif tidak bisa login', function () {
    $this->akun->update(['is_active' => false]);
    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])->assertStatus(422);

    $this->akun->update(['is_active' => true]);
    $this->nasabah->update(['is_active' => false]);
    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])->assertStatus(422);
});

it('me butuh login nasabah; login petugas tidak berlaku', function () {
    dariIb()->getJson('/api/v1/nasabah/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);

    $this->actingAs(userWithRole('bendahara'))
        ->withHeaders(['Origin' => 'http://localhost:5173'])
        ->getJson('/api/v1/nasabah/me')
        ->assertUnauthorized();
});

it('akun nasabah tidak bisa membuka panel petugas', function () {
    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])->assertOk();

    expect(auth('nasabah')->check())->toBeTrue();
    $this->get('/dashboard')->assertRedirect('/login');
});

it('login_terakhir adalah login sebelum sesi ini', function () {
    LoginNasabah::create(['akun_nasabah_id' => $this->akun->id, 'waktu' => '2026-11-09 11:41:00', 'berhasil' => true, 'perangkat' => 'Chrome · Android']);
    LoginNasabah::create(['akun_nasabah_id' => $this->akun->id, 'waktu' => '2026-11-10 08:00:00', 'berhasil' => false]);

    dariIb()->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])
        ->assertJsonPath('data.login_terakhir', '2026-11-09T11:41:00+07:00');
});

it('logout mengakhiri sesi', function () {
    sebagaiNasabah($this->akun)->postJson('/api/v1/nasabah/logout')->assertNoContent();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'logout']);
    expect(auth('nasabah')->check())->toBeFalse();
});

it('versi sesi lama ditolak', function () {
    sebagaiNasabah($this->akun)->getJson('/api/v1/nasabah/me')->assertOk();

    $this->akun->naikkanVersiSesi();

    sebagaiNasabah($this->akun, 0)->getJson('/api/v1/nasabah/me')->assertUnauthorized();
});

it('membuat ringkasan perangkat dari user agent', function () {
    $service = app(AkunNasabahService::class);

    expect($service->perangkat('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36'))->toBe('Chrome · Android')
        ->and($service->perangkat('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0'))->toBe('Firefox · Windows')
        ->and($service->perangkat('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'))->toBe('Safari · iOS');
});

it('request tanpa origin frontend (tanpa sesi) dijawab rapi, bukan 500', function () {
    $this->postJson('/api/v1/nasabah/login', ['nis' => '25041', 'password' => 'Provita2026'])
        ->assertStatus(419)
        ->assertJson(['message' => 'Permintaan harus dari aplikasi Internet Banking (sesi/CSRF tidak tersedia).']);

    $this->getJson('/api/v1/nasabah/me')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    $this->getJson('/api/v1/nasabah/rekening')->assertUnauthorized();
});
