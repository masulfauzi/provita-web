<?php

namespace App\Http\Modules\IbNasabah\Resources;

use App\Http\Modules\IbNasabah\Models\AkunNasabah;
use App\Http\Modules\IbNasabah\Models\LoginNasabah;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data pribadi nasabah (GET/PUT /nasabah/profil). Riwayat login hanya bila $riwayat = true (GET).
 *
 * @mixin AkunNasabah
 */
class ProfilResource extends JsonResource
{
    public bool $riwayat = false;

    public function denganRiwayat(): static
    {
        $this->riwayat = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $n = $this->nasabah;

        return [
            'nama' => $n->nama,
            'nis' => $n->nis ?? $n->nomor,
            'kelas' => $n->kelas,
            'jenis_nasabah' => $n->jenis,
            'tempat_lahir' => $n->tempat_lahir,
            'tanggal_lahir' => $n->tanggal_lahir?->format('Y-m-d'),
            'jenis_kelamin' => $n->jenis_kelamin,
            'alamat_jalan' => $n->alamat_jalan,
            'rt' => $n->rt,
            'rw' => $n->rw,
            'kelurahan' => $n->kelurahan,
            'kecamatan' => $n->kecamatan,
            'kota' => $n->kota,
            'kode_pos' => $n->kode_pos,
            'no_hp' => $this->no_hp,
            'email' => $n->email,
            'wali_nama' => $n->nama_orang_tua,
            'wali_hubungan' => $n->wali_hubungan,
            'wali_hp' => $n->wali_hp,
            $this->mergeWhen($this->riwayat, fn () => [
                'riwayat_login' => LoginNasabah::query()
                    ->where('akun_nasabah_id', $this->id)
                    ->where('berhasil', true)
                    ->orderByDesc('waktu')
                    ->limit(10)
                    ->get()
                    ->map(fn (LoginNasabah $login) => [
                        'waktu' => $login->waktu->toIso8601String(),
                        'perangkat' => $login->perangkat,
                        'ip' => $login->ip,
                    ])->all(),
            ]),
        ];
    }
}
