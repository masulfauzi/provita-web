<?php

namespace App\Http\Modules\Pinjaman\Models;

use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris jadwal angsuran (pokok + jasa) dengan jatuh temponya.
 *
 * @property int $ke
 * @property Carbon $jatuh_tempo
 * @property string $pokok
 * @property string $jasa
 * @property string $total
 * @property Carbon|null $dibayar_tanggal
 */
class PinjamanAngsuran extends Model
{
    use HasUuids;

    protected $table = 'pinjaman_angsuran';

    protected $fillable = [
        'ke',
        'jatuh_tempo',
        'pokok',
        'jasa',
        'total',
        'dibayar_tanggal',
        'transaksi_id',
    ];

    protected function casts(): array
    {
        return [
            'ke' => 'integer',
            'jatuh_tempo' => 'date:Y-m-d',
            'pokok' => 'decimal:2',
            'jasa' => 'decimal:2',
            'total' => 'decimal:2',
            'dibayar_tanggal' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Pinjaman, $this>
     */
    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class);
    }

    /**
     * @return BelongsTo<Transaksi, $this>
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    /**
     * lunas | terlambat | belum. Terlambat dibandingkan dengan tanggal transaksi aktif.
     */
    public function status(Carbon $hariIni): string
    {
        return match (true) {
            $this->dibayar_tanggal !== null => 'lunas',
            $this->jatuh_tempo->lt($hariIni->copy()->startOfDay()) => 'terlambat',
            default => 'belum',
        };
    }
}
