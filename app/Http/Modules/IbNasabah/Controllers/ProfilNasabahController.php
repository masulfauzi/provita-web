<?php

namespace App\Http\Modules\IbNasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Requests\GantiNoHpRequest;
use App\Http\Modules\IbNasabah\Requests\SimpanProfilRequest;
use App\Http\Modules\IbNasabah\Resources\ProfilResource;
use App\Http\Modules\IbNasabah\Services\AkunNasabahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilNasabahController extends Controller
{
    public function __construct(protected AkunNasabahService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => (new ProfilResource($this->akun($request)))->denganRiwayat()]);
    }

    /**
     * Simpan data pribadi. Hanya field yang diizinkan; nama, NIS, kelas, jenis, dan No. HP diabaikan.
     */
    public function update(SimpanProfilRequest $request): JsonResponse
    {
        $akun = $this->akun($request);
        $this->service->simpanProfil($akun, $request->validated(), 'simpan_profil', 'Data pribadi diperbarui lewat Internet Banking');

        return response()->json(['data' => new ProfilResource($akun->refresh()->load('nasabah'))]);
    }

    /**
     * Ganti No. HP login (konfirmasi password). Sesi tetap berjalan.
     */
    public function updateNoHp(GantiNoHpRequest $request): JsonResponse
    {
        $akun = $this->akun($request);
        $this->service->gantiNoHp($akun, $request->validated('no_hp'));

        return response()->json([
            'message' => 'No. HP berhasil diganti. Gunakan No. HP baru untuk masuk berikutnya.',
            'data' => ['no_hp' => $akun->no_hp],
        ]);
    }

    protected function akun(Request $request): AkunNasabah
    {
        return $request->user('nasabah')->load('nasabah');
    }
}
