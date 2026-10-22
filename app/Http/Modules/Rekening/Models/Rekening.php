<?php

namespace App\Http\Modules\Rekening\Models;

use App\Http\Modules\Nasabah\Models\Nasabah;
use App\Http\Modules\Rekening\Database\Factories\RekeningFactory;
use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $nomor
 * @property string $jenis
 * @property string $status
 * @property string $saldo_awal
 * @property string $saldo
 */
class Rekening extends Model
{
    /** @use HasFactory<RekeningFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /**
     * Jenis rekening => [label, prefix nomor].
     */
    public const JENIS = [
        'tabungan' => ['label' => 'Tabungan', 'prefix' => 'TAB-'],
    ];

    public const AKTIF = 'aktif';

    public const TUTUP = 'tutup';

    protected $table = 'rekening';

    protected $fillable = [
        'nomor',
        'nasabah_id',
        'jenis',
        'saldo_awal',
        'status',
        'tanggal_buka',
        'tanggal_tutup',
    ];

    protected function casts(): array
    {
        return [
            'saldo_awal' => 'decimal:2',
            'saldo' => 'decimal:2',
            'tanggal_buka' => 'date:Y-m-d',
            'tanggal_tutup' => 'date:Y-m-d',
        ];
    }

    protected static function newFactory(): RekeningFactory
    {
        return RekeningFactory::new();
    }

    /**
     * @return BelongsTo<Nasabah, $this>
     */
    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class);
    }

    /**
     * @return HasMany<Transaksi, $this>
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class);
    }

    public function isAktif(): bool
    {
        return $this->status === self::AKTIF;
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Kolom saldo (cache) tidak dicatat; mutasinya sudah tercatat di transaksi.
        return LogOptions::defaults()
            ->useLogName('rekening')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
