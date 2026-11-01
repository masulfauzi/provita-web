<?php

namespace App\Http\Modules\Rekening\Models;

use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu mutasi yang dicetak di buku tabungan pada (halaman, baris) tertentu.
 *
 * @property int $halaman
 * @property int $baris
 */
class CetakBukuTabungan extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'cetak_buku_tabungan';

    protected $fillable = ['rekening_id', 'transaksi_id', 'halaman', 'baris', 'user_id'];

    protected function casts(): array
    {
        return [
            'halaman' => 'integer',
            'baris' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Transaksi, $this>
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }
}
