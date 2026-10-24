<?php

namespace App\Http\Modules\Rekening\Models;

use App\Http\Modules\Rekening\Database\Factories\JenisRekeningFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $kode
 * @property string $nama
 * @property string $prefix awalan nomor rekening, mis. TAB-
 * @property bool $is_active
 */
class JenisRekening extends Model
{
    /** @use HasFactory<JenisRekeningFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /**
     * Jenis bawaan yang dibuat oleh migrasi.
     */
    public const TABUNGAN = 'tabungan';

    protected $table = 'jenis_rekening';

    protected $fillable = [
        'kode',
        'nama',
        'prefix',
        'is_active',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): JenisRekeningFactory
    {
        return JenisRekeningFactory::new();
    }

    public static function kode(string $kode): self
    {
        return static::query()->where('kode', $kode)->firstOrFail();
    }

    /**
     * @return HasMany<Rekening, $this>
     */
    public function rekening(): HasMany
    {
        return $this->hasMany(Rekening::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function aktif(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jenis_rekening')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
