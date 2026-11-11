<?php

namespace App\Http\Modules\IbNasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Requests\SimpanProfilRequest;
use App\Http\Modules\IbNasabah\Resources\ProfilResource;
use App\Http\Modules\Nasabah\Models\Nasabah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfilNasabahController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => (new ProfilResource($this->akun($request)))->denganRiwayat()]);
    }

    /**
     * Simpan data pribadi. Hanya field yang diizinkan; nama, NIS, kelas, dan jenis diabaikan.
     */
    public function update(SimpanProfilRequest $request): JsonResponse
    {
        $akun = $this->akun($request);
        $nasabah = $akun->nasabah;
        $data = collect($request->validated())->only(SimpanProfilRequest::FIELD)->all();

        $kolom = [
            ...collect($data)->except('wali_nama')->all(),
            'nama_orang_tua' => $data['wali_nama'] ?? null,
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
        ];
        $kolom['alamat'] = Nasabah::susunAlamat($kolom);

        DB::transaction(function () use ($akun, $nasabah, $kolom) {
            $lama = $nasabah->only(array_keys($kolom));
            // Log nasabah dicatat manual (nilai lama/baru) agar penyebabnya akun nasabah.
            activity()->withoutLogging(fn () => $nasabah->update($kolom));
            $akun->forceFill(['profil_lengkap_at' => now()])->save();

            activity('nasabah')->performedOn($nasabah)->causedBy($akun)->event('simpan_profil')
                ->withProperties(['old' => $lama, 'attributes' => $nasabah->only(array_keys($kolom))])
                ->log('Data pribadi dilengkapi lewat Internet Banking');
        });

        return response()->json(['data' => new ProfilResource($akun->refresh()->load('nasabah'))]);
    }

    protected function akun(Request $request): AkunNasabah
    {
        return $request->user('nasabah')->load('nasabah');
    }
}
