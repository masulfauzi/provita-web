<?php

namespace App\Http\Modules\Akuntansi\Models;

use App\Http\Modules\Transaksi\Models\Transaksi;
use App\Models\User;
use App\Support\Finance\Immutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Jurnal tidak boleh diubah/dihapus; koreksi lewat JurnalService::balik().
 *
 * @property string $nomor
 * @property string $sumber
 * @property string|null $transaksi_id
 * @property string|null $pembalik_dari_id
 */
class Jurnal extends Model
{
    use HasUuids, Immutable;

    public const MANUAL = 'manual';

    public const TRANSAKSI = 'transaksi';

    public const KAS = 'kas';

    protected $table = 'jurnal';

    protected $fillable = [
        'nomor',
        'tanggal',
        'keterangan',
        'sumber',
        'transaksi_id',
        'pembalik_dari_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
        ];
    }

    /**
     * @return HasMany<JurnalDetail, $this>
     */
    public function detail(): HasMany
    {
        return $this->hasMany(JurnalDetail::class)->orderBy('urut');
    }

    /**
     * @return BelongsTo<Transaksi, $this>
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Jurnal, $this>
     */
    public function pembalikDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pembalik_dari_id');
    }

    /**
     * @return HasOne<Jurnal, $this>
     */
    public function pembalik(): HasOne
    {
        return $this->hasOne(self::class, 'pembalik_dari_id');
    }
}
