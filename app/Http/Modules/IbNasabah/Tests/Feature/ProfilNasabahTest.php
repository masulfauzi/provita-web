<?php

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Models\LoginNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Models\Rekening;

beforeEach(function () {
    $this->nasabah = Nasabah::factory()->create(['nis' => '25040', 'nama' => 'Bagas Pratama', 'kelas' => 'XI PPLG 2', 'jenis_kelamin' => null, 'alamat' => null]);
    Rekening::factory()->create(['nasabah_id' => $this->nasabah->id]);
    $this->akun = AkunNasabah::factory()->create(['nasabah_id' => $this->nasabah->id, 'no_hp' => '081234567890']);
});

it('menampilkan profil beserta 10 riwayat login terbaru', function () {
    foreach (range(1, 12) as $i) {
        LoginNasabah::create(['akun_nasabah_id' => $this->akun->id, 'waktu' => now()->subDays($i), 'ip' => '10.10.2.15', 'perangkat' => 'Chrome · Android', 'berhasil' => true]);
    }

    sebagaiNasabah($this->akun)->getJson('/api/v1/nasabah/profil')
        ->assertOk()
        ->assertJsonPath('data.nama', 'Bagas Pratama')
        ->assertJsonPath('data.nis', '25040')
        ->assertJsonPath('data.kelas', 'XI PPLG 2')
        ->assertJsonPath('data.jenis_nasabah', 'siswa')
        ->assertJsonPath('data.jenis_kelamin', null)
        ->assertJsonCount(10, 'data.riwayat_login')
        ->assertJsonPath('data.riwayat_login.0.perangkat', 'Chrome · Android');
});

it('menyimpan profil dan menyusun alamat; nama, NIS, kelas, jenis, No. HP diabaikan', function () {
    sebagaiNasabah($this->akun)
        ->putJson('/api/v1/nasabah/profil', profilValid(['nama' => 'Nama Palsu', 'kelas' => 'XII', 'nis' => '1', 'jenis' => 'guru', 'no_hp' => '089999999999']))
        ->assertOk()
        ->assertJsonPath('data.no_hp', '081234567890')
        ->assertJsonPath('data.alamat_jalan', 'Jl. Sidodadi Barat No. 8')
        ->assertJsonPath('data.wali_nama', 'Slamet Riyadi')
        ->assertJsonMissingPath('data.riwayat_login');

    $nasabah = $this->nasabah->refresh();

    expect($nasabah->only('nama', 'kelas', 'nis', 'jenis'))->toBe(['nama' => 'Bagas Pratama', 'kelas' => 'XI PPLG 2', 'nis' => '25040', 'jenis' => 'siswa'])
        ->and($nasabah->nama_orang_tua)->toBe('Slamet Riyadi')
        ->and($nasabah->tanggal_lahir->toDateString())->toBe('2008-03-14')
        ->and($nasabah->alamat)->toBe('Jl. Sidodadi Barat No. 8, RT 003/RW 005, Kel. Karangkidul, Kec. Semarang Tengah, Kota Semarang 50136')
        ->and($this->akun->refresh()->no_hp)->toBe('081234567890');

    $this->assertDatabaseHas('activity_log', ['log_name' => 'nasabah', 'event' => 'simpan_profil', 'causer_id' => $this->akun->id]);
});

it('siswa wajib mengisi data wali; guru tidak', function () {
    $tanpaWali = profilValid(['wali_nama' => null, 'wali_hubungan' => null, 'wali_hp' => null]);

    sebagaiNasabah($this->akun)->putJson('/api/v1/nasabah/profil', $tanpaWali)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['wali_nama', 'wali_hubungan', 'wali_hp']);

    $guru = Nasabah::factory()->create(['jenis' => 'guru', 'nisn' => null]);
    $akunGuru = AkunNasabah::factory()->create(['nasabah_id' => $guru->id]);

    sebagaiNasabah($akunGuru)->putJson('/api/v1/nasabah/profil', $tanpaWali)->assertOk();
});

it('memvalidasi format isian profil', function (array $ubah, string $field, string $pesan) {
    sebagaiNasabah($this->akun)->putJson('/api/v1/nasabah/profil', profilValid($ubah))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field => $pesan]);
})->with([
    'hp wali' => [['wali_hp' => '0712345678'], 'wali_hp', 'Format No. HP tidak valid.'],
    'kode pos' => [['kode_pos' => '501'], 'kode_pos', 'Kode pos berisi 5 angka.'],
    'rt' => [['rt' => '1234'], 'rt', 'RT berisi 1–3 angka.'],
    'tanggal lahir' => [['tanggal_lahir' => '14-03-2008'], 'tanggal_lahir', 'Format tanggal lahir harus YYYY-MM-DD.'],
    'jenis kelamin' => [['jenis_kelamin' => 'X'], 'jenis_kelamin', 'Jenis kelamin harus L atau P.'],
    'email' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
    'wajib' => [['kelurahan' => ''], 'kelurahan', 'Kelurahan wajib diisi.'],
]);
