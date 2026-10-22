<?php

namespace App\Http\Modules\Rekening\Models;

use App\Http\Modules\Rekening\Database\Factories\RekeningFactory;
use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $saldo_awal
 * @property string $saldo
 */
class Rekening extends Model
{
    /** @use HasFactory<RekeningFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'rekening';

    protected $fillable = [
        'nomor',
        'nama',
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
            'tanggal_buka' => 'date',
            'tanggal_tutup' => 'date',
        ];
    }

    protected static function newFactory(): RekeningFactory
    {
        return RekeningFactory::new();
    }

    /**
     * @return HasMany<Transaksi, $this>
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class);
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
