<?php

namespace App\Http\Modules\Akuntansi\Models;

use App\Support\Finance\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $debit
 * @property string $kredit
 */
class JurnalDetail extends Model
{
    use HasUuids, Immutable;

    protected $table = 'jurnal_detail';

    protected $fillable = [
        'jurnal_id',
        'akun_id',
        'debit',
        'kredit',
        'keterangan',
        'urut',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'kredit' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Jurnal, $this>
     */
    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(Jurnal::class);
    }

    /**
     * @return BelongsTo<Akun, $this>
     */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class);
    }
}
