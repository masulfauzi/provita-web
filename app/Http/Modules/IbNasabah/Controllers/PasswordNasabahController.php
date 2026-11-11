<?php

namespace App\Http\Modules\IbNasabah\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AutentikasiNasabah;
use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Requests\GantiPasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordNasabahController extends Controller
{
    /**
     * Ganti password. Sesi lain (perangkat lain) dikeluarkan; sesi ini tetap login.
     */
    public function update(GantiPasswordRequest $request): JsonResponse
    {
        /** @var AkunNasabah $akun */
        $akun = $request->user('nasabah');

        $akun->forceFill([
            'password' => $request->validated('password'),
            'wajib_ganti_password' => false,
            'password_diganti_at' => now(),
            'versi_sesi' => $akun->versi_sesi + 1,
        ])->save();

        $request->session()->regenerate();
        $request->session()->put(AutentikasiNasabah::KUNCI_VERSI, $akun->versi_sesi);

        activity('nasabah')->performedOn($akun->nasabah)->causedBy($akun)->event('ganti_password')
            ->withProperties(['ip' => $request->ip()])
            ->log('Password Internet Banking diganti');

        return response()->json(['message' => 'Password berhasil diganti.']);
    }
}
