<?php

namespace App\Http\Modules\Akuntansi\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $kode
 * @property string $nama
 * @property string $kategori
 * @property string|null $peran akun sistem yang dipakai jurnal otomatis, mis. kas, simpanan
 * @property bool $is_active
 */
class Akun extends Model
{
    use HasUuids, LogsActivity;

    public const KATEGORI = [
        'aset' => 'Aset',
        'kewajiban' => 'Kewajiban',
        'ekuitas' => 'Ekuitas',
        'pendapatan' => 'Pendapatan',
        'beban' => 'Beban',
    ];

    /**
     * Kategori bersaldo normal debit; lainnya kredit.
     */
    public const SALDO_DEBIT = ['aset', 'beban'];

    public const KAS = 'kas';

    public const BANK = 'bank';

    public const SIMPANAN = 'simpanan';

    public const MODAL = 'modal';

    public const PENDAPATAN_ADMINISTRASI = 'pendapatan_administrasi';

    protected $table = 'akun';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'is_active',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function peran(string $peran): self
    {
        return static::query()->where('peran', $peran)->firstOrFail();
    }

    public function saldoNormalDebit(): bool
    {
        return in_array($this->kategori, self::SALDO_DEBIT, true);
    }

    /**
     * @return HasMany<JurnalDetail, $this>
     */
    public function detail(): HasMany
    {
        return $this->hasMany(JurnalDetail::class);
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
            ->useLogName('akun')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
