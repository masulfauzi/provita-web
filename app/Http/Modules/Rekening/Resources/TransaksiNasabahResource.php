<?php

namespace App\Http\Modules\Rekening\Resources;

use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu baris mutasi rekening untuk Internet Banking.
 *
 * @mixin Transaksi
 */
class TransaksiNasabahResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kategori = config("ib.kategori.{$this->jenis}", 'koreksi');

        return [
            'id' => $this->id,
            'no_referensi' => $this->nomor ?? strtoupper(substr($this->id, 0, 8)),
            'waktu' => $this->tanggal->copy()->setTimeFrom($this->created_at)->toIso8601String(),
            'jenis' => Money::isNegative($this->mutasi) ? 'keluar' : 'masuk',
            'kategori' => $kategori,
            'keterangan' => filled($this->keterangan) ? $this->keterangan : config("ib.label_kategori.{$kategori}"),
            'nominal' => Money::of($this->nominal),
            'saldo_setelah' => Money::of($this->saldo_sesudah),
            // Transaksi tanpa user dibuat oleh sistem (scheduler/console).
            'petugas' => $this->user_id === null ? 'Sistem' : $this->user?->name,
            'loket' => null,
        ];
    }
}
