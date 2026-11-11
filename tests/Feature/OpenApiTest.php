<?php

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Pengaturan\Database\Seeders\PengaturanSeeder;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Http\Modules\Transaksi\Services\TransaksiService;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
| Spesifikasi OpenAPI (docs/openapi/nasabah.yaml) harus sinkron dengan route dan bentuk
| respons API Internet Banking. Test ini gagal bila endpoint/field berubah tanpa memperbarui
| dokumentasi.
*/

function spesifikasiIb(): array
{
    return Yaml::parseFile(base_path('docs/openapi/nasabah.yaml'));
}

/**
 * Field wajib skema (mengikuti $ref dan allOf).
 *
 * @return list<string>
 */
function fieldWajib(array $spec, array $skema): array
{
    if (isset($skema['$ref'])) {
        return fieldWajib($spec, $spec['components']['schemas'][basename($skema['$ref'])]);
    }

    $wajib = $skema['required'] ?? [];

    foreach ($skema['allOf'] ?? [] as $bagian) {
        $wajib = [...$wajib, ...fieldWajib($spec, $bagian)];
    }

    sort($wajib);

    return array_values(array_unique($wajib));
}

it('spesifikasi berformat OpenAPI 3.1 yang valid', function () {
    $spec = spesifikasiIb();

    expect($spec['openapi'])->toBe('3.1.0')
        ->and($spec['info']['title'])->toBe('API Internet Banking Bank Provita')
        ->and($spec['paths'])->toHaveCount(10);

    // Semua $ref menunjuk komponen yang ada.
    preg_match_all("/\\\$ref: '#\\/components\\/(\\w+)\\/(\\w+)'/", file_get_contents(base_path('docs/openapi/nasabah.yaml')), $ref, PREG_SET_ORDER);

    foreach ($ref as [, $jenis, $nama]) {
        expect($spec['components'][$jenis])->toHaveKey($nama);
    }
});

it('setiap route Internet Banking terdokumentasi dan sebaliknya', function () {
    $route = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/nasabah') || $r->uri() === 'api/v1/sanctum/csrf-cookie')
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')
            ->map(fn ($m) => strtolower($m).' /'.substr($r->uri(), strlen('api/v1/'))))
        ->sort()->values()->all();

    $dokumentasi = collect(spesifikasiIb()['paths'])
        ->flatMap(fn ($operasi, $path) => collect(array_keys($operasi))->map(fn ($m) => $m.' '.ltrim($path, '/')))
        ->map(fn ($item) => preg_replace('#^(\w+) #', '$1 /', $item))
        ->sort()->values()->all();

    expect($dokumentasi)->toBe($route)->toHaveCount(11);
});

it('field wajib di skema sama dengan respons API sungguhan', function () {
    $this->seed(PengaturanSeeder::class);
    $spec = spesifikasiIb();
    $nasabah = Nasabah::factory()->create();
    $akun = AkunNasabah::factory()->create(['nasabah_id' => $nasabah->id]);
    $rekening = Rekening::factory()->create(['nasabah_id' => $nasabah->id]);
    app(TransaksiService::class)->setor($rekening, 10000);
    $akun->riwayatLogin()->create(['waktu' => now(), 'berhasil' => true, 'perangkat' => 'Chrome · Android']);

    $kunci = fn (array $data) => collect(array_keys($data))->sort()->values()->all();
    $skema = fn (string $path, string $method = 'get') => $spec['paths'][$path][$method]['responses']['200']['content']['application/json']['schema']['properties'];

    $me = sebagaiNasabah($akun)->getJson('/api/v1/nasabah/me')->assertOk()->json('data');
    expect($kunci($me))->toBe(fieldWajib($spec, $skema('/nasabah/me')['data']));

    $profil = sebagaiNasabah($akun)->getJson('/api/v1/nasabah/profil')->assertOk()->json('data');
    expect($kunci($profil))->toBe(fieldWajib($spec, $skema('/nasabah/profil')['data']))
        ->and($kunci($profil['riwayat_login'][0]))->toBe(fieldWajib($spec, ['$ref' => '#/components/schemas/RiwayatLogin']));

    $daftar = sebagaiNasabah($akun)->getJson('/api/v1/nasabah/rekening')->assertOk()->json();
    expect($kunci($daftar))->toBe(['data', 'meta'])
        ->and($kunci($daftar['data'][0]))->toBe(fieldWajib($spec, ['$ref' => '#/components/schemas/Rekening']))
        ->and($kunci($daftar['meta']))->toBe(fieldWajib($spec, ['$ref' => '#/components/schemas/MetaSaldo']));

    $mutasi = sebagaiNasabah($akun)->getJson("/api/v1/nasabah/rekening/{$rekening->id}/mutasi")->assertOk()->json();
    expect($kunci($mutasi))->toBe(fieldWajib($spec, $spec['paths']['/nasabah/rekening/{id}/mutasi']['get']['responses']['200']['content']['application/json']['schema']))
        ->and($kunci($mutasi['data'][0]))->toBe(fieldWajib($spec, ['$ref' => '#/components/schemas/Transaksi']))
        ->and($kunci($mutasi['meta']))->toBe(fieldWajib($spec, ['$ref' => '#/components/schemas/MetaMutasi']))
        ->and($kunci($mutasi['ringkasan']))->toBe(fieldWajib($spec, ['$ref' => '#/components/schemas/RingkasanMutasi']));

    // Nominal string 2 desimal sesuai pola di skema.
    $pola = '/'.$spec['components']['schemas']['Nominal']['pattern'].'/';
    expect($daftar['data'][0]['saldo'])->toMatch($pola)
        ->and($mutasi['data'][0]['nominal'])->toMatch($pola)
        ->and($mutasi['ringkasan']['saldo_akhir'])->toMatch($pola);
});

it('halaman Swagger UI dan file spesifikasi bisa dibuka tanpa login', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('swagger-ui-bundle.js', false)
        ->assertSee(route('docs.api.spec'), false);

    $respons = $this->get('/docs/api/openapi.yaml')->assertOk();

    expect($respons->headers->get('Content-Type'))->toStartWith('application/yaml')
        ->and(Yaml::parse($respons->streamedContent() ?: file_get_contents(base_path('docs/openapi/nasabah.yaml'))))->toHaveKey('paths');
});
