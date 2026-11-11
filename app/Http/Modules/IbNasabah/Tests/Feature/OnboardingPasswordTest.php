<?php

use App\Http\Middleware\AutentikasiNasabah;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->nasabah = Nasabah::factory()->create(['nis' => '25039']);
    $this->rekening = Rekening::factory()->create(['nasabah_id' => $this->nasabah->id, 'nomor' => '1100325039']);
});

it('wajib ganti password: hanya me, password, logout yang boleh', function () {
    $akun = AkunNasabah::factory()->wajibGantiPassword()->create(['nasabah_id' => $this->nasabah->id, 'password' => 'rahasia123']);

    sebagaiNasabah($akun)->getJson('/api/v1/nasabah/me')->assertOk()->assertJsonPath('data.wajib_ganti_password', true);

    foreach (['/api/v1/nasabah/profil', '/api/v1/nasabah/rekening', "/api/v1/nasabah/rekening/{$this->rekening->id}"] as $url) {
        sebagaiNasabah($akun)->getJson($url)->assertForbidden()
            ->assertExactJson(['message' => 'Anda wajib mengganti password.', 'kode' => 'WAJIB_GANTI_PASSWORD']);
    }

    sebagaiNasabah($akun)->putJson('/api/v1/nasabah/profil', [])->assertForbidden()->assertJsonPath('kode', 'WAJIB_GANTI_PASSWORD');
    sebagaiNasabah($akun)->postJson('/api/v1/nasabah/logout')->assertNoContent();
});

it('profil belum lengkap: me, profil, password, logout boleh; lainnya WAJIB_LENGKAPI_DATA', function () {
    $akun = AkunNasabah::factory()->profilBelumLengkap()->create(['nasabah_id' => $this->nasabah->id]);

    sebagaiNasabah($akun)->getJson('/api/v1/nasabah/me')->assertOk()->assertJsonPath('data.profil_lengkap', false);
    sebagaiNasabah($akun)->getJson('/api/v1/nasabah/profil')->assertOk();
    sebagaiNasabah($akun)->putJson('/api/v1/nasabah/password', [])->assertStatus(422);

    foreach (['/api/v1/nasabah/rekening', "/api/v1/nasabah/rekening/{$this->rekening->id}", "/api/v1/nasabah/rekening/{$this->rekening->id}/mutasi"] as $url) {
        sebagaiNasabah($akun)->getJson($url)->assertForbidden()
            ->assertExactJson(['message' => 'Anda wajib melengkapi data pribadi.', 'kode' => 'WAJIB_LENGKAPI_DATA']);
    }
});

it('WAJIB_GANTI_PASSWORD diperiksa sebelum WAJIB_LENGKAPI_DATA', function () {
    $akun = AkunNasabah::factory()->wajibGantiPassword()->create(['nasabah_id' => $this->nasabah->id]);

    expect($akun->profilLengkap())->toBeFalse();
    sebagaiNasabah($akun)->getJson('/api/v1/nasabah/rekening')->assertJsonPath('kode', 'WAJIB_GANTI_PASSWORD');
});

it('memvalidasi aturan password baru', function (array $input, string $field, string $pesan) {
    $akun = AkunNasabah::factory()->wajibGantiPassword()->create(['nasabah_id' => $this->nasabah->id, 'password' => 'rahasia123']);

    sebagaiNasabah($akun)
        ->putJson('/api/v1/nasabah/password', [...['password_lama' => 'rahasia123', 'password' => 'Baru2026x', 'password_confirmation' => 'Baru2026x'], ...$input])
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field => $pesan]);
})->with([
    'password lama salah' => [['password_lama' => 'keliru'], 'password_lama', 'Password saat ini salah.'],
    'kurang dari 8' => [['password' => 'ab12', 'password_confirmation' => 'ab12'], 'password', 'Password minimal 8 karakter.'],
    'tanpa angka' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password', 'Password harus mengandung huruf dan angka.'],
    'tanpa huruf' => [['password' => '12345678', 'password_confirmation' => '12345678'], 'password', 'Password harus mengandung huruf dan angka.'],
    'sama dengan lama' => [['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'], 'password', 'Password baru tidak boleh sama dengan password lama.'],
    'sama dengan nomor rekening' => [['password' => '1100325039', 'password_confirmation' => '1100325039'], 'password', 'Password harus mengandung huruf dan angka.'],
    'konfirmasi beda' => [['password_confirmation' => 'Lain2026x'], 'password_confirmation', 'Konfirmasi password tidak sama.'],
]);

it('password tidak boleh sama dengan NIS, nomor nasabah, atau nomor rekening', function () {
    $this->nasabah->update(['nis' => 'abc12345']);
    $akun = AkunNasabah::factory()->wajibGantiPassword()->create(['nasabah_id' => $this->nasabah->id, 'password' => 'rahasia123']);

    sebagaiNasabah($akun)
        ->putJson('/api/v1/nasabah/password', ['password_lama' => 'rahasia123', 'password' => 'abc12345', 'password_confirmation' => 'abc12345'])
        ->assertJsonValidationErrors(['password' => 'Password tidak boleh sama dengan NIS / nomor rekening.']);
});

it('ganti password: wajib_ganti_password selesai, sesi lain keluar, sesi ini tetap', function () {
    $akun = AkunNasabah::factory()->wajibGantiPassword()->create(['nasabah_id' => $this->nasabah->id, 'password' => 'rahasia123']);

    sebagaiNasabah($akun)
        ->putJson('/api/v1/nasabah/password', ['password_lama' => 'rahasia123', 'password' => 'Baru2026x', 'password_confirmation' => 'Baru2026x'])
        ->assertOk()
        ->assertExactJson(['message' => 'Password berhasil diganti.']);

    $akun->refresh();

    expect($akun->wajib_ganti_password)->toBeFalse()
        ->and($akun->password_diganti_at)->not->toBeNull()
        ->and($akun->versi_sesi)->toBe(1)
        ->and(Hash::check('Baru2026x', $akun->password))->toBeTrue()
        ->and(session(AutentikasiNasabah::KUNCI_VERSI))->toBe(1);

    // Perangkat lain masih memegang versi sesi lama.
    sebagaiNasabah($akun, 0)->getJson('/api/v1/nasabah/me')->assertUnauthorized();
    sebagaiNasabah($akun, 1)->getJson('/api/v1/nasabah/me')->assertOk()->assertJsonPath('data.wajib_ganti_password', false);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'ganti_password']);
});

it('ganti password dibatasi 10 kali per menit', function () {
    $akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id]);

    foreach (range(1, 10) as $i) {
        sebagaiNasabah($akun)->putJson('/api/v1/nasabah/password', ['password_lama' => 'salah'])->assertStatus(422);
    }

    sebagaiNasabah($akun)->putJson('/api/v1/nasabah/password', ['password_lama' => 'salah'])->assertStatus(429);
});
