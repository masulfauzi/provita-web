<?php

namespace App\Http\Modules\Autodebet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Hasil pemrosesan satu jadwal autodebet (berhasil atau gagal).
 */
class AutodebetRiwayat extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const BERHASIL = 'berhasil';

    public const GAGAL = 'gagal';

    protected $table = 'autodebet_riwayat';

    protected $fillable = ['autodebet_id', 'jadwal', 'tanggal', 'nominal', 'status', 'alasan', 'transaksi_keluar_id', 'transaksi_masuk_id'];

    protected function casts(): array
    {
        return [
            'jadwal' => 'date:Y-m-d',
            'tanggal' => 'date:Y-m-d',
            'nominal' => 'decimal:2',
        ];
    }
}
