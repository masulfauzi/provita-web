<?php

namespace App\Http\Modules\IbNasabah\Resources;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Models\LoginNasabah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Data nasabah yang login (GET /nasabah/me, POST /nasabah/login).
 *
 * @mixin AkunNasabah
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nasabah = $this->nasabah;

        // Waktu login berhasil sebelum sesi ini (entri terbaru adalah sesi ini sendiri).
        $sebelumnya = LoginNasabah::query()
            ->where('akun_nasabah_id', $this->id)
            ->where('berhasil', true)
            ->orderByDesc('waktu')
            ->skip(1)
            ->value('waktu');

        return [
            'id' => $nasabah->id,
            'nis' => $nasabah->nis ?? $nasabah->nomor,
            'nama' => $nasabah->nama,
            'nama_panggilan' => explode(' ', trim($nasabah->nama))[0],
            'kelas' => $nasabah->kelas,
            'jenis_nasabah' => $nasabah->jenis,
            'nomor_rekening' => $nasabah->rekening()->orderBy('nomor')->pluck('nomor')->all(),
            'no_hp' => $this->no_hp,
            'login_terakhir' => $sebelumnya ? Carbon::parse($sebelumnya)->toIso8601String() : null,
        ];
    }
}
