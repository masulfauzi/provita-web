<?php

use App\Http\Middleware\AutentikasiNasabah;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->nasabah = Nasabah::factory()->create(['nis' => '25039', 'nisn' => '0090025039']);
    Rekening::factory()->create(['nasabah_id' => $this->nasabah->id, 'nomor' => '1100325039']);
    $this->akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id, 'no_hp' => '081234567890', 'password' => 'rahasia123']);
});

it('memvalidasi aturan password baru', function (array $input, string $field, string $pesan) {
    sebagaiNasabah($this->akun)
        ->putJson('/api/v1/nasabah/password', [...['password_lama' => 'rahasia123', 'password' => 'Baru2026x', 'password_confirmation' => 'Baru2026x'], ...$input])
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field => $pesan]);
})->with([
    'password lama salah' => [['password_lama' => 'keliru'], 'password_lama', 'Password saat ini salah.'],
    'kurang dari 8' => [['password' => 'ab12', 'password_confirmation' => 'ab12'], 'password', 'Password minimal 8 karakter.'],
    'tanpa angka' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password', 'Password harus mengandung huruf dan angka.'],
    'tanpa huruf' => [['password' => '12345678', 'password_confirmation' => '12345678'], 'password', 'Password harus mengandung huruf dan angka.'],
    'sama dengan lama' => [['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'], 'password', 'Password baru tidak boleh sama dengan password lama.'],
    'konfirmasi beda' => [['password_confirmation' => 'Lain2026x'], 'password_confirmation', 'Konfirmasi password tidak sama.'],
]);

it('password tidak boleh sama dengan NIS, NISN, No. HP, atau nomor rekening', function () {
    $this->nasabah->update(['nis' => 'abc12345', 'nisn' => 'nisn123456']);
    $this->akun->update(['no_hp' => '08123456789a']);

    foreach (['abc12345', 'nisn123456', '08123456789a'] as $password) {
        sebagaiNasabah($this->akun)
            ->putJson('/api/v1/nasabah/password', ['password_lama' => 'rahasia123', 'password' => $password, 'password_confirmation' => $password])
            ->assertJsonValidationErrors(['password' => 'Password tidak boleh sama dengan NIS/NISN, No. HP, atau nomor rekening.']);
    }
});

it('ganti password: sesi lain keluar, sesi ini tetap', function () {
    sebagaiNasabah($this->akun)
        ->putJson('/api/v1/nasabah/password', ['password_lama' => 'rahasia123', 'password' => 'Baru2026x', 'password_confirmation' => 'Baru2026x'])
        ->assertOk()
        ->assertExactJson(['message' => 'Password berhasil diganti.']);

    $this->akun->refresh();

    expect($this->akun->password_diganti_at)->not->toBeNull()
        ->and($this->akun->versi_sesi)->toBe(1)
        ->and(Hash::check('Baru2026x', $this->akun->password))->toBeTrue()
        ->and(session(AutentikasiNasabah::KUNCI_VERSI))->toBe(1);

    sebagaiNasabah($this->akun, 0)->getJson('/api/v1/nasabah/me')->assertUnauthorized();
    sebagaiNasabah($this->akun, 1)->getJson('/api/v1/nasabah/me')->assertOk();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'ganti_password']);
});

it('ganti password dibatasi 10 kali per menit', function () {
    foreach (range(1, 10) as $i) {
        sebagaiNasabah($this->akun)->putJson('/api/v1/nasabah/password', ['password_lama' => 'salah'])->assertStatus(422);
    }

    sebagaiNasabah($this->akun)->putJson('/api/v1/nasabah/password', ['password_lama' => 'salah'])->assertStatus(429);
});

it('ganti No. HP dengan konfirmasi password, lalu login memakai nomor baru', function () {
    sebagaiNasabah($this->akun)
        ->putJson('/api/v1/nasabah/no-hp', ['password' => 'rahasia123', 'no_hp' => '+62 857-0000-1111', 'no_hp_confirmation' => '085700001111'])
        ->assertOk()
        ->assertExactJson([
            'message' => 'No. HP berhasil diganti. Gunakan No. HP baru untuk masuk berikutnya.',
            'data' => ['no_hp' => '085700001111'],
        ]);

    expect($this->akun->refresh()->no_hp)->toBe('085700001111')
        ->and($this->nasabah->refresh()->no_hp)->toBe('085700001111');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'ganti_no_hp', 'causer_id' => $this->akun->id]);

    // Sesi tetap berjalan.
    sebagaiNasabah($this->akun)->getJson('/api/v1/nasabah/me')->assertOk()->assertJsonPath('data.no_hp', '085700001111');

    dariIb()->postJson('/api/v1/nasabah/login', ['no_hp' => '081234567890', 'password' => 'rahasia123'])->assertStatus(422);
    dariIb()->postJson('/api/v1/nasabah/login', ['no_hp' => '085700001111', 'password' => 'rahasia123'])->assertOk();
});

it('memvalidasi ganti No. HP', function (array $input, string $field, string $pesan) {
    AkunNasabah::factory()->create(['no_hp' => '081299990000']);

    sebagaiNasabah($this->akun)
        ->putJson('/api/v1/nasabah/no-hp', [...['password' => 'rahasia123', 'no_hp' => '085700001111', 'no_hp_confirmation' => '085700001111'], ...$input])
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field => $pesan]);

    expect($this->akun->refresh()->no_hp)->toBe('081234567890');
})->with([
    'password salah' => [['password' => 'keliru'], 'password', 'Password saat ini salah.'],
    'dipakai akun lain' => [['no_hp' => '081299990000', 'no_hp_confirmation' => '081299990000'], 'no_hp', 'No. HP sudah dipakai akun lain.'],
    'sama dengan sekarang' => [['no_hp' => '6281234567890', 'no_hp_confirmation' => '081234567890'], 'no_hp', 'No. HP baru sama dengan No. HP sekarang.'],
    'format' => [['no_hp' => '12345', 'no_hp_confirmation' => '12345'], 'no_hp', 'Format No. HP tidak valid.'],
    'konfirmasi beda' => [['no_hp_confirmation' => '085700001112'], 'no_hp_confirmation', 'Konfirmasi No. HP tidak sama.'],
]);
