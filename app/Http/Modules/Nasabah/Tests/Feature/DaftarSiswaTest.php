<?php

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Respons API /api/siswa/cari (data fiktif).
 *
 * @return array<string, mixed>
 */
function apiCariSiswa(): array
{
    return [
        'success' => true,
        'message' => 'Data siswa retrieved successfully',
        'data' => [
            ['id_siswa' => 'a1', 'nama_siswa' => ' Budi  Santoso ', 'nis' => '24001', 'nisn' => '0012345678', 'kelas' => 'x tkj 1'],
            ['id_siswa' => 'a2', 'nama_siswa' => 'Budi Prasetyo', 'nis' => '24002', 'nisn' => '0087654321', 'kelas' => 'XI AK 2'],
            ['id_siswa' => 'a3', 'nama_siswa' => 'Budiman', 'nis' => '24003', 'nisn' => '', 'kelas' => 'XII TKJ 1'],
        ],
    ];
}

beforeEach(function () {
    $this->teller = userWithRole('teller');
});

it('semua peran bisa membuka daftar siswa dan menu tampil', function (string $peran) {
    $this->actingAs(userWithRole($peran))->get('/daftar-siswa')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Nasabah::DaftarSiswa')
            ->where('hasil', null)
            ->where('menu', fn ($menu) => collect($menu)->pluck('items')->flatten(1)->contains('label', 'Daftar siswa')));
})->with(['teller', 'bendahara', 'admin']);

it('tanpa permission tidak bisa membuka daftar siswa', function () {
    $this->actingAs(User::factory()->create())->get('/daftar-siswa')->assertForbidden();
});

it('mencari siswa ke API sekolah dan menandai yang sudah nasabah', function () {
    Http::fake(['apps.smkn2semarang.sch.id/*' => Http::response(apiCariSiswa())]);
    $nasabah = Nasabah::factory()->create(['nisn' => '0012345678', 'nomor' => 'NSB-000001']);

    $this->actingAs($this->teller)->get('/daftar-siswa?nama=budi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nama', 'budi')
            ->where('galat', null)
            ->has('hasil', 3)
            ->where('hasil.0', [
                'nama' => 'Budi Santoso',
                'nis' => '24001',
                'nisn' => '0012345678',
                'kelas' => 'X TKJ 1',
                'nasabah' => ['id' => $nasabah->id, 'nomor' => 'NSB-000001', 'nama' => $nasabah->nama, 'is_active' => true],
            ])
            ->where('hasil.1.nasabah', null)
            ->where('hasil.2.nisn', null)
            ->where('hasil.2.nasabah', null));

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://apps.smkn2semarang.sch.id/api/siswa/cari')
        && $request['nama'] === 'budi');
});

it('hasil kosong bila siswa tidak ditemukan', function () {
    Http::fake(['*' => Http::response(['success' => false, 'message' => 'Siswa not found'], 404)]);

    $this->actingAs($this->teller)->get('/daftar-siswa?nama=zzzz')
        ->assertInertia(fn (Assert $page) => $page->where('hasil', [])->where('galat', null));
});

it('menampilkan pesan bila API sekolah tidak bisa dihubungi', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->actingAs($this->teller)->get('/daftar-siswa?nama=budi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('hasil', null)->where('galat', 'API data siswa tidak dapat dihubungi.'));
});

it('nama kurang dari 3 huruf atau kosong tidak memanggil API', function () {
    Http::fake();

    $this->actingAs($this->teller)->get('/daftar-siswa?nama=bu')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('galatNama', 'Nama siswa minimal 3 huruf.')->where('hasil', null));

    $this->actingAs($this->teller)->get('/daftar-siswa?nama=')->assertInertia(fn (Assert $page) => $page->where('galatNama', null));

    Http::assertNothingSent();
});

it('tombol daftarkan membuka form tambah nasabah dengan NISN terisi', function () {
    $this->actingAs($this->teller)->get('/nasabah/create?nisn=0087654321')
        ->assertInertia(fn (Assert $page) => $page->component('Nasabah::Create')->where('nisn', '0087654321'));

    $this->actingAs($this->teller)->get('/nasabah/create?nisn=12ab')
        ->assertInertia(fn (Assert $page) => $page->where('nisn', null));
});
