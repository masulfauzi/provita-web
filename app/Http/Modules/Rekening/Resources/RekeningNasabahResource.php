<?php

namespace App\Http\Modules\Rekening\Resources;

use App\Http\Modules\Pengaturan\Services\PengaturanService;
use App\Http\Modules\Rekening\Models\Rekening;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rekening untuk Internet Banking nasabah. Kolom internal (nasabah_id, saldo_awal) tidak dikirim.
 *
 * @mixin Rekening
 */
class RekeningNasabahResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kode = $this->jenisRekening?->kode;
        $produk = config("ib.produk.{$kode}", 'reguler');
        $bunga = (string) app(PengaturanService::class)->get('suku_bunga_tabungan', '0');

        return [
            'id' => $this->id,
            'no_rekening' => $this->nomor,
            'produk' => $produk,
            'nama_produk' => $this->jenisRekening?->nama,
            'saldo' => Money::of($this->saldo),
            'status' => $this->status === Rekening::TUTUP ? 'ditutup' : 'aktif',
            'tanggal_buka' => $this->tanggal_buka?->format('Y-m-d'),
            // Suku bunga per tahun dari pengaturan (rekening reguler); belum ada bunga per produk.
            'suku_bunga' => $produk === 'reguler' && is_numeric($bunga) && $bunga > 0 ? number_format((float) $bunga, 2, '.', '') : null,
            'jatuh_tempo' => null,
            'jangka_bulan' => null,
            'setoran_rutin' => null,
        ];
    }
}
